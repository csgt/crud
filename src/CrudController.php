<?php 
namespace Csgt\Crud;

use Illuminate\Routing\Controller as BaseController;;
use Response,Crypt, Session;
use Illuminate\Http\Request;

class CrudController extends BaseController {
	private $uniqueid     = '___id___';
	private $modelo 			= null;
	private $showExport   = true;
	private $showSearch   = true;
	private $stateSave    = true;
	private $layout       = 'layouts.app';
	private $perPage      = 50;
	private $titulo       = '';
	private $campos       = [];
	private $camposHidden = [];
	private $permisos     = ['add'=>true,'edit'=>true,'delete'=>true];
	private $orders       = [];
	private $botonesExtra = [];
	private $joins        = [];
	private $leftJoins    = [];
	private $wheres       = [];
	private $wheresRaw    = [];
	private $noGuardar    = ['_token'];
	private $breadcrumb   = ['mostrar'=>true, 'breadcrumb'=>[]];


	public function index(Request $request) {
		if($this->modelo === null) dd('setModelo es requerido.');
		$breadcrumb = $this->generarBreadcrumb('index');

		return view('csgtcrud::index')
			->with('layout',       $this->layout)
			->with('breadcrumb',   $breadcrumb)
			->with('stateSave',    $this->stateSave)
			->with('showExport', 	 $this->showExport)
			->with('showSearch', 	 $this->showSearch)
			->with('perPage', 		 $this->perPage)
			->with('titulo', 			 $this->titulo)
			->with('columnas', 		 $this->getCamposShow())
			->with('permisos', 		 $this->permisos)
			->with('orders', 			 $this->orders)
			->with('botonesExtra', $this->botonesExtra)
			->with('nuevasVars',   $this->getQueryString($request));
	}

	public function edit(Request $request, $aId) {
		$data       = $this->modelo->find(Crypt::decrypt($aId));
		$path       = $this->downLevel($request->path()) . '/';
		$camposEdit = $this->getCamposEditMine();
		$combos     = $this->fillCombos($camposEdit);
		$breadcrumb = $this->generarBreadcrumb('edit', $this->downLevel($path));
		$nuevasVars = $this->getQueryString($request);

		return view('csgtcrud::edit')
			->with('pathstore', $path)
			->with('template',   $this->layout)
			->with('breadcrumb',  $breadcrumb)
			->with('columnas',   $camposEdit)
			->with('data',       $data)
			->with('combos',     $combos)
			->with('nuevasVars', $nuevasVars);
	}

	public function create(Request $request) {
		$data       = null;
		$path       = $this->downLevel($request->path());
		$camposEdit = $this->getCamposEditMine();
		$combos     = $this->fillCombos($camposEdit);
		$breadcrumb = $this->generarBreadcrumb('create', $path);
		$nuevasVars = $this->getQueryString($request);

		return view('csgtcrud::edit')
			->with('pathstore', $path)
			->with('template',   $this->layout)
			->with('breadcrumb',  $breadcrumb)
			->with('columnas',   $camposEdit)
			->with('data',       $data)
			->with('combos',     $combos)
			->with('nuevasVars', $nuevasVars);
	}

	public function store(Request $request){
		//Aqui falta procesar fechas, files, images, etc
		$campos = array_except($request->request->all(), $this->noGuardar);
		$campos = array_merge($campos, $this->camposHidden);
		$nuevasVars = $this->getQueryString($request);

		$this->modelo->create($campos);
		return redirect()->to($request->path() . $nuevasVars);
	}

	public function update(Request $request, $aId){
		$campos = array_except($request->request->all(), $this->noGuardar);
		$campos = array_merge($campos, $this->camposHidden);
		$nuevasVars = $this->getQueryString($request);

		$m = $this->modelo->find(Crypt::decrypt($aId));
		$m->update($campos);
		return redirect()->to($this->downLevel($request->path()) . $nuevasVars);
	}

	public function destroy(Request $request, $aId) {
		try {
			$this->modelo->destroy(Crypt::decrypt($aId));
			Session::flash('message', trans('csgtcrud::crud.registroeliminado'));
			Session::flash('type', 'warning');
		} 
		catch (\Exception $e) {
			Session::flash('message', trans('csgtcrud::crud.registroelimiandoe'));
			Session::flash('type', 'danger');
		}
		return redirect($this->downLevel($request->path()));
	}

	private function downLevel($aPath) {
		$arr = explode('/', $aPath);
		array_pop($arr);
		$route = implode('/', $arr);
		return $route;
	}

	private function fillCombos($aCampos){
		$combos = [];
		foreach($aCampos as $campo){
			if($campo['tipo'] == 'combobox'){
				$arr = [];
				foreach($campo['collection']->toArray() as $item){
					$arr[current($item)] = next($item); 
				}

				$combos[$campo['alias']] = $arr;
			}
		}
		return $combos;
	}

