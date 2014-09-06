<?php 
namespace Csgt\Crud;

use Hash, View, DB, Input, Response, Request, Session, Redirect, Crypt;


class Crud {
	private $showExport = true;
	private $perPage    = 20;
	private $tabla;
	private $tablaId;
	private $titulo;
	private $data;
	private $camposShow   = array();
	private $camposEdit   = array();
	private $wheres       = array();
	private $wheresRaw    = array();
	private $leftJoins    = array();
	private $botonesExtra = array();
	private $permisos     = array('add'=>false,'edit'=>false,'delete'=>false);

	public function getData($showEdit) {
		$response = array();
		$dataarr  = array();

		$selects = array();
		$query = DB::table($this->tabla);
		
		if($showEdit=='1')
			$campos = $this->camposEdit;
		else
			$campos = $this->camposShow;

		foreach ($campos as $campo) {
			$selects[] = $campo['campo'].' AS '.$campo['alias'];
		}
		$selects[] = $this->tablaId;
		
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

		$registros = $query->count();
		
		$orders = Input::get('order');
		foreach($orders as $order){
			$orderArray = explode(' AS ', $selects[$order['column']]);
			$query->orderBy($orderArray[0], $order['dir']);
		}

		$columns = Input::get('columns');
		$search  = Input::get('search');
		$i       = 0;
		$query->where(function($q) use ($columns, $selects, $i, $search){
			foreach ($columns as $column) {
				if($column['searchable']){
					$select = explode(' AS ', $selects[$i]);
					$q->orWhere($select[0], 'like', '%'.$search['value'].'%');
				}
				$i++;
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

	public function setExport($aBool){
		$this->showExport = $aBool;
	}

	public function setPerPage($aCuantos){
		$this->perPage = $aCuantos;
	}

	public function setTabla($aTabla){
		$this->tabla = $aTabla;
	}

	public function setTablaId($aNombre){
		$this->tablaId = $aNombre;
	}

	public function setTitulo($aNombre){
		$this->titulo = $aNombre;
	}

	public function setBotonExtra($aParams) {
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
		$this->botonesExtra[] = $arr;
	}

	public function setCampo($aParams) {
		$allowed = array('campo','nombre','editable','show','tipo','class','default','rules','decimales','query','combokey');
		$tipos   = array('string','numeric','date','datetime','bool','combobox');
		
		foreach ($aParams as $key=>$val) { //Validamos que todas las variables del array son permitidas.
			if (!in_array($key, $allowed)) {
				dd('setCampo no recibe parametros con el nombre: ' . $key . '! solamente se permiten: ' . implode(', ', $allowed));
			}
		}

		if(!array_key_exists('campo', $aParams)) dd('setCampo debe tener un valor para "campo"');

		$nombre    = (!array_key_exists('nombre', $aParams) ? str_replace('_', ' ', ucfirst($aParams['campo'])) : $aParams['nombre']); 
		$edit      = (!array_key_exists('editable', $aParams) ? true : $aParams['editable']);
		$show      = (!array_key_exists('show', $aParams) ? true : $aParams['show']);
		$tipo      = (!array_key_exists('tipo', $aParams) ? 'string' : $aParams['tipo']);
		$class     = (!array_key_exists('class', $aParams) ? '' : $aParams['class']);
		$default   = (!array_key_exists('default', $aParams) ? '' : $aParams['default']);
		$rules     = (!array_key_exists('rules', $aParams) ? '' : $aParams['rules']);
		$decimales = (!array_key_exists('decimales', $aParams) ? 0 : $aParams['decimales']);
		$query     = (!array_key_exists('query', $aParams) ? '' : $aParams['query']);
		$combokey  = (!array_key_exists('combokey', $aParams) ? '' : $aParams['combokey']);

		if (!in_array($tipo, $tipos)) dd('El tipo configurado (' . $tipo . ') no existe! solamente se permiten: ' . implode(', ', $tipos));

		if($tipo == 'combobox' && ($query == '' || $combokey == '')) dd('Para el tipo combobox el query y combokey son requeridos');
	
		
		if (!strpos($aParams['campo'], ')')) {
			$arr = explode('.', $aParams['campo']);
			if (count($arr)>=2) $campoReal = $arr[1]; else $campoReal = $aParams['campo'];
			$alias = str_replace('.','__', $aParams['campo']);
		} 

		else {
			$campoReal  = $aParams['campo'];
			$alias 			= 'a' . date('U') . count($this->campos); //Nos inventamos un alias para los subqueries
		}

		$arr = array(
			'nombre'   => $nombre,
			'campo'    => $aParams['campo'],
			'alias'    => $alias,
			'campoReal'=> $campoReal,
			'tipo'     => $tipo,
			'show'     => $show,
			'editable' => $edit,
			'default'  => $default,
			'rules'    => $rules,
			'class'    => $class,
			'decimales'=> $decimales,
			'query'    => $query,
			'combokey' => $combokey
		);
		if ($show) $this->camposShow[] = $arr;
		if ($edit) $this->camposEdit[] = $arr;
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

	public function setLeftJoin($aTabla, $aCol1, $aOperador, $aCol2) {
		$this->leftJoins[] = array('tabla'=>$aTabla, 'col1'=>$aCol1, 'operador'=>$aOperador, 'col2'=>$aCol2);
	}

	public function setPermisos($aPermisos) {
		$this->permisos = $aPermisos;
	}



	private function getUrl($aPath) {
		$arr = explode('/', $aPath);
		array_pop($arr);
		$route = implode('/', $arr);
		return $route;
	}

	public function index() {
		if ($this->tabla=='')   dd('setTabla es obligatorio.');
		if ($this->tablaId=='') dd('setTablaId es obligatorio.');
		return View::make('crud::index')
			->with('showExport', $this->showExport)
			->with('perPage', $this->perPage)
			->with('titulo', $this->titulo)
			->with('columnas', $this->camposShow)
			->with('permisos', $this->permisos)
			->with('botonesExtra', $this->botonesExtra);
	}

	public function create($aId) {
		$data = null;
		$hijo = 'Nuevo';
		if(!$aId==0){
			$data = DB::table($this->tabla)
				->where($this->tablaId, Crypt::decrypt($aId))
				->first();
			$hijo = 'Editar';
		}

		$route = $this->getUrl(Request::path());

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

				$combos[$campo['campoReal']] = $temp;
			}
		}

		return View::make('crud::edit')
			->with('breadcrum', array('padre'=>array('titulo'=>$this->titulo,'ruta'=>$route), 'hijo'=>$hijo))
			->with('columnas', $this->camposEdit)
			->with('data', $data)
			->with('combos', $combos);
	}

	public function store($id=null) {
		$data  = array();
		//dd($this->camposEdit);
		foreach($this->camposEdit as $campo){
			if ($campo['tipo']=='bool') 
				$data[$campo['campoReal']] = Input::get($campo['campoReal'],0);
			else if ($campo['tipo']=='combobox')
				$data[$campo['combokey']] = Input::get($campo['combokey']);
			else
				$data[$campo['campoReal']] = Input::get($campo['campoReal']);
		}
		$data['updated_at'] = date_create();
		if($id == null){
			$data['created_at'] = date_create();

			$query = DB::table($this->tabla)
				->insert($data);

			Session::flash('message', 'Registro creado exitosamente');
			Session::flash('type', 'success');
			return Redirect::to(Request::path());
		}

		else {
			$query = DB::table($this->tabla)
				->where($this->tablaId, Crypt::decrypt($id))
				->update($data);

			Session::flash('message', 'Registro actualizado exitosamente');
			Session::flash('type', 'success');
			return Redirect::to($this->getUrl(Request::path()));	
		}
	}

	public function destroy($aId) {
		try{
			$query = DB::table($this->tabla)
				->where($this->tablaId, $aId)
				->delete();

			Session::flash('message', 'Registro borrado exitosamente');
			Session::flash('type', 'warning');

		} catch (\Exception $e) {
			Session::flash('message', 'Error al borrar campo. Revisar datos relacionados.');
			Session::flash('type', 'danger');
		}

		return Redirect::to($this->getUrl(Request::path()));
	}
}