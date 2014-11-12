<?php 
namespace Csgt\Crud;

use Hash, View, DB, Input, Response, Request, Session, Redirect, Crypt;


class Crud {
	private static $showExport = true;
	private static $showSearch = true;
	private static $softDelete = false;
	private static $perPage    = 20;
	private static $tabla;
	private static $tablaId;
	private static $titulo;
	private static $data;
	private static $camposShow   = array();
	private static $camposEdit   = array();
	private static $camposHidden = array();
	private static $wheres       = array();
	private static $wheresRaw    = array();
	private static $leftJoins    = array();
	private static $botonesExtra = array();
	private static $orders       = array();
	private static $permisos     = array('add'=>false,'edit'=>false,'delete'=>false);

	public static function getData($showEdit) {
		$response = array();
		$dataarr  = array();

		$selects = array();
		$query = DB::table(self::$tabla);
		
		if($showEdit=='1')
			$campos = self::$camposEdit;
		else
			$campos = self::$camposShow;

		foreach ($campos as $campo) {
			$selects[] = $campo['campo'].' AS '.$campo['alias'];
		}
		$selects[] = self::$tablaId;
		
		$query->selectRaw(implode(',',$selects));

		foreach(self::$leftJoins as $leftJoin){
			$query->leftJoin($leftJoin['tabla'], $leftJoin['col1'], $leftJoin['operador'], $leftJoin['col2']);
		}

		foreach(self::$wheres as $where){
			$query->where($where['columna'], $where['operador'], $where['valor']);
		}

		foreach(self::$wheresRaw as $whereRaw){
			$query->whereRaw($whereRaw);
		}
		if (self::$softDelete) $query->whereNull(self::$tabla . '.deleted_at');

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
				if ($columna==self::$tablaId) $tmparr[] = Crypt::encrypt($valor);
				else $tmparr[] = $valor;
			}

