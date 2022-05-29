<?php
namespace Csgt\Crud;

use DB;
use Storage;
use Exception;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrudController extends BaseController
{
    private $uniqueid     = '___id___';
    private $model        = null;
    private $showExport   = true;
    private $showSearch   = true;
    private $stateSave    = true;
    private $responsive   = true;
    private $layout       = 'layouts.app';
    private $perPage      = 50;
    private $title        = '';
    private $fields       = null;
    private $hiddenFields = [];
    private $permissions  = ['create' => false, 'update' => false, 'destroy' => false];
    private $orders       = [];
    private $extraButtons = [];
    private $extraActions = [];
    private $joins        = [];
    private $leftJoins    = [];
    private $wheres       = [];
    private $wheresIn     = [];
    private $wheresRaw    = [];
    private $ignoreFields = ['_token'];
    private $breadcrumb   = ['mostrar' => true, 'breadcrumb' => []];

    public function index(Request $request)
    {
        if (!$this->model) {
            abort(400, 'setModel is required.');
        }
        $breadcrumb = $this->generateBreadcrumb('index');
        $props      = [
            'columns'      => $this->getShowFields(),
            'urldata'      => $request->url() . '/data' . $this->getQueryString($request),
            'urledit'      => $request->url() . '/{id}/edit' . $this->getQueryString($request),
            'urldestroy'   => $request->url() . '/{id}' . $this->getQueryString($request),
            'urlcreate'    => $request->url() . '/create' . $this->getQueryString($request),
            'extrabuttons' => $this->extraButtons,
            'extraactions' => $this->extraActions,
            // 'stateSave'       => $this->stateSave,
            // 'showExport'      => $this->showExport,
            // 'showSearch'      => $this->showSearch,
            // 'responsive'      => $this->responsive,
            // 'perPage'         => $this->perPage,
            // 'permisos'        => $this->permissions,
            // 'orders'          => $this->orders,
            // 'queryParameters' => $this->getQueryString($request),
        ];

        return view('csgtcrud::index')
            ->with('title', $this->title)
            ->with('component', 'csgtcrud-index')
            ->with('layout', $this->layout)
            ->with('breadcrumb', $breadcrumb)
            ->with('props', $props);
    }

    public function show(Request $request, $aId)
    {
        $data = $this->model->find($aId);
        if ($request->expectsJson()) {
            return response()->json($data);
        }
    }

    public function edit(Request $request, $aId)
    {
        $urlUpdate = '/' . $this->downLevel($request->path());
        $urlIndex  = $this->downLevel($urlUpdate);
        if ($aId) {
            $state = $this->model->find($aId);
            $this->getMultiFields()->each(function ($multi) use ($state) {
                $state->load($multi);
            });
            $breadcrumb = $this->generateBreadcrumb('edit', $urlIndex);
        } else {
            $state      = $this->emptyState();
            $breadcrumb = $this->generateBreadcrumb('create', $urlUpdate);
        }
        $state = $state->jsonSerialize();

        $editFields = $this->getLocalEditFields();
        foreach ($editFields as $editField) {
            switch ($editField['type']) {
                case 'datetime':
                    $state[$editField['field']] = Carbon::parse($state[$editField['field']])->format('Y-m-d\TH:i:s');
                    break;
                case 'date':
                    $state[$editField['field']] = Carbon::parse($state[$editField['field']])->format('Y-m-d');
                    break;
                case 'time':
                    $state[$editField['field']] = Carbon::parse($state[$editField['field']])->format('H:i:s');
                    break;
                default:
                    break;
            }
        }

        $queryParameters = $this->getQueryString($request);

        $props = [
            'urlupdate'       => $urlUpdate,
            'urlindex'        => $urlIndex,
            'columns'         => $editFields,
            'queryParameters' => $queryParameters,
        ];

        return view('csgtcrud::edit')
            ->with('component', 'csgtcrud-edit')
            ->with('state', $state)
            ->with('layout', $this->layout)
            ->with('breadcrumb', $breadcrumb)
            ->with('props', $props);
    }

    public function create(Request $request)
    {
        return $this->edit($request, null);
    }

    public function store(Request $request)
    {
        return $this->update($request, 0);
    }

    public function update(Request $request, $aId)
    {
        $rules = [
            'email'  => 'email|unique:usuarios',
            'nombre' => 'numeric',
            'roles'  => 'required|min:1',
        ];
        $request->validate($rules);
        $fields = $request->except($this->ignoreFields);
        $fields = array_merge($fields, $this->hiddenFields);

        $newMulti = [];
        foreach ($this->fields as $field) {
            if (array_key_exists($field['field'], $fields)) {
                if ($field['type'] == 'date' || $field['type'] == 'datetime') {
                    $aFecha    = $fields[$field['field']];
                    $fechahora = explode(' ', $fields[$field['field']]);

                    if (sizeof($fechahora) == 2) {
                        $formato    = 'd/m/Y H:i';
                        $formatoOut = 'Y-m-d H:i';
                        $aFecha     = substr($aFecha, 0, 16);
                    } else {
                        $formato    = 'd/m/Y';
                        $formatoOut = 'Y-m-d';
                    }

                    try {
                        $fecha                   = Carbon::createFromFormat($formato, $aFecha);
                        $fields[$field['field']] = $fecha;
                    } catch (Exception $e) {
                        $fields[$field['field']] = null;
                    }
                }
            }

            if (($field['type'] == 'file') || ($field['type'] == 'image')) {
                if ($request->hasFile($field['field'])) {
                    $file = $request->file($field['field']);

                    $filename = date('Ymdhis') . mt_rand(1, 1000) . '.' . strtolower($file->getClientOriginalExtension());
                    $path     = public_path() . $field['filepath'];

                    if (!file_exists($path)) {
                        mkdir($path, 0777, true);
                    }

                    $file->move($path, $filename);
                    $fields[$field['field']] = $filename;
                    $fields[$field['field']] = $filename;
                }
            }

            if ($field['type'] == 'securefile') {
                if ($request->hasFile($field['field'])) {
                    if ($aId !== 0) {
                        $existing = $this->model->find($aId);
                        if ($existing) {
                            if ($existing->{$field['field']} != '') {
                                Storage::disk($field['filedisk'])->delete($existing->{$field['field']});
                            }
                        }
                    }

                    $filename                = Storage::disk($field['filedisk'])->putFile($field['filepath'], $request->file($field['field']));
                    $fields[$field['field']] = $filename;
                    $fields[$field['field']] = $filename;
                }
            }

            if ($field['type'] == 'multi') {
                if (array_key_exists($field['field'], $fields)) {
                    $newMulti[$field['field']] = $fields[$field['field']];
                } else {
                    $newMulti[$field['field']] = [];
                }

                $fields = Arr::except($fields, $field['field']);
            }
        }

        $queryParameters = $this->getQueryString($request);
        if ($aId === 0) {
            $item = $this->model->create($fields);
            foreach ($newMulti as $relationship => $values) {
                $item->{$relationship}()->attach($values);
            }
            if ($request->expectsJson()) {
                return response()->json($item);
            }

            return redirect()->to($request->path() . $queryParameters);
        } else {
            $m = $this->model->find($aId);
            $m->update($fields);
            foreach ($newMulti as $relationship => $values) {
                $m->{$relationship}()->detach();
                $m->{$relationship}()->attach($values);
            }
            if ($request->expectsJson()) {
                return response()->json($m);
            }

            return redirect()->to($this->downLevel($request->path()) . $queryParameters);
        }
    }

    public function destroy(Request $request, $aId)
    {
        $this->model->destroy($aId);

        return response()->json('ok');
    }

    public function data(Request $request)
    {
        $data = $this->model->query()
            ->select($this->model->getTable() . '.' . $this->model->getKeyName() . ' AS ' . $this->uniqueid);

        $fields = $this->getLocalShowFields();
        $fields->each(function ($field) use ($data) {
            $data->addSelect($field['field']);
        });

        $foreigns = $this->getForeignShowFields();
        foreach ($foreigns as $relation => $fields) {
            $foreignModel = $data->{$relation}();

            $data = $data->with([$relation => function ($query) use ($fields, $foreignModel) {
                $query->addSelect($foreignModel instanceof BelongsTo ? $foreignModel->getOwnerKeyName() : $foreignModel->getForeignKeyName());
                foreach ($fields as $field) {
                    foreach ($field as $fiel) {
                        $query->addSelect(DB::raw($fiel));
                    }
                }
            }]);

            foreach ($fields as $field) {
                $data->addSelect($foreignModel instanceof BelongsTo ? $foreignModel->getForeignKeyName() : $foreignModel->getQualifiedParentKeyName());
            }
        }

        foreach ($this->leftJoins as $leftJoin) {
            $data->leftJoin($leftJoin['tabla'], $leftJoin['col1'], $leftJoin['operador'], $leftJoin['col2']);
        }

        //Filtramos a partir del where
        foreach ($this->wheres as $where) {
            $data->where($where['columna'], $where['operador'], $where['valor']);
        }
        //Filtramos a partir del whereIn
        foreach ($this->wheresIn as $whereIn) {
            $data = $data->whereIn($whereIn['columna'], $whereIn['arreglo']);
        }
        //Filtramos a partir de WhereRaw
        foreach ($this->wheresRaw as $whereRaw) {
            $data->whereRaw($whereRaw);
        }

        if ($request->sort['field']) {
            $data->orderBy($request->sort['field'], $request->sort['direction']);
        }

        foreach ($request->searches as $search) {
            if (empty($search['field']) || empty($search['value'])) {
                continue;
            }
            if ($search['conjunction'] == 'or') {
                $data->orWhere($search['field'],
                    $search['operator'],
                    ($search['operator'] == 'LIKE' ? "%" : "") . $search['value'] . ($search['operator'] == 'LIKE' ? "%" : "")
                );
            } else {
                $data->where(
                    $search['field'],
                    $search['operator'],
                    ($search['operator'] == 'LIKE' ? "%" : "") . $search['value'] . ($search['operator'] == 'LIKE' ? "%" : "")
                );
            }
        }

        $data = $data->paginate($this->perPage)->withQueryString();

        return response()->json($data);
    }

    private function downLevel($aPath)
    {
        $arr = explode('/', $aPath);
        array_pop($arr);
        $route = implode('/', $arr);

        return $route;
    }

    private function emptyState()
    {
        $ret = [
            $this->model->getKeyName() => 0,
        ];

        foreach ($this->getLocalEditFields() as $item) {
            $ret[$item['field']] = $item['default'];
        }

        $this->getMultiFields()->each(function ($multi) use ($ret) {
            $ret[$multi] = [];
        });

        return $ret;
    }

    public function getTemplate()
    {
        return $this->layout;
    }

    public function generateBreadcrumb($aTipo, $aUrl = '')
    {
        $html = '';
        if ($this->breadcrumb['mostrar']) {
            $html .= '<ol class="breadcrumb float-sm-right">';
            if (empty($this->breadcrumb['breadcrumb'])) {
                switch ($aTipo) {
                    case 'edit':
                        $html .= '<li class="breadcrumb-item">
                                <a href="/' . $aUrl . '">' . $this->title . '</a>
                            </li>
                            <li class="breadcrumb-item active">
                                <i class="far fa-pencil"></i> Editar
                            </li>';
                        break;
                    case 'create':
                        $html .= '<li class="breadcrumb-item">
                                <a href="/' . $aUrl . '">' . $this->title . '</a>
                            </li>
                            <li class="breadcrumb-item active">
                                <i class="far fa-plus-circle"></i> Nuevo
                            </li>';
                        break;
                    default:
                        $html .= '<li class="breadcrumb-item active">' . $this->title . '</li>';
                        break;
                }
            } else {
                $array     = $this->breadcrumb['breadcrumb'];
                $htmlArray = [];
                $lastItem  = end($array);
                switch ($aTipo) {
                    case 'edit':
                        $htmlArray = array_map(function ($item) use ($aUrl, $lastItem) {
                            if ($item == $lastItem) {
                                return '<li class="breadcrumb-item">
                                        <a href="/' . $aUrl . '">' .
                                    ($item['icon'] == '' ? '' : '<i class="' . $item['icon'] . '"></i> ') . $item['title'] . '
                                        </a>
                                    </li>';
                            } elseif ($item['url'] == '') {
                                return '<li class="breadcrumb-item active">' .
                                    ($item['icon'] == '' ? '' : '<i class="' . $item['icon'] . '"></i> ') . $item['title'] .
                                    '</li>';
                            } else {
                                return '<li class="breadcrumb-item">
                                            <a href="/' . $item['url'] . '">' . ($item['icon'] == '' ? '' : '<i class="' . $item['icon'] . '"></i> ') . $item['title'] .
                                    '</a>
                                        </li>';
                            }
                        }, $array);

                        $htmlArray[] = '<li class="breadcrumb-item active">
                                <i class="fa fa-pencil"></i> Editar
                            </li>';
                        break;
                    case 'create':
                        $htmlArray = array_map(function ($item) use ($aUrl, $lastItem) {
                            if ($item == $lastItem) {
                                return '<li class="breadcrumb-item">
                                            <a href="/' . $aUrl . '">' . ($item['icon'] == '' ? '' : '<i class="' . $item['icon'] . '"></i> ') . $item['title'] .
                                    '</a>
                                        </li>';
                            } elseif ($item['url'] == '') {
                                return '<li class="breadcrumb-item active">' .
                                    ($item['icon'] == '' ? '' : '<i class="' . $item['icon'] . '"></i> ') . $item['title'] .
                                    '</li>';
                            } else {
                                return '<li class="breadcrumb-item">
                                            <a href="/' . $item['url'] . '">' . ($item['icon'] == '' ? '' : '<i class="' . $item['icon'] . '"></i> ') . $item['title'] .
                                    '</a>
                                        </li>';
                            }
                        }, $array);

                        $htmlArray[] = '<li class="breadcrumb-item active">
                                <i class="fa fa-plus-circle"></i> Nuevo
                            </li>';
                        break;
                    default:
                        $htmlArray = array_map(function ($item) use ($aUrl) {
                            if ($item['url'] == '') {
                                return '<li class="breadcrumb-item active">' . ($item['icon'] == '' ? '' : '<i class="' . $item['icon'] . '"></i> ') . $item['title'] . '</li>';
                            } else {
                                return '<li class="breadcrumb-item"><a href="/' . $item['url'] . '">' . ($item['icon'] == '' ? '' : '<i class="' . $item['icon'] . '"></i> ') . $item['title'] . '</a></li>';
                            }
                        }, $array);
                        break;
                }
                $html .= implode('', $htmlArray);

                //Armarlo a partir del array
            }
            $html .= '</ol>';
        }

        return $html;
    }

    public function mostrarBreadcrumb($aBool)
    {
        $this->breadcrumb['mostrar'] = $aBool;
    }

    /*==================== GETTERS =====================================*/
    private function getFieldOrder()
    {
        $orders = $this->fields->where('show', true)->map(function ($field) {
            return $field['field'];
        });

        $orders->push($this->uniqueid);

        return $orders;
    }

    private function getShowFields()
    {
        return $this->fields->where('show', true);
    }

    private function getShowMultipleFields()
    {
        return array_values(array_filter($this->fields, function ($field) {
            return $field['type'] == 'multi';
        }));
    }

    private function getLocalShowFields()
    {
        return $this->fields->filter(function ($field) {
            return (
                $field['show'] == true &&
                $field['type'] != 'multi' &&
                !$field['isforeign']
            );
        });
    }

    private function getLocalEditFields()
    {
        return $this->fields->filter(function ($field) {
            return strpos($field['field'], '.') === false && $field['editable'] == true;
        });
    }

    private function getMultiFields()
    {
        return $this->fields->where('type', 'multi')->pluck('field');
    }

    private function getForeignShowFields()
    {
        $foreigns = $this->fields->where('isforeign', true)->map(function ($field) {
            $parts = explode('.', $field['field']);
            $key   = $parts[0];
            array_shift($parts);
            if (is_array($parts)) {
                $value = implode('.', $parts);
            } else {
                $value = $parts;
            }

            return [$key => $value];
        });

        return $foreigns;

        // $foreigns =
        //     array_filter(
        //     $this->fields,
        //     function ($c) {
        //         return ($c['show'] == true && strpos($c['field'], '.') != 0 && strpos($c['field'], '"') === false);
        //     }
        // );
        // $i = 0;
        // //dd($foreigns);
        // foreach ($foreigns as $foreign) {
        //     if ($foreign['isforeign']) {

        //     }
        // }

        // return $arr;
    }

    private function getCamposEdit()
    {
        return array_values(array_filter($this->fields, function ($c) {
            return $c['editable'] == true;
        }));
    }

    private function getSelect($aFields)
    {
        return $aFields->map(function ($field) {
            return DB::raw($field->field);
        });
    }

    /*==================== SETTERS =====================================*/
    public function setModel(Model $aModelo)
    {
        $this->model = $aModelo;
    }

    public function setLayout($aLayout)
    {
        $this->layout = $aLayout;
    }

    public function setField($aParams)
    {
        $allowed = ['field', 'name', 'editable', 'show', 'type', 'class',
            'default', 'decimals', 'collection',
            'filepath', 'filewidth', 'fileheight', 'filedisk', 'target', 'isforeign', 'utc', 'editClass'];
        $tipos = ['string', 'multi', 'numeric', 'date', 'datetime', 'time', 'bool', 'combobox', 'password',
            'file', 'image', 'textarea', 'url', 'summernote', 'securefile'];

        foreach ($aParams as $key => $val) {
            //Validamos que todas las variables del array son permitidas.
            if (!in_array($key, $allowed)) {
                dd('setField no recibe parametros con el nombre: ' . $key . '! solamente se permiten: ' . implode(', ', $allowed));
            }
        }

        if (!array_key_exists('field', $aParams)) {
            dd('setField must have a value for "field"');
        }

        $nombre     = (!array_key_exists('name', $aParams) ? str_replace('_', ' ', ucfirst($aParams['field'])) : $aParams['name']);
        $edit       = (!array_key_exists('editable', $aParams) ? true : $aParams['editable']);
        $show       = (!array_key_exists('show', $aParams) ? true : $aParams['show']);
        $tipo       = (!array_key_exists('type', $aParams) ? 'string' : $aParams['type']);
        $class      = (!array_key_exists('class', $aParams) ? '' : $aParams['class']);
        $default    = (!array_key_exists('default', $aParams) ? null : $aParams['default']);
        $decimals   = (!array_key_exists('decimals', $aParams) ? 0 : $aParams['decimals']);
        $collection = (!array_key_exists('collection', $aParams) ? '' : $aParams['collection']);
        $filepath   = (!array_key_exists('filepath', $aParams) ? '' : $aParams['filepath']);
        $filewidth  = (!array_key_exists('filewidth', $aParams) ? 80 : $aParams['filewidth']);
        $fileheight = (!array_key_exists('fileheight', $aParams) ? 80 : $aParams['fileheight']);
        $target     = (!array_key_exists('target', $aParams) ? '_blank' : $aParams['target']);
        $isforeign  = (!array_key_exists('isforeign', $aParams) ? false : $aParams['isforeign']);
        $filedisk   = (!array_key_exists('filedisk', $aParams) ? true : $aParams['filedisk']);
        $utc        = (!array_key_exists('utc', $aParams) ? false : $aParams['utc']);
        $editClass  = (!array_key_exists('editClass', $aParams) ? 'col-sm-12' : $aParams['editClass']);
        $searchable = true;

        if (!in_array($tipo, $tipos)) {
            dd('El tipo configurado (' . $tipo . ') no existe! solamente se permiten: ' . implode(', ', $tipos));
        }

        if ($tipo == 'combobox' && ($collection == '')) {
            dd('Para el tipo combobox el collection es requerido');
        }
        if ($tipo == 'combobox') {
            $show = false;
        }

        if ($tipo == 'multi') {
            $methodName = 'fetch' . ucfirst($aParams['field']) . 'Column';
            $keyName    = method_exists($this->model, $methodName) ? $this->model->{$methodName}() : 'name';

            $options = $this->model
                ->{'fetch' . ucfirst($aParams['field'])}();

            $collection = $options;
        }

        if ($tipo == 'file' && $filepath == '') {
            dd('Para el tipo file hay que especifiarle el filepath');
        }
        if ($tipo == 'image' && $filepath == '') {
            dd('Para el tipo image hay que especifiarle el filepath');
        }
        if ($tipo == 'securefile' && $filepath == '') {
            dd('Para el tipo securefile hay que especifiarle el filepath');
        }
        if ($tipo == 'securefile' && $filedisk == '') {
            dd('Para el tipo securefile hay que especifiarle el filedisk');
        }

        if (!strpos($aParams['field'], ')')) {
            $arr = explode('.', $aParams['field']);
            if (count($arr) >= 2) {
                $fieldReal = $arr[count($arr) - 1];
            } else {
                $fieldReal = $aParams['field'];
            }
            $alias = str_replace('.', '__', $aParams['field']);
        } else {
            $fieldReal = $aParams['field'];
            $alias     = 'a' . date('U') . count($this->getShowFields()); //Nos inventamos un alias para los subqueries
        }

        if ($aParams['field'] == $this->model->getKeyName()) {
            $alias = 'idsinenc' . count($this->getShowFields());
            $edit  = false;
        }

        $arr = [
            'name'       => $nombre,
            'field'      => $aParams['field'],
            'alias'      => $alias,
            'campoReal'  => $fieldReal,
            'type'       => $tipo,
            'show'       => $show,
            'editable'   => $edit,
            'default'    => $default,
            'class'      => $class,
            'decimals'   => $decimals,
            'collection' => $collection,
            'searchable' => $searchable,
            'filepath'   => $filepath,
            'filewidth'  => $filewidth,
            'fileheight' => $fileheight,
            'filedisk'   => $filedisk,
            'target'     => $target,
            'isforeign'  => $isforeign,
            'utc'        => $utc,
            'editClass'  => $editClass,
        ];

        if (!$this->fields) {
            $this->fields = collect();
        }

        $this->fields->push(collect($arr));
    }

    public function setJoin($aRelation)
    {
        $this->joins[] = $aRelation;
    }

    public function setLeftJoin($aTabla, $aCol1, $aOperador, $aCol2)
    {
        $this->leftJoins[] = ['tabla' => $aTabla, 'col1' => $aCol1, 'operador' => $aOperador, 'col2' => $aCol2];
    }

    public function setWhere($aColumna, $aOperador, $aValor = null)
    {
        if ($aValor == null) {
            $aValor    = $aOperador;
            $aOperador = '=';
        }

        $this->wheres[] = ['columna' => $aColumna, 'operador' => $aOperador, 'valor' => $aValor];
    }

    public function setWhereIn($aColumna, $aArray)
    {
        $this->wheresIn[] = ['columna' => $aColumna, 'arreglo' => $aArray];
    }

    public function setWhereRaw($aStatement)
    {
        $this->wheresRaw[] = $aStatement;
    }

    public function setTitle($aTitle)
    {
        $this->title = $aTitle;
    }

    public function getTitle()
    {
        return $this->title;
    }

    public function getLayout()
    {
        return $this->layout;
    }

    public function setignoreFields($aField)
    {
        $this->ignoreFields[] = $aField;
    }

    public function setBreadcrumb($aArray)
    {
        $this->breadcrumb['breadcrumb'] = $aArray;
    }

    public function setExtraButton($aParams)
    {
        $allowed = ['url', 'title', 'target', 'icon', 'class', 'confirm', 'confirmmessage'];

        foreach ($aParams as $key => $val) {
            //Validamos que todas las variables del array son permitidas.
            if (!in_array($key, $allowed)) {
                dd('setExtraButton no recibe parametros con el nombre: ' . $key . '! solamente se permiten: ' . implode(', ', $allowed));
            }
        }
        if (!array_key_exists('url', $aParams)) {
            dd('setExtraButton debe tener un valor para "url"');
        }

        $icon           = (!array_key_exists('icon', $aParams) ? 'fa fa-star' : $aParams['icon']);
        $class          = (!array_key_exists('class', $aParams) ? 'default' : $aParams['class']);
        $title          = (!array_key_exists('title', $aParams) ? '' : $aParams['title']);
        $target         = (!array_key_exists('target', $aParams) ? '' : $aParams['target']);
        $confirm        = (!array_key_exists('confirm', $aParams) ? false : $aParams['confirm']);
        $confirmmessage = (!array_key_exists('confirmmessage', $aParams) ? '¿Estas seguro?' : $aParams['confirmmessage']);

        $arr = [
            'url'            => $aParams['url'],
            'title'          => $title,
            'icon'           => $icon,
            'class'          => $class,
            'target'         => $target,
            'confirm'        => $confirm,
            'confirmmessage' => $confirmmessage,
        ];

        $this->extraButtons[] = $arr;
    }

    public function setExtraAction($aParams)
    {
        $allowed = ['url', 'title', 'target', 'class', 'icon'];

        foreach ($aParams as $key => $val) {
            //Validamos que todas las variables del array son permitidas.
            if (!in_array($key, $allowed)) {
                dd('setExtraAction does not allow parameter ' . $key . '! allowed parameters: ' . implode(', ', $allowed));
            }
        }
        if (!array_key_exists('url', $aParams)) {
            dd('setExtraAction must have a "url" value');
        }

        $title  = (!array_key_exists('title', $aParams) ? '' : $aParams['title']);
        $target = (!array_key_exists('target', $aParams) ? '' : $aParams['target']);
        $icon   = (!array_key_exists('icon', $aParams) ? '' : $aParams['icon']);
        $class  = (!array_key_exists('class', $aParams) ? 'btn btn-default' : $aParams['class']);

        $arr = [
            'url'    => $aParams['url'],
            'title'  => $title,
            'target' => $target,
            'class'  => $class,
            'icon'   => $icon,
        ];

        $this->extraActions[] = $arr;
    }

    public function setPermissions($aPermissionsCallback, $aModule = false)
    {
        if (!$aModule) {
            $this->permissions = $aPermissionsCallback;
        } else {
            $this->middleware(function ($request, $next) use ($aPermissionsCallback, $aModule) {
                $this->permissions = call_user_func($aPermissionsCallback, $aModule);

                return $next($request);
            });
        }
    }

    public function setHidden($aParams)
    {
        $allowed = ['field', 'value'];

        foreach ($aParams as $key => $val) {
            //Validamos que todas las variables del array son permitidas.
            if (!in_array($key, $allowed)) {
                dd('setHidden no recibe parametros con el nombre: ' . $key . '! solamente se permiten: ' . implode(', ', $allowed));
            }
        }

        $this->hiddenFields[$aParams['field']] = $aParams['value'];
    }

    public function setPerPage($aCuantos)
    {
        $this->perPage = $aCuantos;
    }

    public function setResponsive($aResponsive)
    {
        $this->responsive = $aResponsive;
    }

    private function getQueryString($request)
    {
        $query = '?' . $request->getQueryString();
        if ($query == '?') {
            $query = '';
        }

        return $query;
    }

    public function setOrderBy($aParams)
    {
        $allowed    = ['column', 'direction'];
        $directions = ['asc', 'desc'];

        foreach ($aParams as $key => $val) {
            if (!in_array($key, $allowed)) {
                dd('setOrderBy does not accept parameter: ' . $key . '! only ' . implode(', ', $allowed) . ' are allowed.');
            }
        }

        $column    = (!array_key_exists('column', $aParams) ? 0 : $aParams['column']);
        $direction = (!array_key_exists('direction', $aParams) ? 'asc' : $aParams['direction']);

        $this->orders[$column] = $direction;
    }
}
