<?php
namespace Csgt\Crud;

use DB;
use Crypt;
use Storage;
use Response;
use Exception;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;

class CrudController extends BaseController
{
    private $uniqueid      = '___id___';
    private $modelo        = null;
    private $showExport    = true;
    private $showSearch    = true;
    private $stateSave     = true;
    private $responsive    = true;
    private $layout        = 'layouts.app';
    private $perPage       = 50;
    private $titulo        = '';
    private $campos        = [];
    private $camposHidden  = [];
    private $permisos      = ['add' => false, 'edit' => false, 'delete' => false];
    private $orders        = [];
    private $botonesExtra  = [];
    private $accionesExtra = [];
    private $joins         = [];
    private $leftJoins     = [];
    private $wheres        = [];
    private $wheresIn      = [];
    private $wheresRaw     = [];
    private $noGuardar     = ['_token'];
    private $breadcrumb    = ['mostrar' => true, 'breadcrumb' => []];

    public function index(Request $request)
    {
        if ($this->modelo === null) {
            abort(500, 'setModelo es requerido.');
        }
        $breadcrumb = $this->generarBreadcrumb('index');
        $nuevasVars = $this->getQueryString($request);
        $columnas   = $this->getCamposShow();
        $path       = $request->path();

        $options = [
            "processing"     => true,
            "serverSide"     => true,
            "searchDelay"    => 500,
            "ajax"           => [
                "url"     => "/" . $path . "/data" . $nuevasVars,
                "headers" => [
                    "X-CSRF-Token" => csrf_token(),
                ],
                "method"  => "POST",
            ],
            "bLengthChange"  => false,
            "sDom"           => '<"row"' . ($this->showSearch ? '<"col-sm-8 float-left"f>' : '') . '<"col-sm-4"<"btn-toolbar float-right"  B <"btn-group btn-group-sm btn-group-agregar">>>>     rt<"float-left"i><"float-right"p>',
            "iDisplayLength" => $this->perPage,
            "oLanguage"      => [
                "sLengthMenu"   => trans('csgtcrud::crud.sLengthMenu'),
                "sZeroRecords"  => trans('csgtcrud::crud.sZeroRecords'),
                "sInfo"         => trans('csgtcrud::crud.sInfo'),
                "sInfoEmpty"    => trans('csgtcrud::crud.sInfoEmpty'),
                "sInfoFiltered" => trans('csgtcrud::crud.sInfoFiltered'),
                "sSearch"       => "",
                "sProcessing"   => trans('csgtcrud::crud.sProcessing'),
                "oPaginate"     => [
                    "sPrevious" => trans('csgtcrud::crud.sPrevious'),
                    "sNext"     => trans('csgtcrud::crud.sNext'),
                    "sFirst"    => trans('csgtcrud::crud.sFirst'),
                    "sLast"     => trans('csgtcrud::crud.sLast'),
                ],
            ],
        ];

        foreach ($this->orders as $col => $order) {
            $options['order'][] = [$col, $order];
        }

        if ($this->showExport) {
            $options['buttons'] = ['copy', 'excel', 'pdf'];
        }

        return view('csgtcrud::index')
            ->with('options', $options)
            ->with('layout', $this->layout)
            ->with('breadcrumb', $breadcrumb)
            ->with('stateSave', $this->stateSave)
            ->with('showExport', $this->showExport)
            ->with('showSearch', $this->showSearch)
            ->with('responsive', $this->responsive)
            ->with('perPage', $this->perPage)
            ->with('titulo', $this->titulo)
            ->with('columnas', $columnas)
            ->with('permisos', $this->permisos)
            ->with('orders', $this->orders)
            ->with('botonesExtra', $this->botonesExtra)
            ->with('accionesExtra', $this->accionesExtra)
            ->with('nuevasVars', $nuevasVars);
    }

