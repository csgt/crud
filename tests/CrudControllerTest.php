<?php
namespace Csgt\Crud\Tests;

use Csgt\Crud\Tests\Fixtures\Client;
use Csgt\Crud\Tests\Fixtures\ClientsController;

/**
 * Un grupo de pruebas por cada metodo de CrudController que arma la consulta de
 * listado. Todo se comprueba sobre el SQL y los bindings generados.
 *
 * Esta rama no tiene ningun flag "isforeign" en los campos: una columna de
 * relacion se detecta unicamente por el punto en "campo" (ver
 * getCamposShowForeign / applyOrderToQuery). Tampoco hay filtro por columna:
 * el listado solo soporta la busqueda global de DataTables via applySearchToQuery.
 */
class CrudControllerTest extends TestCase {
	protected $controller;

	protected function setUp(): void {
		parent::setUp();

		$this->controller = new ClientsController;
	}

	protected function query() {
		return Client::query();
	}

	/*==================== getSelect ====================*/

	public function testGetSelectReturnsOneExpressionPerLocalShownField() {
		$columns = $this->call($this->controller, 'getCamposShowMine');
		$select  = $this->call($this->controller, 'getSelect', [$columns]);

		$this->assertSame(['name', 'email', 'CONCAT(name, id)'], $select);
	}

	/*==================== getCamposShowMine ====================*/

	public function testGetCamposShowMineExcludesRelationsAndHiddenFields() {
		$fields = array_column($this->call($this->controller, 'getCamposShowMine'), 'campo');

		$this->assertSame(['name', 'email', 'CONCAT(name, id)'], $fields);
	}

	/*==================== getCamposShowForeign ====================*/

	public function testGetCamposShowForeignGroupsColumnsByRelation() {
		$foreigns = $this->call($this->controller, 'getCamposShowForeign');

		$this->assertSame(['country'], array_keys($foreigns));
		$this->assertSame('name', $foreigns['country'][0][0]);
	}

	/*==================== getCamposOrden ====================*/

	public function testGetCamposOrdenKeepsColumnOrderAndAppendsTheUniqueId() {
		$order = $this->call($this->controller, 'getCamposOrden');

		$this->assertSame(
			['name', 'country.name', 'email', 'CONCAT(name, id)', '___id___'],
			$order
		);
	}

	/*==================== stripAlias ====================*/

	public function testStripAliasRemovesTheAliasAndKeepsPlainColumns() {
		$this->assertSame('country.name', $this->call($this->controller, 'stripAlias', ['country.name AS country_name']));
		$this->assertSame('name', $this->call($this->controller, 'stripAlias', ['  name  ']));
		$this->assertSame('CONCAT(a, b)', $this->call($this->controller, 'stripAlias', ['CONCAT(a, b) as composed']));
	}

	/*==================== qualifyRelatedColumn ====================*/

	public function testQualifyRelatedColumnOnlyPrefixesPlainIdentifiers() {
		$model = new \Csgt\Crud\Tests\Fixtures\Country;

		$this->assertSame('countries.name', $this->call($this->controller, 'qualifyRelatedColumn', [$model, 'name']));
		$this->assertSame('CONCAT(a, b)', $this->call($this->controller, 'qualifyRelatedColumn', [$model, 'CONCAT(a, b)']));
		$this->assertSame('other.name', $this->call($this->controller, 'qualifyRelatedColumn', [$model, 'other.name']));
	}

	/*==================== applyOrderToQuery ====================*/

	public function testApplyOrderToQueryOrdersALocalColumn() {
		$query = $this->query();
		$this->call($this->controller, 'applyOrderToQuery', [$query, 'name', 'desc']);

		$this->assertStringContainsString('order by "name" desc', $query->toSql());
	}

	public function testApplyOrderToQueryOrdersARawExpressionWithoutQuotingIt() {
		$query = $this->query();
		$this->call($this->controller, 'applyOrderToQuery', [$query, 'CONCAT(name, id)', 'asc']);

		$this->assertStringContainsString('order by CONCAT(name, id) asc', $query->toSql());
	}

