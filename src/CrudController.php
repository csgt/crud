<?php
namespace Csgt\Crud;

use DB;
use Storage;
use Response;
use Exception;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Http\Request;
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
    private $fields       = [];
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

    public function setup(Request $request)
    {
        abort(400, "This method must be overriden in parent");
        //Has to be overridden in parent
    }

    public function index(Request $request)
    {
        $this->setup($request);

        if (!$this->model) {
            abort(400, 'setModel is required.');
        }
        $breadcrumb = $this->generateBreadcrumb('index');

        return view('csgtcrud::index')
            ->with('layout', $this->layout)
            ->with('breadcrumb', $breadcrumb)
            ->with('stateSave', $this->stateSave)
            ->with('showExport', $this->showExport)
            ->with('showSearch', $this->showSearch)
            ->with('responsive', $this->responsive)
            ->with('perPage', $this->perPage)
            ->with('title', $this->title)
            ->with('columns', $this->getShowFields())
            ->with('permisos', $this->permissions)
            ->with('orders', $this->orders)
            ->with('extraButtons', $this->extraButtons)
            ->with('extraActions', $this->extraActions)
            ->with('queryParameters', $this->getQueryString($request));
    }

    public function show(Request $request, $aId)
    {
        $this->setup($request);
        $data = $this->model->find($aId);
        if ($request->expectsJson()) {
            return response()->json($data);
        }
    }

    public function edit(Request $request, $aId)
    {
        $this->setup($request);
        $path = $this->downLevel($request->path()) . '/';
        if ($aId) {
            $data       = $this->model->find($aId);
            $breadcrumb = $this->generateBreadcrumb('edit', $this->downLevel($path));
        } else {
            $data       = null;
            $breadcrumb = $this->generateBreadcrumb('create', $path);
        }

        $editFields      = $this->getLocalEditFields();
        $combos          = $this->fillCombos($editFields);
        $queryParameters = $this->getQueryString($request);

        $uses = [
            'selectize'  => false,
            'summernote' => false,
        ];

        foreach ($editFields as $column) {
            if (($column['type'] == 'combobox') || ($column['type'] == 'enum') || $column['type'] == 'multi') {
                $uses['selectize'] = true;
            }

            if ($column['type'] == 'summernote') {
                $uses['summernote'] = true;
            }
        }

        return view('csgtcrud::edit')
            ->with('pathstore', $path)
            ->with('template', $this->layout)
            ->with('breadcrumb', $breadcrumb)
            ->with('columns', $editFields)
            ->with('data', $data)
            ->with('combos', $combos)
            ->with('queryParameters', $queryParameters)
            ->with('uses', $uses);
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
        // abort(400, json_encode($request->all()));

        $this->setup($request);
        $fields = Arr::except($request->request->all(), $this->ignoreFields);
        $fields = array_merge($fields, $this->hiddenFields);

        $newMulti = [];
        foreach ($this->fields as $campo) {
            if ((($campo['type'] == 'date') || ($campo['type'] == 'datetime')) && $campo['utc']) {
                $fields[$campo['field']] = Carbon::parse($fields[$campo['field']])
                    ->shiftTimezone($request->__tz__)
                    ->setTimezone('UTC');
            }

            if (($campo['type'] == 'file') || ($campo['type'] == 'image')) {
                if ($request->hasFile($campo['field'])) {
                    $file = $request->file($campo['field']);

                    $filename = date('Ymdhis') . mt_rand(1, 1000) . '.' . strtolower($file->getClientOriginalExtension());
                    $path     = public_path() . $campo['filepath'];

                    if (!file_exists($path)) {
                        mkdir($path, 0777, true);
                    }

                    $file->move($path, $filename);
                    $fields[$campo['field']] = $filename;
                    $fields[$campo['field']] = $filename;
                }
            }

            if ($campo['type'] == 'securefile') {
                if ($request->hasFile($campo['field'])) {
                    if ($aId !== 0) {
                        $existing = $this->model->find($aId);
                        if ($existing) {
                            if ($existing->{$campo['field']} != '') {
                                Storage::disk($campo['filedisk'])->delete($existing->{$campo['field']});
                            }
                        }
                    }

                    $filename                = Storage::disk($campo['filedisk'])->putFile($campo['filepath'], $request->file($campo['field']));
                    $fields[$campo['field']] = $filename;
                    $fields[$campo['field']] = $filename;
                }
            }

            if ($campo['type'] == 'multi') {
                if (array_key_exists($campo['field'], $fields)) {
                    $newMulti[$campo['field']] = $fields[$campo['field']];
                } else {
                    $newMulti[$campo['field']] = [];
                }

                $fields = Arr::except($fields, $campo['field']);
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
        $this->setup($request);
        try {
            $this->model->destroy($aId);
            $request->session()->flash('message', trans('csgtcrud::crud.registroeliminado'));
            $request->session()->flash('type', 'warning');
        } catch (Exception $e) {
            $request->session()->flash('message', trans('csgtcrud::crud.registroelimiandoe'));
            $request->session()->flash('type', 'danger');
        }
        if ($request->expectsJson()) {
            return response()->json('ok');
        }

        return redirect($this->downLevel($request->path()));
    }

    public function data(Request $request)
    {
        $this->setup($request);
        //Definimos las variables que nos ayudar'an en el proceso de devolver la data
        $search          = $request->search;
        $orders          = $request->order;
        $multiColumns    = $this->getShowMultipleFields();
        $columns         = $this->getLocalShowFields();
        $fields          = $this->getSelect($columns);
        $recordsFiltered = 0;
        $recordsTotal    = 0;

        //Se obtienen los campos a mostrar desde el modelo
        $data = $this->model->select($fields);

        $foreigns = $this->getForeignShowFields();
        foreach ($foreigns as $relation => $fields) {
            $foreignModel = $this->model->{$relation}();

            $data->with([$relation => function ($query) use ($fields, $foreignModel) {
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

        // foreach ($multiColumns as $multiColumn) {
        //  $data->with($multiColumn['campoReal']);
        // }

        $data->addSelect($this->model->getTable() . '.' . $this->model->getKeyName() . ' AS ' . $this->uniqueid);
        foreach ($this->leftJoins as $leftJoin) {
            $data->leftJoin($leftJoin['tabla'], $leftJoin['col1'], $leftJoin['operador'], $leftJoin['col2']);
        }

        //Filtramos a partir del where
        foreach ($this->wheres as $where) {
            $data->where($where['columna'], $where['operador'], $where['valor']);
        }
        //Filtramos a partir del whereIn
        foreach ($this->wheresIn as $whereIn) {
            $data->whereIn($whereIn['columna'], $whereIn['arreglo']);
        }
        //Filtramos a partir de WhereRaw
        foreach ($this->wheresRaw as $whereRaw) {
            $data->whereRaw($whereRaw);
        }

        $data = $data->get();
        //Obtenemos la cantidad de registros antes de filtrar
        $recordsTotal = $data->count();

        //Filtramos con el campo de la vista
        if ($search['value'] != '') {
            if ($recordsTotal > 0) {
                $data = $data->filter(function ($item) use ($search) {
                    $result = false;
                    foreach ($item->getAttributes() as $column) {
                        $result = $result || stristr(strtoupper($column), strtoupper($search['value']));
                    }
                    $relations = $item->getRelations();
                    foreach ($relations as $relation) {
                        if ($relation && method_exists($relation, 'getAttributes')) {
                            foreach ($relation->getAttributes() as $column) {
                                $result = $result || stristr(strtoupper($column), strtoupper($search['value']));
                            }
                        }
                    }

                    return $result;
                });
            }
        }

        //Obtenemos la cantidad de registros luego de haber filtrado
        $recordsFiltered = $data->count();

        //Ahora order by
        $fieldsOrder = $this->getFieldOrder();
        if ($orders) {
            foreach ($orders as $order) {
                if ($order['dir'] == 'asc') {
                    $data = $data->sortBy($fieldsOrder[$order['column']], SORT_NATURAL | SORT_FLAG_CASE);
                } else {
                    $data = $data->sortByDesc($fieldsOrder[$order['column']], SORT_NATURAL | SORT_FLAG_CASE);
                }
            }
        }

        //Filtramos los registros y obtenemos el arreglo con la data
        $items = $data
            ->splice($request->start)
            ->take($request->length)
            ->toArray();

        $arr = [];
        foreach ($items as $item) {
            $cols     = [];
            $lastItem = '';

            for ($i = 0; $i < sizeof($fieldsOrder); $i++) {
                $colName            = '';
                $relationName       = '';
                $actualOrdencolumns = $fieldsOrder[$i];
                $tienePunto         = (strpos($fieldsOrder[$i], '.') !== false) && (strpos($fieldsOrder[$i], '"') === false);

                $column = collect($this->fields)->first(function ($item, $key) use ($actualOrdencolumns) {
                    return $item['field'] == $actualOrdencolumns;
                });

                $esRelacion = false;

                if ($column) {
                    if (array_key_exists('isforeign', $column)) {
                        $esRelacion = ($tienePunto) && ($column['isforeign'] == true);
                    }
                }

                if ($tienePunto) {
                    $helperString = explode('.', $fieldsOrder[$i]);
                    $colName      = $helperString[1];
                    $relationName = $helperString[0];
                    if (strpos($colName, ' AS ')) {
                        $colName = explode('AS ', $colName)[1];
                    }
                } else {
                    if (strpos($fieldsOrder[$i], ' AS ')) {
                        $colName = explode('AS ', $fieldsOrder[$i])[1];
                    } else {
                        $colName = $fieldsOrder[$i];
                    }
                }

                if ($colName == $this->uniqueid) {
                    $lastItem = $item[$colName];
                } elseif ($esRelacion) {
                    //Se chequea si el restultado de la relaci'on es de uno a uno o de uno a muchos
                    if ($item[$relationName]) {
                        if (array_key_exists(0, $item[$relationName])) {
                            $cols[] = $item[$relationName][0][$colName];
                        } else {
                            if (array_key_exists($colName, $item[$relationName])) {
                                $cols[] = $item[$relationName][$colName];
                            } else {
                                $cols[] = null;
                            }
                        }
                    } else {
                        $cols[] = null;
                    }
                } else {
                    $fullCampo = array_filter($this->fields, function ($campo) use ($colName) {
                        return $campo['field'] == $colName;
                    });
                    if (count($fullCampo) > 0) {
                        $fullCampoFixed = array_values($fullCampo)[0];
                        if ($fullCampoFixed['type'] == 'securefile') {
                            if ($item[$colName] != null) {
                                $cols[] = Storage::disk($fullCampoFixed['filedisk'])->temporaryUrl(
                                    $item[$colName], now()->addMinutes(5)
                                );
                            } else {
                                $cols[] = $item[$colName];
                            }
                        } else if ($fullCampoFixed['type'] == 'multi') {
                            $methodName = 'fetch' . ucfirst($fullCampoFixed['field']) . 'Column';
                            $keyName    = method_exists($this->model, $methodName) ? $this->model->{$methodName}() : 'name';

                            $cols[] = implode(', ',
                                $this->model
                                    ->find($item[$this->uniqueid])
                                    ->{$fullCampoFixed['field']}
                                    ->pluck($keyName)
                                    ->toArray()
                            );
                        } else {
                            $cols[] = $item[$colName];
                        }
                    } else {
                        $cols[] = $item[$colName];
                    }
                }
            }

            $cols['DT_RowId'] = $lastItem;
            $arr[]            = $cols;
        }

        return response()->json(['draw' => $request->draw, 'recordsTotal' => $recordsTotal, 'recordsFiltered' => $recordsFiltered, 'data' => $arr]);
    }

    private function downLevel($aPath)
    {
        $arr = explode('/', $aPath);
        array_pop($arr);
        $route = implode('/', $arr);

        return $route;
    }

    private function fillCombos($aCampos)
    {
        $combos = [];
        foreach ($aCampos as $campo) {
            if ($campo['type'] == 'combobox') {
                $arr = [];
                foreach ($campo['collection']->toArray() as $item) {
                    $arr[current($item)] = next($item);
                }

                $combos[$campo['alias']] = $arr;
            } else if ($campo['type'] == 'multi') {
                $methodName = 'fetch' . ucfirst($campo['field']) . 'Column';
                $keyName    = method_exists($this->model, $methodName) ? $this->model->{$methodName}() : 'name';

                $options = $this->model
                    ->{'fetch' . ucfirst($campo['field'])}()
                    ->mapWithKeys(function ($item) use ($keyName) {
                        return [$item->id => $item->{$keyName}];
                    }
                    );
                $combos[$campo['alias']] = $options;
            }
        }

        return $combos;
    }

    public function getTemplate()
    {
        return $this->layout;
    }

    public function generateBreadcrumb($aTipo, $aUrl = '')
    {
        $html = '';
        if ($this->breadcrumb['mostrar']) {
            $html .= '<ol class="breadcrumb float-sm-right float-sm-end">';
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
        $tempArray = array_filter($this->fields, function ($c) {
            return $c['show'] === true;
        });
        $tempArray = array_map(function ($campo) {
            return $campo['field'];
        }, $tempArray);
        $tempArray[] = $this->uniqueid;

        return array_values($tempArray);
    }

    private function getShowFields()
    {
        return array_values(array_filter($this->fields, function ($c) {
            return ($c['show'] == true);
        }));
    }

    private function getShowMultipleFields()
    {
        return array_values(array_filter($this->fields, function ($campo) {
            return $campo['type'] == 'multi';
        }));
    }

    private function getLocalShowFields()
    {
        return array_values(array_filter(
            $this->fields,
            function ($c) {
                return (
                    $c['show'] == true &&
                    $c['type'] != 'multi' &&
                    (strpos($c['field'], '.') === false ||
                        strpos($c['field'], '"') !== false || !$c['isforeign'])
                );
            }
        ));
    }

    private function getLocalEditFields()
    {
        return array_values(array_filter($this->fields, function ($c) {
            return ($c['editable'] == true && strpos($c['field'], '.') === false);
        }));
    }

    private function getForeignShowFields()
    {
        $arr      = [];
        $foreigns =
            array_filter(
            $this->fields,
            function ($c) {
                return ($c['show'] == true && strpos($c['field'], '.') != 0 && strpos($c['field'], '"') === false);
            }
        );
        $i = 0;
        //dd($foreigns);
        foreach ($foreigns as $foreign) {
            if ($foreign['isforeign']) {
                $partes = explode('.', $foreign['field']);
                $key    = $partes[0];
                array_shift($partes);
                if (is_array($partes)) {
                    $valor = implode('.', $partes);
                } else {
                    $valor = $partes;
                }

                $arr[$key][$i][] = $valor;
                $i++;
            }
        }

        return $arr;
    }

    private function getCamposEdit()
    {
        return array_values(array_filter($this->fields, function ($c) {
            return $c['editable'] == true;
        }));
    }

    private function getSelect($aCampos)
    {
        return array_map(function ($c) {
            return DB::raw($c['field']);
        }, $aCampos);
    }

    /*==================== SETTERS =====================================*/
    public function setModel($aModelo)
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
            'default', 'validationRules', 'validationRulesMessage', 'decimals', 'collection',
            'enumarray', 'filepath', 'filewidth', 'fileheight', 'filedisk', 'target', 'isforeign', 'utc', 'editClass'];
        $tipos = ['string', 'multi', 'numeric', 'date', 'datetime', 'bool', 'combobox', 'password',
            'enum', 'file', 'image', 'textarea', 'url', 'summernote', 'securefile'];

        foreach ($aParams as $key => $val) {
            //Validamos que todas las variables del array son permitidas.
            if (!in_array($key, $allowed)) {
                dd('setField no recibe parametros con el nombre: ' . $key . '! solamente se permiten: ' . implode(', ', $allowed));
            }
        }

        if (!array_key_exists('field', $aParams)) {
            dd('setField must have a value for "campo"');
        }

        $nombre        = (!array_key_exists('name', $aParams) ? str_replace('_', ' ', ucfirst($aParams['field'])) : $aParams['name']);
        $edit          = (!array_key_exists('editable', $aParams) ? true : $aParams['editable']);
        $show          = (!array_key_exists('show', $aParams) ? true : $aParams['show']);
        $tipo          = (!array_key_exists('type', $aParams) ? 'string' : $aParams['type']);
        $class         = (!array_key_exists('class', $aParams) ? '' : $aParams['class']);
        $default       = (!array_key_exists('default', $aParams) ? '' : $aParams['default']);
        $reglas        = (!array_key_exists('validationRules', $aParams) ? [] : $aParams['validationRules']);
        $decimals      = (!array_key_exists('decimals', $aParams) ? 0 : $aParams['decimals']);
        $collection    = (!array_key_exists('collection', $aParams) ? '' : $aParams['collection']);
        $reglasmensaje = (!array_key_exists('validationRulesMessage', $aParams) ? '' : $aParams['validationRulesMessage']);
        $filepath      = (!array_key_exists('filepath', $aParams) ? '' : $aParams['filepath']);
        $filewidth     = (!array_key_exists('filewidth', $aParams) ? 80 : $aParams['filewidth']);
        $fileheight    = (!array_key_exists('fileheight', $aParams) ? 80 : $aParams['fileheight']);
        $target        = (!array_key_exists('target', $aParams) ? '_blank' : $aParams['target']);
        $enumarray     = (!array_key_exists('enumarray', $aParams) ? [] : $aParams['enumarray']);
        $isforeign     = (!array_key_exists('isforeign', $aParams) ? true : $aParams['isforeign']);
        $filedisk      = (!array_key_exists('filedisk', $aParams) ? true : $aParams['filedisk']);
        $utc           = (!array_key_exists('utc', $aParams) ? true : $aParams['utc']);
        $editClass     = (!array_key_exists('editClass', $aParams) ? 'col-sm-12' : $aParams['editClass']);
        $searchable    = true;

        if (!in_array($tipo, $tipos)) {
            dd('El tipo configurado (' . $tipo . ') no existe! solamente se permiten: ' . implode(', ', $tipos));
        }

        if ($tipo == 'combobox' && ($collection == '')) {
            dd('Para el tipo combobox el collection es requerido');
        }
        if ($tipo == 'combobox') {
            $show = false;
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

        if ($tipo == 'emum' && count($enumarray) == 0) {
            dd('Para el tipo enum el enumarray es requerido');
        }

        if (!strpos($aParams['field'], ')')) {
            $arr = explode('.', $aParams['field']);
            if (count($arr) >= 2) {
                $campoReal = $arr[count($arr) - 1];
            } else {
                $campoReal = $aParams['field'];
            }
            $alias = str_replace('.', '__', $aParams['field']);
        } else {
            $campoReal = $aParams['field'];
            $alias     = 'a' . date('U') . count($this->getShowFields()); //Nos inventamos un alias para los subqueries
        }

        if ($aParams['field'] == $this->model->getKeyName()) {
            $alias = 'idsinenc' . count($this->getShowFields());
            $edit  = false;
        }

        $arr = [
            'name'                   => $nombre,
            'field'                  => $aParams['field'],
            'alias'                  => $alias,
            'campoReal'              => $campoReal,
            'type'                   => $tipo,
            'show'                   => $show,
            'editable'               => $edit,
            'default'                => $default,
            'validationRules'        => $reglas,
            'validationRulesMessage' => $reglasmensaje,
            'class'                  => $class,
            'decimals'               => $decimals,
            'collection'             => $collection,
            'searchable'             => $searchable,
            'enumarray'              => $enumarray,
            'filepath'               => $filepath,
            'filewidth'              => $filewidth,
            'fileheight'             => $fileheight,
            'filedisk'               => $filedisk,
            'target'                 => $target,
            'isforeign'              => $isforeign,
            'utc'                    => $utc,
            'editClass'              => $editClass,
        ];
        $this->fields[] = $arr;
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

    public function setignoreFields($aCampo)
    {
        $this->ignoreFields[] = $aCampo;
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
        $allowed = ['url', 'title', 'target'];

        foreach ($aParams as $key => $val) {
            //Validamos que todas las variables del array son permitidas.
            if (!in_array($key, $allowed)) {
                dd('setExtraAction no recibe parametros con el nombre: ' . $key . '! solamente se permiten: ' . implode(', ', $allowed));
            }
        }
        if (!array_key_exists('url', $aParams)) {
            dd('setExtraAction debe tener un valor para "url"');
        }

        $title  = (!array_key_exists('title', $aParams) ? '' : $aParams['title']);
        $target = (!array_key_exists('target', $aParams) ? '' : $aParams['target']);

        $arr = [
            'url'    => $aParams['url'],
            'title'  => $title,
            'target' => $target,
        ];

        $this->extraActions[] = $arr;
    }

    public function setPermissions($permissions)
    {
        $this->permissions = $permissions;
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