    public function show(Request $request, $aId)
    {
        $data = $this->modelo->find(Crypt::decrypt($aId));
        if ($request->expectsJson()) {
            return response()->json($data);
        }
    }

    public function edit(Request $request, $aId)
    {
        $data       = $this->modelo->find(Crypt::decrypt($aId));
        $path       = $this->downLevel($request->path()) . '/';
        $camposEdit = $this->getCamposEditMine();
        $combos     = $this->fillCombos($camposEdit);
        $breadcrumb = $this->generarBreadcrumb('edit', $this->downLevel($path));
        $nuevasVars = $this->getQueryString($request);

        return view('csgtcrud::edit')
            ->with('pathstore', $path)
            ->with('template', $this->layout)
            ->with('breadcrumb', $breadcrumb)
            ->with('columnas', $camposEdit)
            ->with('data', $data)
            ->with('combos', $combos)
            ->with('nuevasVars', $nuevasVars);
    }

    public function create(Request $request)
    {
        $data       = null;
        $path       = $this->downLevel($request->path());
        $camposEdit = $this->getCamposEditMine();
        $combos     = $this->fillCombos($camposEdit);
        $breadcrumb = $this->generarBreadcrumb('create', $path);
        $nuevasVars = $this->getQueryString($request);

        return view('csgtcrud::edit')
            ->with('pathstore', $path)
            ->with('template', $this->layout)
            ->with('breadcrumb', $breadcrumb)
            ->with('columnas', $camposEdit)
            ->with('data', $data)
            ->with('combos', $combos)
            ->with('nuevasVars', $nuevasVars);
    }

    public function store(Request $request)
    {
        return $this->update($request, 0);
    }

    public function update(Request $request, $aId)
    {
        $rules = [];
        foreach ($this->getCamposEditMine() as $columna) {
            foreach ($columna['reglas'] as $regla) {
                $rules[$columna['campoReal']] = $regla;
            }
        }

        \Log::warning($rules);
        \Log::info($request->all());
        if (!empty($rules)) {
            $request->validate($rules);
        }

        $fields = Arr::except($request->request->all(), $this->noGuardar);
        $fields = array_merge($fields, $this->camposHidden);

        $newMulti = [];
        foreach ($this->campos as $campo) {
            if (array_key_exists($campo['campo'], $fields)) {
                if ($campo['tipo'] == 'date' || $campo['tipo'] == 'datetime') {
                    $aFecha    = $fields[$campo['campo']];
                    $fechahora = explode(' ', $fields[$campo['campo']]);

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
                        $fields[$campo['campo']] = $fecha;
                    } catch (Exception $e) {
                        $fields[$campo['campo']] = null;
                    }
                }
            }

            if (($campo['tipo'] == 'file') || ($campo['tipo'] == 'image')) {
                if ($request->hasFile($campo['campo'])) {
                    $file = $request->file($campo['campo']);

                    $filename = date('Ymdhis') . mt_rand(1, 1000) . '.' . strtolower($file->getClientOriginalExtension());
                    $path     = public_path() . $campo['filepath'];

                    if (!file_exists($path)) {
                        mkdir($path, 0777, true);
                    }

                    $file->move($path, $filename);
                    $campos[$campo['campo']] = $filename;
                    $fields[$campo['campo']] = $filename;
                }
            }

            if ($campo['tipo'] == 'securefile') {
                if ($request->hasFile($campo['campo'])) {
                    if ($aId !== 0) {
                        $existing = $this->modelo->find(Crypt::decrypt($aId));
                        if ($existing) {
                            if ($existing->{$campo['campo']} != '') {
                                Storage::disk($campo['filedisk'])->delete($existing->{$campo['campo']});
                            }
                        }
                    }

                    $filename                = Storage::disk($campo['filedisk'])->putFile($campo['filepath'], $request->file($campo['campo']));
                    $campos[$campo['campo']] = $filename;
                    $fields[$campo['campo']] = $filename;
                }
            }

            if ($campo['tipo'] == 'multi') {
                if (array_key_exists($campo['campo'], $fields)) {
                    $newMulti[$campo['campo']] = $fields[$campo['campo']];
                } else {
                    $newMulti[$campo['campo']] = [];
                }

                $fields = Arr::except($fields, $campo['campo']);
            }
        }