	// No hay prueba para el ordenamiento por relacion via subquery correlacionado:
	// esa rama llama \DB::raw() y el archivo no tiene "use DB;" (bug preexistente,
	// no se corrige aqui). Solo funciona si algo externo registra el alias global
	// "DB" (una app Laravel real lo hace); no queremos que la suite dependa de eso.
	// El resto de applyOrderToQuery (columna local, expresion cruda y el fallback
	// de relacion desconocida) no toca esa linea y si esta cubierto arriba/abajo.
	// Nota: cuando el Eloquent instalado no tiene getRelationExistenceQuery
	// (Laravel < 5.5) esta rama tampoco se alcanza y cae al orden por la llave
	// primaria; con nuestra dependencia de desarrollo (Eloquent moderno) si se
	// alcanzaria de tener una prueba.

	public function testApplyOrderToQueryFallsBackToAPlainOrderForAnUnknownRelation() {
		$query = $this->query();
		$this->call($this->controller, 'applyOrderToQuery', [$query, 'missing.name', 'asc']);

		$this->assertStringContainsString('order by "missing"."name" asc', $query->toSql());
	}

	/*==================== applySearchToQuery ====================*/

	public function testApplySearchToQueryFiltersOverTheGivenSearchableColumns() {
		// This is exactly what data() passes: the local shown columns. CONCAT(name, id)
		// is not searchable (see below), so only the two plain columns take part.
		$columns = $this->call($this->controller, 'getCamposShowMine');
		$query   = $this->query();

		$this->call($this->controller, 'applySearchToQuery', [$query, 'ACME', $columns]);

		$sql = $query->toSql();

		$this->assertStringContainsString('LOWER(name) LIKE ?', $sql);
		$this->assertStringContainsString('LOWER(email) LIKE ?', $sql);
		$this->assertStringNotContainsString('CONCAT', $sql);
		$this->assertSame(['%acme%', '%acme%'], $query->getBindings());
	}

	public function testApplySearchToQuerySkipsColumnsThatAreNotSearchable() {
		// setCampo marca automaticamente searchable=false para expresiones con
		// parentesis (ver 'CONCAT(name, id)'), asi que este campo se filtra solo.
		$columns = $this->call($this->controller, 'getCamposShow');
		$query   = $this->query();

		$this->call($this->controller, 'applySearchToQuery', [$query, 'acme', $columns]);

		$sql = $query->toSql();

		$this->assertStringContainsString('LOWER(name) LIKE ?', $sql);
		$this->assertStringContainsString('LOWER(country.name) LIKE ?', $sql);
		$this->assertStringContainsString('LOWER(email) LIKE ?', $sql);
		$this->assertStringNotContainsString('CONCAT', $sql);
		$this->assertSame(['%acme%', '%acme%', '%acme%'], $query->getBindings());
	}

	public function testApplySearchToQuerySkipsTheUniqueIdColumn() {
		$columns = [
			['campo' => '___id___', 'searchable' => true],
		];
		$query = $this->query();

		$this->call($this->controller, 'applySearchToQuery', [$query, 'acme', $columns]);

		$this->assertSame([], $query->getBindings());
	}

	/*==================== downLevel ====================*/

	public function testDownLevelRemovesTheLastSegmentOfThePath() {
		$this->assertSame('clients', $this->call($this->controller, 'downLevel', ['clients/5']));
		$this->assertSame('', $this->call($this->controller, 'downLevel', ['clients']));
	}

	/*==================== setters ====================*/

	public function testSetCampoAndSetTituloStoreFields() {
		$this->assertSame('Clients', $this->read($this->controller, 'titulo'));
		$this->assertCount(5, $this->read($this->controller, 'campos'));
	}

	public function testSetCampoMarksExpressionFieldsAsNotSearchable() {
		$campos = $this->read($this->controller, 'campos');

		$this->assertTrue($campos[0]['searchable']);
		$this->assertFalse($campos[3]['searchable']);
	}
}