			$dataarr[] = $tmparr;
		}

		$response['data'] = $dataarr;
		return  Response::json($response);
	}

	public static function setExport($aBool){
		self::$showExport = $aBool;
	}

	public static function setSearch($aBool){
		self::$showSearch = $aBool;
	}

	public static function setSoftDelete($aBool){
		self::$softDelete = $aBool;
	}

	public static function setPerPage($aCuantos){
		self::$perPage = $aCuantos;
	}

	public static function setTabla($aTabla){
		self::$tabla = $aTabla;
	}

	public static function setTablaId($aNombre){
		self::$tablaId = $aNombre;
	}

	public static function setTitulo($aNombre){
		self::$titulo = $aNombre;
	}

	public static function setBotonExtra($aParams) {
		$allowed = array('url','titulo','target','icon','class');
		foreach ($aParams as $key=>$val) { //Validamos que todas las variables del array son permitidas.
			if (!in_array($key, $allowed)) {
				dd('setBotonExtra no recibe parametros con el nombre: ' . $key . '! solamente se permiten: ' . implode(', ', $allowed));
			}
		}
		if(!array_key_exists('url', $aParams)) dd('setBotonExtra debe tener un valor para "url"');

		$icon   = (!array_key_exists('icon', $aParams) ? 'glyphicon glyphicon-star': $aParams['icon']); 
		$class  = (!array_key_exists('class', $aParams) ? 'default': $aParams['class']); 
		$titulo = (!array_key_exists('titulo', $aParams) ? '': $aParams['titulo']); 

		$arr = array(
			'url'      => $aParams['url'],
			'titulo'	 => $titulo,
			'icon'     => $icon,
			'class'    => $class,
		);
		self::$botonesExtra[] = $arr;
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
		self::$camposHidden[] = $arr;
	}

	public static function setOrderBy($aParams) {
		$allowed     = array('columna','direccion');
		$direcciones = array('asc','desc');

		foreach ($aParams as $key=>$val)  //Validamos que todas las variables del array son permitidas.
			if (!in_array($key, $allowed))
				dd('setOrderBy no recibe parametros con el nombre: ' . $key . '! solamente se permiten: ' . implode(', ', $allowed));
		
		$columna    = (!array_key_exists('columna', $aParams) ? 0: $aParams['columna']);
		$direccion  = (!array_key_exists('direccion', $aParams) ? 'asc': $aParams['direccion']);

		self::$orders[$columna] = $direccion;
	}

	public static function setCampo($aParams) {
		$allowed = array('campo','nombre','editable','show','tipo','class',
			'default','reglas', 'reglasmensaje', 'decimales','query','combokey','enumarray','filepath');
		$tipos   = array('string','numeric','date','datetime','bool','combobox','password','enum','file');
		
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
		$reglas        = (!array_key_exists('reglas', $aParams) ? array() : $aParams['reglas']);
		$decimales     = (!array_key_exists('decimales', $aParams) ? 0 : $aParams['decimales']);
		$query         = (!array_key_exists('query', $aParams) ? '' : $aParams['query']);
		$combokey      = (!array_key_exists('combokey', $aParams) ? '' : $aParams['combokey']);
		$reglasmensaje = (!array_key_exists('reglasmensaje', $aParams) ? '' : $aParams['reglasmensaje']);
		$filepath      = (!array_key_exists('filepath', $aParams) ? '' : $aParams['filepath']);
		$enumarray     = (!array_key_exists('enumarray', $aParams) ? array() : $aParams['enumarray']);
		$searchable    = true;

		if (!in_array($tipo, $tipos)) dd('El tipo configurado (' . $tipo . ') no existe! solamente se permiten: ' . implode(', ', $tipos));

		if($tipo == 'combobox' && ($query == '' || $combokey == '')) dd('Para el tipo combobox el query y combokey son requeridos');
		if($tipo == 'file' && $filepath == '') dd('Para el tipo file hay que especifiarle el filepath');

		if($tipo == 'emum' && count($enumarray) == 0) dd('Para el tipo enum el enumarray es requerido');
		
		if (!strpos($aParams['campo'], ')')) {
			$arr = explode('.', $aParams['campo']);
			if (count($arr)>=2) $campoReal = $arr[1]; else $campoReal = $aParams['campo'];
			$alias = str_replace('.','__', $aParams['campo']);
		} 

		else {
			$campoReal  = $aParams['campo'];
			$alias 			= 'a' . date('U') . count(self::$camposShow); //Nos inventamos un alias para los subqueries
			$searchable = false;
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
			'filepath'			=> $filepath
		);
		if ($show) self::$camposShow[] = $arr;
		if ($edit) self::$camposEdit[] = $arr;
	}

	public static function setWhere($aColumna, $aOperador, $aValor=null) {
		if($aValor == null){
			$aValor    = $aOperador;
			$aOperador = '=';
		}

		self::$wheres[] = array('columna'=>$aColumna, 'operador'=>$aOperador, 'valor'=>$aValor);
	}

	public static function setWhereRaw($aStatement) {
		self::$wheresRaw[] = $aStatement;
	}

	public static function setLeftJoin($aTabla, $aCol1, $aOperador, $aCol2) {
		self::$leftJoins[] = array('tabla'=>$aTabla, 'col1'=>$aCol1, 'operador'=>$aOperador, 'col2'=>$aCol2);
	}

	public static function setPermisos($aPermisos) {
		self::$permisos = $aPermisos;
	}

	private static function getUrl($aPath) {
		$arr = explode('/', $aPath);
		array_pop($arr);
		$route = implode('/', $arr);
		return $route;
	}

	public static function index() {
		if (self::$tabla=='')   dd('setTabla es obligatorio.');
		if (self::$tablaId=='') dd('setTablaId es obligatorio.');

		$getVars = $_SERVER['QUERY_STRING']==''?'': '?' . str_replace('?', '&', $_SERVER['QUERY_STRING']);
		
		return View::make('crud::index')
			->with('showExport', 	self::$showExport)
			->with('showSearch', 	self::$showSearch)
			->with('perPage', 		self::$perPage)
			->with('titulo', 			self::$titulo)
			->with('columnas', 		self::$camposShow)
			->with('permisos', 		self::$permisos)
			->with('orders', 			self::$orders)
			->with('botonesExtra',self::$botonesExtra)
			->with('getVars', $getVars);
	}

	public static function create($aId) {
		$data = null;
		$hijo = 'Nuevo';
		if(!$aId==0){
			$data = DB::table(self::$tabla)
				->where(self::$tablaId, Crypt::decrypt($aId))
				->first();
			$hijo = 'Editar';
		}

		$route = self::getUrl(Request::path());

		$combos = null;
		foreach(self::$camposEdit as $campo){
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


		return View::make('crud::edit')
			->with('breadcrum', array('padre'=>array('titulo'=>self::$titulo,'ruta'=>$route), 'hijo'=>$hijo))
			->with('columnas', self::$camposEdit)
			->with('data', $data)
			->with('combos', $combos);
	}

	public static function store($id=null) {
		$data  = array();

		//dd(self::$camposEdit);
		foreach(self::$camposEdit as $campo){
			if ($campo['tipo']=='bool') 
				$data[$campo['campoReal']] = Input::get($campo['campoReal'],0);
			else if ($campo['tipo']=='combobox')
				$data[$campo['combokey']] = Input::get($campo['combokey']);
			else if ($campo['tipo']=='password') {
				$data[$campo['campoReal']] = Hash::make(Input::get($campo['campoReal']));
			}
			else if ($campo['tipo']=='file') {
				if (Input::hasFile($campo['campoReal']))
				{
					$file = Input::file($campo['campoReal']);
					
					$filename = date('Ymdhi').$file->getClientOriginalName();
					$file->move($campo['filepath'], $filename);
					
					$data[$campo['campoReal']] = $filename;
				}
			}
			else
				$data[$campo['campoReal']] = Input::get($campo['campoReal']);
		}
		$data['updated_at'] = date_create();

		foreach (self::$camposHidden as $campo) {
			$data[$campo['campo']] = $campo['valor'];
		}

		if($id == null){
			$data['created_at'] = date_create();

			$query = DB::table(self::$tabla)
				->insert($data);

			Session::flash('message', 'Registro creado exitosamente');
			Session::flash('type', 'success');
			return Redirect::to(Request::path());
		}

		else {
			$query = DB::table(self::$tabla)
				->where(self::$tablaId, Crypt::decrypt($id))
				->update($data);

			Session::flash('message', 'Registro actualizado exitosamente');
			Session::flash('type', 'success');
			return Redirect::to(self::getUrl(Request::path()));	
		}
	}

	public static function destroy($aId) {
		try{
			if (self::$softDelete){
				$query = DB::table(self::$tabla)
					->where(self::$tablaId, Crypt::decrypt($aId))
					->update(array('deleted_at'=>date_create()));
			}
			else
				$query = DB::table(self::$tabla)
					->where(self::$tablaId, Crypt::decrypt($aId))
					->delete();

			Session::flash('message', 'Registro borrado exitosamente');
			Session::flash('type', 'warning');

		} catch (\Exception $e) {
			Session::flash('message', 'Error al borrar campo. Revisar datos relacionados.');
			Session::flash('type', 'danger');
		}

		return Redirect::to(self::getUrl(Request::path()));
	}
}