	private function getCamposOrden(){
		$tempArray = array_filter($this->campos, function($c) {
			return $c['show'] === true;
		});
		$tempArray = array_map(function($campo){
			return $campo['campo'];
		}, $tempArray);
		$tempArray[] = $this->uniqueid;
		return array_values($tempArray);
	}

	private function getCamposShow(){
		return array_values(array_filter($this->campos, function($c){ return ($c['show'] == true); }));
	}

	private function getCamposShowMine(){
		return array_values(array_filter($this->campos, function($c){ return ($c['show'] == true && strpos($c['campo'],'.') === false); }));
	}

	private function getCamposEditMine(){
		return array_values(array_filter($this->campos, function($c){ return ($c['editable'] == true && strpos($c['campo'],'.') === false); }));
	}

	private function getCamposShowForeign(){
		$arr = [];
		$foreigns = 
		array_filter($this->campos, 
			function($c){ 
				return ($c['show'] == true && strpos($c['campo'],'.') != 0); 
			}
		);
		$i=0;
		//dd($foreigns);
		foreach ($foreigns as $foreign) {
			$partes = explode('.', $foreign['campo']);
			$arr[$partes[0]][$i][] = $partes[1];
			$i++;
		}
		return $arr;
	}

	private function getCamposEdit(){
		return array_values(array_filter($this->campos, function($c){ return $c['editable'] == true; }));
	}

	private function getSelect($aCampos){
		return array_map(function($c){ return $c['campo']; }, $aCampos);
	}