        $nuevasVars = $this->getQueryString($request);
        if ($aId === 0) {
            $item = $this->modelo->create($fields);
            foreach ($newMulti as $relationship => $values) {
                $item->{$relationship}()->attach($values);
            }
            if ($request->expectsJson()) {
                return response()->json([
                    'data'     => $item,
                    'redirect' => "/" . $request->path() . $nuevasVars,
                ]);
            }

            return redirect()->to("/" . $request->path() . $nuevasVars);
        } else {
            $m = $this->modelo->find(Crypt::decrypt($aId));
            $m->update($fields);
            foreach ($newMulti as $relationship => $values) {
                $m->{$relationship}()->detach();
                $m->{$relationship}()->attach($values);
            }
            if ($request->expectsJson()) {
                return response()->json([
                    'data'     => $m,
                    'redirect' => "/" . $this->downLevel($request->path()) . $nuevasVars,
                ]);
            }

            return redirect()->to("/" . $this->downLevel($request->path()) . $nuevasVars);
        }
    }

    public function destroy(Request $request, $aId)
    {
        try {
            $this->modelo->destroy(Crypt::decrypt($aId));
            $request->session()->flash('message', trans('csgtcrud::crud.registroeliminado'));
            $request->session()->flash('type', 'warning');
        } catch (Exception $e) {
            \Log::error($e);
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
        //Definimos las variables que nos ayudar'an en el proceso de devolver la data
        $search          = $request->search;
        $orders          = $request->order;
        $multiColumns    = $this->getCamposShowMulti();
        $columns         = $this->getCamposShowMine();
        $campos          = $this->getSelect($columns);
        $recordsFiltered = 0;
        $recordsTotal    = 0;

        //Se obtienen los campos a mostrar desde el modelo
        $data = $this->modelo->select($campos);

        $foreigns = $this->getCamposShowForeign();
        foreach ($foreigns as $relation => $fields) {
            $foreignModel = $this->modelo->{$relation}();

            $data->with([$relation => function ($query) use ($fields, $foreignModel) {
                $query->addSelect($foreignModel->getForeignKeyName());
                foreach ($fields as $field) {
                    foreach ($field as $fiel) {
                        $query->addSelect(DB::raw($fiel));
                    }
                }
            }]);

            foreach ($fields as $field) {
                $data->addSelect($foreignModel->getQualifiedParentKeyName());
            }
        }

        // foreach ($multiColumns as $multiColumn) {
        //  $data->with($multiColumn['campoReal']);
        // }

        $data->addSelect($this->modelo->getTable() . '.' . $this->modelo->getKeyName() . ' AS ' . $this->uniqueid);
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
                        if (method_exists($relation, 'getAttributes')) {
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
        $ordenColumnas = $this->getCamposOrden();
        if ($orders) {
            foreach ($orders as $order) {
                if ($order['dir'] == 'asc') {
                    $data = $data->sortBy($ordenColumnas[$order['column']], SORT_NATURAL | SORT_FLAG_CASE);
                } else {
                    $data = $data->sortByDesc($ordenColumnas[$order['column']], SORT_NATURAL | SORT_FLAG_CASE);
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

            for ($i = 0; $i < sizeof($ordenColumnas); $i++) {
                $colName             = '';
                $relationName        = '';
                $actualOrdenColumnas = $ordenColumnas[$i];
                $tienePunto          = (strpos($ordenColumnas[$i], '.') !== false) && (strpos($ordenColumnas[$i], '"') === false);

                $column = collect($this->campos)->first(function ($item, $key) use ($actualOrdenColumnas) {
                    return $item['campo'] == $actualOrdenColumnas;
                });

                $esRelacion = false;

                if ($column) {
                    if (array_key_exists('isforeign', $column)) {
                        $esRelacion = ($tienePunto) && ($column['isforeign'] == true);
                    }
                }

                if ($tienePunto) {
                    $helperString = explode('.', $ordenColumnas[$i]);
                    $colName      = $helperString[1];
                    $relationName = $helperString[0];
                    if (strpos($colName, ' AS ')) {
                        $colName = explode('AS ', $colName)[1];
                    }
                } else {
                    if (strpos($ordenColumnas[$i], ' AS ')) {
                        $colName = explode('AS ', $ordenColumnas[$i])[1];
                    } else {
                        $colName = $ordenColumnas[$i];
                    }
                }

                if ($colName == $this->uniqueid) {
                    $lastItem = Crypt::encrypt($item[$colName]);
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
                    $fullCampo = array_filter($this->campos, function ($campo) use ($colName) {
                        return $campo['campo'] == $colName;
                    });
                    if (count($fullCampo) > 0) {
                        $fullCampoFixed = array_values($fullCampo)[0];
                        if ($fullCampoFixed['tipo'] == 'securefile') {
                            if ($item[$colName] != null) {
                                $cols[] = Storage::disk($fullCampoFixed['filedisk'])->temporaryUrl(
                                    $item[$colName], now()->addMinutes(5)
                                );
                            } else {
                                $cols[] = $item[$colName];
                            }
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
            if ($campo['tipo'] == 'combobox') {
                $arr = [];
                foreach ($campo['collection']->toArray() as $item) {
                    $arr[current($item)] = next($item);
                }

                $combos[$campo['alias']] = $arr;
            } else if ($campo['tipo'] == 'multi') {
                $options = $this->modelo
                    ->{'fetch' . ucfirst($campo['campo'])}()
                    ->mapWithKeys(function ($item) {
                        return [$item->id => $item->name];
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

    public function generarBreadcrumb($aTipo, $aUrl = '')
    {
        $html = '';
        if ($this->breadcrumb['mostrar']) {
            $html .= '<ol class="breadcrumb float-sm-right">';
            if (empty($this->breadcrumb['breadcrumb'])) {
                switch ($aTipo) {
                    case 'edit':
                        $html .= '<li class="breadcrumb-item"><a href="/' . $aUrl . '">' . $this->titulo . '</a></li><li class="active"><i class="fa fa-pencil"></i> Editar</li>';
                        break;
                    case 'create':
                        $html .= '<li class="breadcrumb-item"><a href="/' . $aUrl . '">' . $this->titulo . '</a></li><li class="active"><i class="fa fa-plus-circle"></i> Nuevo</li>';
                        break;
                    default:
                        $html .= '<li class="breadcrumb-item active">' . $this->titulo . '</li>';
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
                                return '<li class="breadcrumb-item"><a href="/' . $aUrl . '">' . ($item['icon'] == '' ? '' : '<i class="' . $item['icon'] . '"></i> ') . $item['title'] . '</a></li>';
                            } elseif ($item['url'] == '') {
                                return '<li class="breadcrumb-item active">' . ($item['icon'] == '' ? '' : '<i class="' . $item['icon'] . '"></i> ') . $item['title'] . '</li>';
                            } else {
                                return '<li class="breadcrumb-item"><a href="/' . $item['url'] . '">' . ($item['icon'] == '' ? '' : '<i class="' . $item['icon'] . '"></i> ') . $item['title'] . '</a></li>';
                            }
                        }, $array);

                        $htmlArray[] = '<li class="breadcrumb-item active"><i class="fa fa-pencil"></i> Editar</li>';
                        break;
                    case 'create':
                        $htmlArray = array_map(function ($item) use ($aUrl, $lastItem) {
                            if ($item == $lastItem) {
                                return '<li class="breadcrumb-item"><a href="/' . $aUrl . '">' . ($item['icon'] == '' ? '' : '<i class="' . $item['icon'] . '"></i> ') . $item['title'] . '</a></li>';
                            } elseif ($item['url'] == '') {
                                return '<li class="breadcrumb-item active">' . ($item['icon'] == '' ? '' : '<i class="' . $item['icon'] . '"></i> ') . $item['title'] . '</li>';
                            } else {
                                return '<li class="breadcrumb-item"><a href="/' . $item['url'] . '">' . ($item['icon'] == '' ? '' : '<i class="' . $item['icon'] . '"></i> ') . $item['title'] . '</a></li>';
                            }
                        }, $array);

                        $htmlArray[] = '<li class="breadcrumb-item active"><i class="fa fa-plus-circle"></i> Nuevo</li>';
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
    private function getCamposOrden()
    {
        $tempArray = array_filter($this->campos, function ($c) {
            return $c['show'] === true;
        });
        $tempArray = array_map(function ($campo) {
            return $campo['campo'];
        }, $tempArray);
        $tempArray[] = $this->uniqueid;

        return array_values($tempArray);
    }

    private function getCamposShow()
    {
        return array_values(array_filter($this->campos, function ($c) {
            return ($c['show'] == true);
        }));
    }

    private function getCamposShowMulti()
    {
        return array_values(array_filter($this->campos, function ($campo) {
            return $campo['tipo'] == 'multi';
        }));
    }

    private function getCamposShowMine()
    {
        return array_values(array_filter(
            $this->campos,
            function ($c) {
                return (
                    $c['show'] == true &&
                    $c['tipo'] != 'multi' &&
                    (strpos($c['campo'], '.') === false ||
                        strpos($c['campo'], '"') !== false || !$c['isforeign'])
                );
            }
        ));
    }

    private function getCamposEditMine()
    {
        return array_values(array_filter($this->campos, function ($c) {
            return ($c['editable'] == true && strpos($c['campo'], '.') === false);
        }));
    }

    private function getCamposShowForeign()
    {
        $arr      = [];
        $foreigns =
            array_filter(
            $this->campos,
            function ($c) {
                return ($c['show'] == true && strpos($c['campo'], '.') != 0 && strpos($c['campo'], '"') === false);
            }
        );
        $i = 0;
        //dd($foreigns);
        foreach ($foreigns as $foreign) {
            if ($foreign['isforeign']) {
                $partes = explode('.', $foreign['campo']);
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
        return array_values(array_filter($this->campos, function ($c) {
            return $c['editable'] == true;
        }));
    }

    private function getSelect($aCampos)
    {
        return array_map(function ($c) {
            return DB::raw($c['campo']);
        }, $aCampos);
    }

    /*==================== SETTERS =====================================*/
    public function setModelo($aModelo)
    {
        $this->modelo = $aModelo;
    }

    public function setLayout($aLayout)
    {
        $this->layout = $aLayout;
    }

    public function setCampo($aParams)
    {
        $allowed = ['campo', 'nombre', 'editable', 'show', 'tipo', 'class',
            'default', 'reglas', 'reglasmensaje', 'decimales', 'collection',
            'enumarray', 'filepath', 'filewidth', 'fileheight', 'filedisk', 'target', 'isforeign'];
        $tipos = ['string', 'multi', 'numeric', 'date', 'datetime', 'time', 'bool', 'combobox', 'password', 'enum', 'file', 'image', 'textarea', 'url', 'summernote', 'securefile'];

        foreach ($aParams as $key => $val) {
            //Validamos que todas las variables del array son permitidas.
            if (!in_array($key, $allowed)) {
                dd('setCampo no recibe parametros con el nombre: ' . $key . '! solamente se permiten: ' . implode(', ', $allowed));
            }
        }

        if (!array_key_exists('campo', $aParams)) {
            dd('setCampo debe tener un valor para "campo"');
        }

        $nombre        = (!array_key_exists('nombre', $aParams) ? str_replace('_', ' ', ucfirst($aParams['campo'])) : $aParams['nombre']);
        $edit          = (!array_key_exists('editable', $aParams) ? true : $aParams['editable']);
        $show          = (!array_key_exists('show', $aParams) ? true : $aParams['show']);
        $tipo          = (!array_key_exists('tipo', $aParams) ? 'string' : $aParams['tipo']);
        $class         = (!array_key_exists('class', $aParams) ? '' : $aParams['class']);
        $default       = (!array_key_exists('default', $aParams) ? '' : $aParams['default']);
        $reglas        = (!array_key_exists('reglas', $aParams) ? [] : $aParams['reglas']);
        $decimales     = (!array_key_exists('decimales', $aParams) ? 0 : $aParams['decimales']);
        $collection    = (!array_key_exists('collection', $aParams) ? '' : $aParams['collection']);
        $reglasmensaje = (!array_key_exists('reglasmensaje', $aParams) ? '' : $aParams['reglasmensaje']);
        $filepath      = (!array_key_exists('filepath', $aParams) ? '' : $aParams['filepath']);
        $filewidth     = (!array_key_exists('filewidth', $aParams) ? 80 : $aParams['filewidth']);
        $fileheight    = (!array_key_exists('fileheight', $aParams) ? 80 : $aParams['fileheight']);
        $target        = (!array_key_exists('target', $aParams) ? '_blank' : $aParams['target']);
        $enumarray     = (!array_key_exists('enumarray', $aParams) ? [] : $aParams['enumarray']);
        $isforeign     = (!array_key_exists('isforeign', $aParams) ? true : $aParams['isforeign']);
        $filedisk      = (!array_key_exists('filedisk', $aParams) ? true : $aParams['filedisk']);
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

        if (!strpos($aParams['campo'], ')')) {
            $arr = explode('.', $aParams['campo']);
            if (count($arr) >= 2) {
                $campoReal = $arr[count($arr) - 1];
            } else {
                $campoReal = $aParams['campo'];
            }
            $alias = str_replace('.', '__', $aParams['campo']);
        } else {
            $campoReal = $aParams['campo'];
            $alias     = 'a' . date('U') . count($this->getCamposShow()); //Nos inventamos un alias para los subqueries
        }

        if ($aParams['campo'] == $this->modelo->getKeyName()) {
            $alias = 'idsinenc' . count($this->getCamposShow());
            $edit  = false;
        }

        $arr = [
            'nombre'        => $nombre,
            'campo'         => $aParams['campo'],
            'alias'         => $alias,
            'campoReal'     => $campoReal,
            'tipo'          => $tipo,
            'show'          => $show,
            'editable'      => $edit,
            'default'       => $default,
            'reglas'        => $reglas,
            'reglasmensaje' => $reglasmensaje,
            'class'         => $class,
            'decimales'     => $decimales,
            'collection'    => $collection,
            'searchable'    => $searchable,
            'enumarray'     => $enumarray,
            'filepath'      => $filepath,
            'filewidth'     => $filewidth,
            'fileheight'    => $fileheight,
            'filedisk'      => $filedisk,
            'target'        => $target,
            'isforeign'     => $isforeign,
        ];
        $this->campos[] = $arr;
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

    public function setTitulo($aTitulo)
    {
        $this->titulo = $aTitulo;
    }

    public function getTitulo()
    {
        return $this->titulo;
    }

    public function setNoGuardar($aCampo)
    {
        $this->noGuardar[] = $aCampo;
    }

    public function setBreadcrumb($aArray)
    {
        $this->breadcrumb['breadcrumb'] = $aArray;
    }

    public function setBotonExtra($aParams)
    {
        $allowed = ['url', 'titulo', 'target', 'icon', 'class', 'confirm', 'confirmmessage'];

        foreach ($aParams as $key => $val) {
            //Validamos que todas las variables del array son permitidas.
            if (!in_array($key, $allowed)) {
                dd('setBotonExtra no recibe parametros con el nombre: ' . $key . '! solamente se permiten: ' . implode(', ', $allowed));
            }
        }
        if (!array_key_exists('url', $aParams)) {
            dd('setBotonExtra debe tener un valor para "url"');
        }

        $icon           = (!array_key_exists('icon', $aParams) ? 'glyphicon glyphicon-star' : $aParams['icon']);
        $class          = (!array_key_exists('class', $aParams) ? 'default' : $aParams['class']);
        $titulo         = (!array_key_exists('titulo', $aParams) ? '' : $aParams['titulo']);
        $target         = (!array_key_exists('target', $aParams) ? '' : $aParams['target']);
        $confirm        = (!array_key_exists('confirm', $aParams) ? false : $aParams['confirm']);
        $confirmmessage = (!array_key_exists('confirmmessage', $aParams) ? '¿Estas seguro?' : $aParams['confirmmessage']);

        $arr = [
            'url'            => $aParams['url'],
            'titulo'         => $titulo,
            'icon'           => $icon,
            'class'          => $class,
            'target'         => $target,
            'confirm'        => $confirm,
            'confirmmessage' => $confirmmessage,
        ];

        $this->botonesExtra[] = $arr;
    }

    public function setAccionExtra($aParams)
    {
        $allowed = ['url', 'titulo', 'target'];

        foreach ($aParams as $key => $val) {
            //Validamos que todas las variables del array son permitidas.
            if (!in_array($key, $allowed)) {
                dd('setAccionExtra no recibe parametros con el nombre: ' . $key . '! solamente se permiten: ' . implode(', ', $allowed));
            }
        }
        if (!array_key_exists('url', $aParams)) {
            dd('setAccionExtra debe tener un valor para "url"');
        }

        $titulo = (!array_key_exists('titulo', $aParams) ? '' : $aParams['titulo']);
        $target = (!array_key_exists('target', $aParams) ? '' : $aParams['target']);

        $arr = [
            'url'    => $aParams['url'],
            'titulo' => $titulo,
            'target' => $target,
        ];

        $this->accionesExtra[] = $arr;
    }

    public function setPermisos($aFuncionPermisos, $aModulo = false)
    {
        if (!$aModulo) {
            $this->permisos = $aFuncionPermisos;
        } else {
            $this->middleware(function ($request, $next) use ($aFuncionPermisos, $aModulo) {
                $this->permisos = call_user_func($aFuncionPermisos, $aModulo);

                return $next($request);
            });
        }
    }

    public function setHidden($aParams)
    {
        $allowed = ['campo', 'valor'];

        foreach ($aParams as $key => $val) {
            //Validamos que todas las variables del array son permitidas.
            if (!in_array($key, $allowed)) {
                dd('setHidden no recibe parametros con el nombre: ' . $key . '! solamente se permiten: ' . implode(', ', $allowed));
            }
        }

        $this->camposHidden[$aParams['campo']] = $aParams['valor'];
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
        $allowed     = ['columna', 'direccion'];
        $direcciones = ['asc', 'desc'];

        foreach ($aParams as $key => $val) {
            //Validamos que todas las variables del array son permitidas.
            if (!in_array($key, $allowed)) {
                dd('setOrderBy no recibe parametros con el nombre: ' . $key . '! solamente se permiten: ' . implode(', ', $allowed));
            }
        }

        $columna   = (!array_key_exists('columna', $aParams) ? 0 : $aParams['columna']);
        $direccion = (!array_key_exists('direccion', $aParams) ? 'asc' : $aParams['direccion']);

        $this->orders[$columna] = $direccion;
    }
}