	private function generarBreadcrumb($aTipo, $aUrl='') {
		$html = '';
		if ($this->breadcrumb['mostrar']) {
			$html .= '<ol class="breadcrumb">';
			if (empty($this->breadcrumb['breadcrumb'])) {
				switch ($aTipo) {
					case 'edit':
						$html .= '<li><a href="' . $aUrl . '">' . $this->titulo . '</a></li><li class="active"><i class="fa fa-pencil"></i> Editar</li>';
						break;
					case 'create':
						$html .= '<li><a href="' . $aUrl . '">' . $this->titulo . '</a></li><li class="active"><i class="fa fa-plus-circle"></i> Nuevo</li>';
						break;
					default:
						$html .= '<li class="active">' . $this->titulo . '</li>';
						break;
				}
			}
			else {
				$array = $this->breadcrumb['breadcrumb'];
				$htmlArray = [];
				$lastItem = end($array);
				switch ($aTipo) {
					case 'edit':
						$htmlArray = array_map(function($item) use ($aUrl, $lastItem){
							if($item == $lastItem){
								return '<li><a href="' . $aUrl . '">' . ($item['icon'] == ''?'':'<i class="' . $item['icon'] . '"></i> ') . $item['title'] . '</a></li>';
							}
							else if($item['url'] == ''){
								return '<li class="active">' . ($item['icon'] == ''?'':'<i class="' . $item['icon'] . '"></i> ') . $item['title'] . '</li>';
							}else{
								return '<li><a href="' . $item['url'] . '">' . ($item['icon'] == ''?'':'<i class="' . $item['icon'] . '"></i> ') . $item['title'] . '</a></li>';
							}
						}, $array);

						$htmlArray[] = '<li class="active"><i class="fa fa-pencil"></i> Editar</li>';
						break;
					case 'create':
						$htmlArray = array_map(function($item) use ($aUrl, $lastItem){
							if($item == $lastItem){
								return '<li><a href="' . $aUrl . '">' . ($item['icon'] == ''?'':'<i class="' . $item['icon'] . '"></i> ') . $item['title'] . '</a></li>';
							}
							else if($item['url'] == ''){
								return '<li class="active">' . ($item['icon'] == ''?'':'<i class="' . $item['icon'] . '"></i> ') . $item['title'] . '</li>';
							}else{
								return '<li><a href="' . $item['url'] . '">' . ($item['icon'] == ''?'':'<i class="' . $item['icon'] . '"></i> ') . $item['title'] . '</a></li>';
							}
						}, $array);

						$htmlArray[] = '<li class="active"><i class="fa fa-plus-circle"></i> Nuevo</li>';
						break;
					default:
						$htmlArray = array_map(function($item) use ($aUrl){
							if($item['url'] == ''){
								return '<li class="active">' . ($item['icon'] == ''?'':'<i class="' . $item['icon'] . '"></i> ') . $item['title'] . '</li>';
							}else{
								return '<li><a href="' . $item['url'] . '">' . ($item['icon'] == ''?'':'<i class="' . $item['icon'] . '"></i> ') . $item['title'] . '</a></li>';
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

	public function data(Request $request){
		//Definimos las variables que nos ayudar'an en el proceso de devolver la data
		$search          = $request->search;
		$orders           = $request->order;
		$columns         = $this->getCamposShowMine();
		$campos          = $this->getSelect($columns);
		$recordsFiltered = 0;
		$recordsTotal    = 0;

		//Se obtienen los campos a mostrar desde el modelo
		$data = $this->modelo->select($campos);

		$foreigns = $this->getCamposShowForeign();  

		foreach($foreigns as $relation => $fields) {
			$foreignModel = $this->modelo->{$relation}();

			$data->with([$relation=>function($query) use ($fields, $foreignModel) {
				$query->addSelect($foreignModel->getPlainForeignKey());
				foreach($fields as $field) 
					$query->addSelect($field);
			 }]);

			foreach($fields as $field) {
				$data->addSelect($foreignModel->getQualifiedParentKeyName());
			}
		}
		$data->addSelect($this->modelo->getKeyName() . ' AS ' . $this->uniqueid);
		foreach($this->leftJoins as $leftJoin){
			$data->leftJoin($leftJoin['tabla'], $leftJoin['col1'], $leftJoin['operador'], $leftJoin['col2']);
		}
		//Filtramos a partir del where
		foreach($this->wheres as $where){
			$data->where($where['columna'], $where['operador'], $where['valor']);
		}
		//Filtramos a partir de WhereRaw
		foreach($this->wheresRaw as $whereRaw){
			$data->whereRaw($whereRaw);
		}
		//Obtenemos la cantidad de registros antes de filtrar
		$recordsTotal = $data->count();
		//Filtramos con el campo de la vista
		$data->where(function($q) use ($columns, $search){
			if ($columns) {
				foreach ($columns as $column) {
					if($column['searchable']){
						//$select = explode(' AS ', $selects[$i]);
						$q->orWhere($column['campo'], 'like', '%'.$search['value'].'%');
					}
				}
			}
		});

		if ($orders) {
			foreach($orders as $order){
				$orderArray = explode(' AS ', $selects[$order['column']]);
				$data->orderBy(reset($orderArray), $order['dir']);
			}
		}

		//Obtenemos la cantidad de registros luego de haber filtrado
		$recordsFiltered = $data->count();
		//Filtramos los registros y obtenemos el arreglo con la data
		$items = $data
			->skip($request->start)
			->take($request->length)
			->get()->toArray();

		$arr = [];
		$ordenColumnas = $this->getCamposOrden();
		foreach($items as $item) {
			$cols = [];
			$lastItem = '';
			for ($i = 0; $i < sizeof($ordenColumnas); $i++){
				$colName = '';
				$relationName = '';
				$esRelacion = (strpos($ordenColumnas[$i], '.') !== false);

				if($esRelacion){
					$helperString = explode('.', $ordenColumnas[$i]);
					$colName = $helperString[1];
					$relationName = $helperString[0];
				}
				else{
					$colName = $ordenColumnas[$i];
				}
				
				if ($colName == $this->uniqueid) $lastItem = Crypt::encrypt($item[$colName]);
				else if($esRelacion){
					//Se chequea si el restultado de la relaci'on es de uno a uno o de uno a muchos
					if(array_key_exists(0, $item[$relationName]))
						$cols[] = $item[$relationName][0][$colName];
					else
						$cols[] = $item[$relationName][$colName];
				}
				else $cols[] = $item[$colName];
			}

			$cols[] = $lastItem;
			$arr[] = $cols;
		}
		return response()->json(['draw' => $request->draw, 'recordsTotal' => $recordsTotal, 'recordsFiltered' => $recordsFiltered, 'data' => $arr]);
	}

	public function setModelo($aModelo){
		$this->modelo = $aModelo;
	}

	public function setLayout($aLayout){
		$this->layout = $aLayout;
	}

	public function setCampo($aParams) {
		$allowed = ['campo','nombre','editable','show','tipo','class',
			'default','reglas', 'reglasmensaje', 'decimales','collection',
			'enumarray','filepath','filewidth','fileheight','target'];
		$tipos   = ['string','numeric','date','datetime','bool','combobox','password','enum','file','image','textarea','url','summernote','securefile'];
		
		foreach ($aParams as $key=>$val) { //Validamos que todas las variables del array son permitidas.
			if (!in_array($key, $allowed)) {
				dd('setCampo no recibe parametros con el nombre: ' . $key . '! solamente se permiten: ' . implode(', ', $allowed));
			}
		}


		if(!array_key_exists('campo', $aParams)) dd('setCampo debe tener un valor para "campo"');

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
		$searchable    = true;

		if (!in_array($tipo, $tipos)) dd('El tipo configurado (' . $tipo . ') no existe! solamente se permiten: ' . implode(', ', $tipos));

		if($tipo == 'combobox' && ($collection == '')) dd('Para el tipo combobox el collection es requerido');
		if($tipo == 'combobox') $show = false;
		if($tipo == 'file' && $filepath == '') dd('Para el tipo file hay que especifiarle el filepath');
		if($tipo == 'image' && $filepath == '') dd('Para el tipo image hay que especifiarle el filepath');
		if($tipo == 'securefile' && $filepath == '') dd('Para el tipo securefile hay que especifiarle el filepath');

		if($tipo == 'emum' && count($enumarray) == 0) dd('Para el tipo enum el enumarray es requerido');
		
		if (!strpos($aParams['campo'], ')')) {
			$arr = explode('.', $aParams['campo']);
			if (count($arr)>=2) $campoReal = $arr[count($arr) - 1]; else $campoReal = $aParams['campo'];
			$alias = str_replace('.','__', $aParams['campo']);
		} 
		else {
			$campoReal  = $aParams['campo'];
			$alias 			= 'a' . date('U') . count($this->getCamposShow()); //Nos inventamos un alias para los subqueries
			$searchable = false;
		}

		if($aParams['campo']==$this->modelo->getKeyName()) {
			$alias = 'idsinenc'  . count($this->getCamposShow());
			$edit  = false;
		}

		$arr = [
			'nombre'   			=> $nombre,
			'campo'    			=> $aParams['campo'],
			'alias'    			=> $alias,
			'campoReal'			=> $campoReal,
			'tipo'     			=> $tipo,
			'show'     			=> $show,
			'editable' 			=> $edit,
			'default'  			=> $default,
			'reglas'   			=> $reglas,
			'reglasmensaje' => $reglasmensaje,
			'class'    			=> $class,
			'decimales'			=> $decimales,
			'collection'   	=> $collection,
			'searchable'    => $searchable,
			'enumarray'     => $enumarray,
			'filepath'			=> $filepath,
			'filewidth'			=> $filewidth,
			'fileheight'		=> $fileheight,
			'target'        => $target,
		];
		$this->campos[] = $arr;
	}

	public function setJoin($aRelation){
		$this->joins[] = $aRelation;
	}

	public function setLeftJoin($aTabla, $aCol1, $aOperador, $aCol2) {
		$this->leftJoins[] = array('tabla'=>$aTabla, 'col1'=>$aCol1, 'operador'=>$aOperador, 'col2'=>$aCol2);
	}

	public function setWhere($aColumna, $aOperador, $aValor=null) {
		if($aValor == null){
			$aValor    = $aOperador;
			$aOperador = '=';
		}

		$this->wheres[] = array('columna'=>$aColumna, 'operador'=>$aOperador, 'valor'=>$aValor);
	}

	public function setWhereRaw($aStatement) {
		$this->wheresRaw[] = $aStatement;
	}

	public function setTitulo($aTitulo){
		$this->titulo = $aTitulo;
	}

	public function setNoGuardar($aCampo) {
		$this->noGuardar[] = $aCampo;
	}

	public function mostrarBreadcrumb($aBool) {
		$this->breadcrumb['mostrar'] = $aBool;
	}

	public function setBreadcrumb($aArray) {
		$this->breadcrumb['breadcrumb'] = $aArray;
	}

	public function setBotonExtra($aParams) {
		$allowed = array('url','titulo','target','icon','class','confirm','confirmmessage');


		foreach ($aParams as $key=>$val) { //Validamos que todas las variables del array son permitidas.
			if (!in_array($key, $allowed)) {
				dd('setBotonExtra no recibe parametros con el nombre: ' . $key . '! solamente se permiten: ' . implode(', ', $allowed));
			}
		}
		if(!array_key_exists('url', $aParams)) dd('setBotonExtra debe tener un valor para "url"');

		$icon           = (!array_key_exists('icon', $aParams) ? 'glyphicon glyphicon-star': $aParams['icon']); 
		$class          = (!array_key_exists('class', $aParams) ? 'default': $aParams['class']); 
		$titulo         = (!array_key_exists('titulo', $aParams) ? '': $aParams['titulo']); 
		$target         = (!array_key_exists('target', $aParams) ? '': $aParams['target']); 
		$confirm        = (!array_key_exists('confirm', $aParams) ? false: $aParams['confirm']);
		$confirmmessage = (!array_key_exists('confirmmessage', $aParams) ? '¿Estas seguro?': $aParams['confirmmessage']);


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

	public function setPermisos($aPermisos) {
		$this->permisos = $aPermisos;
	}

	public function setHidden($aParams) {
		$allowed = ['campo','valor'];

		foreach ($aParams as $key=>$val)  //Validamos que todas las variables del array son permitidas.
			if (!in_array($key, $allowed)) 
				dd('setHidden no recibe parametros con el nombre: ' . $key . '! solamente se permiten: ' . implode(', ', $allowed));
			
		$this->camposHidden[$aParams['campo']] = $aParams['valor'];
	}

	private function getQueryString($request) {
		$query = '?' . $request->getQueryString();
		if ($query=='?') $query = '';
		return $query;
	}

	public function setOrderBy($aParams) {
		$allowed     = ['columna','direccion'];
		$direcciones = ['asc','desc'];

		foreach ($aParams as $key=>$val)  //Validamos que todas las variables del array son permitidas.
			if (!in_array($key, $allowed))
				dd('setOrderBy no recibe parametros con el nombre: ' . $key . '! solamente se permiten: ' . implode(', ', $allowed));
		
		$columna    = (!array_key_exists('columna', $aParams) ? 0: $aParams['columna']);
		$direccion  = (!array_key_exists('direccion', $aParams) ? 'asc': $aParams['direccion']);

		$this->orders[$columna] = $direccion;
	}


	/*
	private static $showExport = true;
	private static $showSearch = true;
	private static $stateSave  = true;
	private static $softDelete = false;
	private static $perPage    = 20;
	private static $tabla;
	private static $tablaId;
	private static $titulo;
	private static $data;
	private static $colSlug       = 'slug';
	private static $slugSeparator = '-';
	private static $camposSlug    = array();
	private static $camposShow    = array();
	private static $camposEdit    = array();
	private static $camposHidden  = array();
	private static $wheres        = array();
	private static $wheresRaw     = array();
	private static $leftJoins     = array();
	private static $botonesExtra  = array();
	private static $orders        = array();
	private static $groups        = array();
	private static $permisos      = array('add'=>false,'edit'=>false,'delete'=>false);
	private static $template      = 'layouts/app';

	public static function getData($showEdit) {
		$response = array();
		$dataarr  = array();

		$selects = array();
		$query = DB::table($this->tabla);
		
		if($showEdit=='1')
			$campos = $this->camposEdit;
		else
			$campos = $this->getCamposShow();

		foreach ($campos as $campo) {
			$selects[] = $campo['campo'].' AS '.$campo['alias'];
		}
		$selects[] = $this->tabla . '.' . $this->tablaId;
		
		$query->selectRaw(implode(',',$selects));

		foreach($this->leftJoins as $leftJoin){
			$query->leftJoin($leftJoin['tabla'], $leftJoin['col1'], $leftJoin['operador'], $leftJoin['col2']);
		}

		foreach($this->wheres as $where){
			$query->where($where['columna'], $where['operador'], $where['valor']);
		}

		foreach($this->wheresRaw as $whereRaw){
			$query->whereRaw($whereRaw);
		}
		if ($this->softDelete) $query->whereNull($this->tabla . '.deleted_at');

		foreach($this->groups as $group) {
			$query->groupBy($group);
		}

		$registros = $query->count();
		
		$orders = Input::get('order');
		if ($orders) {
			foreach($orders as $order){
				$orderArray = explode(' AS ', $selects[$order['column']]);
				$query->orderBy(DB::raw($orderArray[0]), $order['dir']);
			}
		}

		$columns = Input::get('columns');
		$search  = Input::get('search');
		$i       = 0;
		$query->where(function($q) use ($columns, $selects, $i, $search){
			if ($columns) {
				foreach ($columns as $column) {
					if($column['searchable']){
						$select = explode(' AS ', $selects[$i]);
						$q->orWhere($select[0], 'like', '%'.$search['value'].'%');
					}
					$i++;
				}
			}
		});

		$filtrados = $query->count();

		$query->skip(Input::get('start'))
					->take(Input::get('length'));

		$data = $query->get();

		$response['draw']            = Input::get('draw');
		$response['recordsTotal']    = $registros;
		$response['recordsFiltered'] = $filtrados;
		foreach($data as $d){
			$tmparr = array();
			foreach($d as $columna => $valor){
				if ($columna==$this->tablaId) $tmparr[] = Crypt::encrypt($valor);
				else $tmparr[] = $valor;
			}

			$dataarr[] = $tmparr;
		}

		$response['data'] = $dataarr;
		return  Response::json($response);
	}

	public static function setExport($aBool){
		$this->showExport = $aBool;
	}

	public static function setSearch($aBool){
		$this->showSearch = $aBool;
	}

	public static function setSoftDelete($aBool){
		$this->softDelete = $aBool;
	}

	public static function setStateSave($aBool){
		$this->stateSave = $aBool;
	}
	
	public static function setSlug($aParams){
		$allowed = array('columnas','campo','separator');
		
		foreach ($aParams as $key=>$val) {  //Validamos que todas las variables del array son permitidas.
			if (!in_array($key, $allowed))
				dd('setSlug no recibe parametros con el nombre: ' . $key . '! solamente se permiten: ' . implode(', ', $allowed));

			else {
				if($key == 'columnas') {
					foreach($val as $columnas)
						$this->camposSlug[] = $columnas;
				}

				elseif($key == 'campo')
					$this->colSlug = $val;

				elseif($key == 'separator')
					$this->slugSeparator = $val;
			}
		}
	}

	public static function getSoftDelete(){
		return $this->softDelete;
	}

	public static function setPerPage($aCuantos){
		$this->perPage = $aCuantos;
	}

	public static function setTabla($aTabla){
		$this->tabla = $aTabla;
	}

	public static function setTablaId($aNombre){
		$this->tablaId = $aNombre;
	}

	public static function setTitulo($aNombre){
		$this->titulo = $aNombre;
	}

	public static function setBotonExtra($aParams) {
		$allowed = array('url','titulo','target','icon','class','confirm','confirmmessage');


		foreach ($aParams as $key=>$val) { //Validamos que todas las variables del array son permitidas.
			if (!in_array($key, $allowed)) {
				dd('setBotonExtra no recibe parametros con el nombre: ' . $key . '! solamente se permiten: ' . implode(', ', $allowed));
			}
		}
		if(!array_key_exists('url', $aParams)) dd('setBotonExtra debe tener un valor para "url"');

		$icon           = (!array_key_exists('icon', $aParams) ? 'glyphicon glyphicon-star': $aParams['icon']); 
		$class          = (!array_key_exists('class', $aParams) ? 'default': $aParams['class']); 
		$titulo         = (!array_key_exists('titulo', $aParams) ? '': $aParams['titulo']); 
		$target         = (!array_key_exists('target', $aParams) ? '': $aParams['target']); 
		$confirm        = (!array_key_exists('confirm', $aParams) ? false: $aParams['confirm']);
		$confirmmessage = (!array_key_exists('confirmmessage', $aParams) ? '¿Estas seguro?': $aParams['confirmmessage']);


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

	public static function setHidden($aParams) {
		$allowed = array('campo','valor');

		foreach ($aParams as $key=>$val)  //Validamos que todas las variables del array son permitidas.
			if (!in_array($key, $allowed)) 
				dd('setHidden no recibe parametros con el nombre: ' . $key . '! solamente se permiten: ' . implode(', ', $allowed));
			

		$arr = array(
			'campo' => $aParams['campo'],
			'valor'	=> $aParams['valor']
		);
		$this->camposHidden[] = $arr;
	}



	public static function setGroupBy($aCampo) {
		$this->groups[] = $aCampo;
	}

	public static function setCampo($aParams) {
		$allowed = array('campo','nombre','editable','show','tipo','class',
			'default','reglas', 'reglasmensaje', 'decimales','query','combokey',
			'enumarray','filepath','filewidth','fileheight','target');
		$tipos   = array('string','numeric','date','datetime','bool','combobox','password','enum','file','image','textarea','url','summernote');
		
		foreach ($aParams as $key=>$val) { //Validamos que todas las variables del array son permitidas.
			if (!in_array($key, $allowed)) {
				dd('setCampo no recibe parametros con el nombre: ' . $key . '! solamente se permiten: ' . implode(', ', $allowed));
			}
		}

		if(!array_key_exists('campo', $aParams)) dd('setCampo debe tener un valor para "campo"');

		$nombre        = (!array_key_exists('nombre', $aParams) ? str_replace('_', ' ', ucfirst($aParams['campo'])) : $aParams['nombre']); 
		$edit          = (!array_key_exists('editable', $aParams) ? true : $aParams['editable']);
		$show          = (!array_key_exists('show', $aParams) ? true : $aParams['show']);
		$tipo          = (!array_key_exists('tipo', $aParams) ? 'string' : $aParams['tipo']);
		$class         = (!array_key_exists('class', $aParams) ? '' : $aParams['class']);
		$default       = (!array_key_exists('default', $aParams) ? '' : $aParams['default']);
		$reglas        = (!array_key_exists('reglas', $aParams) ? [] : $aParams['reglas']);
		$decimales     = (!array_key_exists('decimales', $aParams) ? 0 : $aParams['decimales']);
		$query         = (!array_key_exists('query', $aParams) ? '' : $aParams['query']);
		$combokey      = (!array_key_exists('combokey', $aParams) ? '' : $aParams['combokey']);
		$reglasmensaje = (!array_key_exists('reglasmensaje', $aParams) ? '' : $aParams['reglasmensaje']);
		$filepath      = (!array_key_exists('filepath', $aParams) ? '' : $aParams['filepath']);
		$filewidth     = (!array_key_exists('filewidth', $aParams) ? 80 : $aParams['filewidth']);
		$fileheight    = (!array_key_exists('fileheight', $aParams) ? 80 : $aParams['fileheight']);
		$target        = (!array_key_exists('target', $aParams) ? '_blank' : $aParams['target']);
		$enumarray     = (!array_key_exists('enumarray', $aParams) ? [] : $aParams['enumarray']);
		$searchable    = true;

		if (!in_array($tipo, $tipos)) dd('El tipo configurado (' . $tipo . ') no existe! solamente se permiten: ' . implode(', ', $tipos));

		if($tipo == 'combobox' && ($query == '' || $combokey == '')) dd('Para el tipo combobox el query y combokey son requeridos');
		if($tipo == 'file' && $filepath == '') dd('Para el tipo file hay que especifiarle el filepath');
		if($tipo == 'image' && $filepath == '') dd('Para el tipo image hay que especifiarle el filepath');

		if($tipo == 'emum' && count($enumarray) == 0) dd('Para el tipo enum el enumarray es requerido');
		
		if (!strpos($aParams['campo'], ')')) {
			$arr = explode('.', $aParams['campo']);
			if (count($arr)>=2) $campoReal = $arr[count($arr) - 1]; else $campoReal = $aParams['campo'];
			$alias = str_replace('.','__', $aParams['campo']);
		} 
		else {
			$campoReal  = $aParams['campo'];
			$alias 			= 'a' . date('U') . count($this->getCamposShow()); //Nos inventamos un alias para los subqueries
			$searchable = false;
		}

		if($aParams['campo']==$this->tablaId) {
			$alias = 'idsinenc'  . count($this->getCamposShow());
			$edit  = false;
		}

		$arr = array(
			'nombre'   			=> $nombre,
			'campo'    			=> $aParams['campo'],
			'alias'    			=> $alias,
			'campoReal'			=> $campoReal,
			'tipo'     			=> $tipo,
			'show'     			=> $show,
			'editable' 			=> $edit,
			'default'  			=> $default,
			'reglas'   			=> $reglas,
			'reglasmensaje' => $reglasmensaje,
			'class'    			=> $class,
			'decimales'			=> $decimales,
			'query'    			=> $query,
			'combokey' 			=> $combokey,
			'searchable'    => $searchable,
			'enumarray'     => $enumarray,
			'filepath'			=> $filepath,
			'filewidth'			=> $filewidth,
			'fileheight'		=> $fileheight,
			'target'        => $target,
		);
		if ($show) $this->getCamposShow()[] = $arr;
		if ($edit) $this->camposEdit[] = $arr;
	}

	public static function setPermisos($aPermisos) {
		$this->permisos = $aPermisos;
	}

	public static function setTemplate($aTemplate){
		$this->template = $aTemplate;
	}

	private static function downLevel($aPath, $aEdit=false) {
		$arr = explode('/', $aPath);
		array_pop($arr);
		if($aEdit) array_pop($arr);
		$route = implode('/', $arr);
		return $route;
	}

	private static function getGetVars(){
		$getVars = Request::server('QUERY_STRING');
		$nuevasVars = '';
		if ($getVars!='') $nuevasVars = '?' . $getVars;
		return $nuevasVars;
	}

	public static function index() {
		if ($this->tabla=='')   dd('setTabla es obligatorio.');
		if ($this->tablaId=='') dd('setTablaId es obligatorio.');
				
		return view('csgtcrud::index')
			->with('template',    $this->template)
			->with('stateSave',   $this->stateSave)
			->with('showExport', 	$this->showExport)
			->with('showSearch', 	$this->showSearch)
			->with('perPage', 		$this->perPage)
			->with('titulo', 			$this->titulo)
			->with('columnas', 		$this->getCamposShow())
			->with('permisos', 		$this->permisos)
			->with('orders', 			$this->orders)
			->with('botonesExtra',$this->botonesExtra)
			->with('nuevasVars', self::getGetVars());
	}

	public static function create($aId) {
		$data  = null;
		$hijo  = 'Nuevo';

		if(!$aId==0){
			$data = DB::table($this->tabla)
				->where($this->tablaId, Crypt::decrypt($aId))
				->first();
			$hijo = 'Editar';
			$path = self::downLevel(Request::path(), true);
		}
		else {
			$path  = self::downLevel(Request::path(), false);
		}
		
		$route = str_replace($aId, '', $path);

		$combos = null;
		foreach($this->camposEdit as $campo){
			if($campo['tipo'] == 'combobox'){
				$resultados = DB::select(DB::raw($campo['query']));
				$temp       = array();
				foreach($resultados as $resultado){
					$i = 0;
					foreach($resultado as $columna){
						if($i == 0) $nombre = $columna;
						else $id = $columna;
						$i++;
					}

					$temp[$id] = $nombre;
				}
				$combos[$campo['alias']] = $temp;
			}
		}

		return View::make('csgtcrud::edit')
			->with('pathstore', self::downLevel(Request::path(), false))
			->with('template',   $this->template)
			->with('breadcrum',  array('padre' =>array('titulo'=>$this->titulo,'ruta'=>$path), 'hijo'=>$hijo))
			->with('columnas',   $this->camposEdit)
			->with('data',       $data)
			->with('combos',     $combos)
			->with('nuevasVars', self::getGetVars());
	}

	public static function store($id=null) {
		$data          = array();
		$slug          = '';
		$no_permitidas = array ("á","é","í","ó","ú","Á","É","Í","Ó","Ú","ñ","À","Ã","Ì","Ò","Ù","Ã™","Ã ","Ã¨","Ã¬","Ã²","Ã¹","ç","Ç","Ã¢","ê","Ã®","Ã´","Ã»","Ã‚","ÃŠ","ÃŽ","Ã”","Ã›","ü","Ã¶","Ã–","Ã¯","Ã¤","«","Ò","Ã","Ã„","Ã‹");
		$permitidas    = array ("a","e","i","o","u","A","E","I","O","U","n","N","A","E","I","O","U","a","e","i","o","u","c","C","a","e","i","o","u","A","E","I","O","U","u","o","O","i","a","e","U","I","A","E");

		foreach($this->camposEdit as $campo) {
			if ($campo['tipo']=='bool') 
				$data[$campo['campoReal']] = Input::get($campo['campoReal'],0);
			else if ($campo['tipo']=='combobox') {
				if (Input::get($campo['combokey'])=='') 
					$data[$campo['combokey']] = null;
				else 
					$data[$campo['combokey']] = Input::get($campo['combokey']);
			}
			else if ($campo['tipo']=='date') {
				$laFecha = explode('/',Input::get($campo['campoReal']));
				if (count($laFecha)==3) {
					$data[$campo['campoReal']] = $laFecha[2] . '-' . $laFecha[1] . '-' . $laFecha[0];
				}
				else {
					$data[$campo['campoReal']] = null;
				}
			}
			else if ($campo['tipo']=='datetime') {
				$fechaHora = explode(' ', Input::get($campo['campoReal']));
				if (count($fechaHora)==2) {
					$laFecha = explode('/',$fechaHora[0]);
					if (count($laFecha)<>3) {
						$data[$campo['campoReal']] = null;
					}
					else {
						$data[$campo['campoReal']] = $laFecha[2] . '-' . $laFecha[1] . '-' . $laFecha[0] . ' ' . $fechaHora[1];
					}
				}
				else {
					$data[$campo['campoReal']] = null;
				}
			}
			else if ($campo['tipo']=='password') {
				if($id == null)
					$data[$campo['campoReal']] = Hash::make(Input::get($campo['campoReal']));
				else {
					if(Input::get($campo['campoReal']) <> '')
						$data[$campo['campoReal']] = Hash::make(Input::get($campo['campoReal']));
				}
			}
			else if (($campo['tipo']=='file')||($campo['tipo']=='image')) {
				if (Input::hasFile($campo['campoReal'])) {
					$file = Input::file($campo['campoReal']);
					
					$filename = date('Ymdhis') . mt_rand(1, 1000) . '.' . strtolower($file->getClientOriginalExtension());
					$path     = public_path() . $campo['filepath'];

					if (!file_exists($path)) {
    				mkdir($path, 0777, true);
					}

					$file->move($path, $filename);
					
					$data[$campo['campoReal']] = $filename;
				}
			}
			else
				$data[$campo['campoReal']] = Input::get($campo['campoReal']);

			if(in_array($campo['campoReal'], $this->camposSlug)) {
				$temp  = strtolower(Input::get($campo['campoReal']));
				$temp  = str_replace(' ', $this->slugSeparator, $temp);
				$temp  = str_replace('\\', 'y', $temp);
				$temp  = str_replace('+', 'y', $temp);
				$temp  = str_replace('-', '', $temp);
				$temp  = str_replace('\'', '', $temp);
				$temp  = str_replace($no_permitidas, $permitidas ,$temp);
				$slug .= $temp; 
			}
		}

		if($slug <> '' && $id == null) {
			$result = DB::table($this->tabla)->where($this->colSlug, $slug)->first();
			if(!$result)
				$data[$this->colSlug] = $slug;

			else {

				$i = 1;
				while ($result) {
					$i++;
					$result = DB::table($this->tabla)->where($this->colSlug, $slug.$this->slugSeparator.$i)->first();
				}
				
				$data[$this->colSlug] = $slug.$this->slugSeparator.$i;
			}

		}

		$data['updated_at'] = date_create();

		foreach ($this->camposHidden as $campo) {
			$data[$campo['campo']] = $campo['valor'];
		}

		if($id == null){
			$data['created_at'] = date_create();
			try {
				$query = DB::table($this->tabla)
					->insert($data);
				Session::flash('message', trans('csgtcrud::crud.registrook'));
				Session::flash('type', 'success');
			}
			catch (\Exception $e) {
				Session::flash('message', trans('csgtcrud::crud.registroerror') . $e->getMessage());
				Session::flash('type', 'danger');
			}
			
			return Redirect::to(Request::path() . self::getGetVars());
		}

		else {
			try {
				$query = DB::table($this->tabla)
					->where($this->tablaId, Crypt::decrypt($id))
					->update($data);

				Session::flash('message',  trans('csgtcrud::crud.registrook'));
				Session::flash('type', 'success');
			}
			catch (\Exception $e) {
				Session::flash('message', trans('csgtcrud::crud.registroerror') . $e->getMessage());
				Session::flash('type', 'danger');
			}
			return Redirect::to(self::downLevel(Request::path(), false) . self::getGetVars());	
		}
	}

	public static function destroy($aId) {
		try{
			if ($this->softDelete){
				$query = DB::table($this->tabla)
					->where($this->tablaId, Crypt::decrypt($aId))
					->update(array('deleted_at'=>date_create()));
			}
			else
				$query = DB::table($this->tabla)
					->where($this->tablaId, Crypt::decrypt($aId))
					->delete();

			Session::flash('message', trans('csgtcrud::crud.registroeliminado'));
			Session::flash('type', 'warning');

		} catch (\Exception $e) {
			Session::flash('message', trans('csgtcrud::crud.registroelimiandoe'));
			Session::flash('type', 'danger');
		}

				return Redirect::to(self::downLevel(Request::path(), false) . '?' . Request::server('QUERY_STRING'));
	}
	*/
}