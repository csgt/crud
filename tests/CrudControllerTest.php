<?php
namespace Csgt\Crud\Tests;

use Csgt\Crud\Tests\Fixtures\Client;
use Csgt\Crud\Tests\Fixtures\ClientsController;

/**
 * One group of tests per method of CrudController that shapes the listing
 * query. Everything is asserted on the generated SQL and bindings.
 *
 * This branch has no "isforeign" flag on the fields: a relation column is
 * detected only by the dot in "campo" (see getCamposShowForeign / applyOrderToQuery).
 * It also has no per-column filter (no getFilterColumns/applyColumnFilter):
 * the listing only supports the single global DataTables search handled by
 * applySearchToQuery.
 */
class CrudControllerTest extends TestCase
{
    protected $controller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->controller = new ClientsController;
    }

    protected function query()
    {
        return Client::query();
    }

    /*==================== getSelect ====================*/

    public function testGetSelectReturnsOneExpressionPerLocalShownField()
    {
        $columns = $this->call($this->controller, 'getCamposShowMine');
        $select  = $this->call($this->controller, 'getSelect', [$columns]);

        $this->assertCount(count($columns), $select);
        $this->assertSame(
            ['name', 'CONCAT(name, id)'],
            array_map(function ($expression) {
                return $expression->getValue(\Illuminate\Database\Capsule\Manager::connection()->getQueryGrammar());
            }, $select)
        );
    }

    /*==================== getCamposShowMine ====================*/

    public function testGetCamposShowMineExcludesRelationsAndHiddenFields()
    {
        $fields = array_column($this->call($this->controller, 'getCamposShowMine'), 'campo');

        $this->assertSame(['name', 'CONCAT(name, id)'], $fields);
    }

    /*==================== getCamposShowForeign ====================*/

    public function testGetCamposShowForeignGroupsColumnsByRelation()
    {
        $foreigns = $this->call($this->controller, 'getCamposShowForeign');

        $this->assertSame(['country'], array_keys($foreigns));
        $this->assertSame('name', $foreigns['country'][0][0]);
    }

    /*==================== getCamposOrden ====================*/

    public function testGetCamposOrdenKeepsColumnOrderAndAppendsTheUniqueId()
    {
        $order = $this->call($this->controller, 'getCamposOrden');

        $this->assertSame(
            ['name', 'country.name', 'CONCAT(name, id)', '___id___'],
            $order
        );
    }

    /*==================== stripAlias ====================*/

    public function testStripAliasRemovesTheAliasAndKeepsPlainColumns()
    {
        $this->assertSame('country.name', $this->call($this->controller, 'stripAlias', ['country.name AS country_name']));
        $this->assertSame('name', $this->call($this->controller, 'stripAlias', ['  name  ']));
        $this->assertSame('CONCAT(a, b)', $this->call($this->controller, 'stripAlias', ['CONCAT(a, b) as composed']));
    }

    /*==================== qualifyRelatedColumn ====================*/

    public function testQualifyRelatedColumnOnlyPrefixesPlainIdentifiers()
    {
        $model = new \Csgt\Crud\Tests\Fixtures\Country;

        $this->assertSame('countries.name', $this->call($this->controller, 'qualifyRelatedColumn', [$model, 'name']));
        $this->assertSame('CONCAT(a, b)', $this->call($this->controller, 'qualifyRelatedColumn', [$model, 'CONCAT(a, b)']));
        $this->assertSame('other.name', $this->call($this->controller, 'qualifyRelatedColumn', [$model, 'other.name']));
    }

    /*==================== applyOrderToQuery ====================*/

    public function testApplyOrderToQueryOrdersALocalColumn()
    {
        $query = $this->query();
        $this->call($this->controller, 'applyOrderToQuery', [$query, 'name', 'desc']);

        $this->assertStringContainsString('order by "name" desc', $query->toSql());
    }

    public function testApplyOrderToQueryOrdersARawExpressionWithoutQuotingIt()
    {
        $query = $this->query();
        $this->call($this->controller, 'applyOrderToQuery', [$query, 'CONCAT(name, id)', 'asc']);

        $this->assertStringContainsString('order by CONCAT(name, id) asc', $query->toSql());
    }

    public function testApplyOrderToQueryOrdersARelationWithACorrelatedSubquery()
    {
        // Only reachable when the installed Eloquent has getRelationExistenceQuery
        // (Laravel >= 5.5). Older Eloquent falls back to ordering by the primary
        // key instead, which is not exercised here since our dev dependency is
        // a modern Eloquent and always takes this path.
        $query = $this->query();
        $this->call($this->controller, 'applyOrderToQuery', [$query, 'country.name', 'desc']);

        $sql = $query->toSql();

        $this->assertStringContainsString('order by (select countries.name from "countries"', $sql);
        $this->assertStringContainsString('"clients"."country_id" = "countries"."id"', $sql);
        $this->assertStringContainsString('limit 1) desc', $sql);
    }

    public function testApplyOrderToQueryFallsBackToAPlainOrderForAnUnknownRelation()
    {
        $query = $this->query();
        $this->call($this->controller, 'applyOrderToQuery', [$query, 'missing.name', 'asc']);

        $this->assertStringContainsString('order by "missing"."name" asc', $query->toSql());
    }

    /*==================== applySearchToQuery ====================*/

    public function testApplySearchToQueryFiltersOverTheGivenSearchableColumns()
    {
        $columns = $this->call($this->controller, 'getCamposShowMine');
        $query   = $this->query();

        $this->call($this->controller, 'applySearchToQuery', [$query, 'ACME', $columns]);

        $sql = $query->toSql();

        $this->assertStringContainsString('LOWER(name) LIKE ?', $sql);
        $this->assertStringContainsString('LOWER(CONCAT(name, id)) LIKE ?', $sql);
        $this->assertSame(['%acme%', '%acme%'], $query->getBindings());
    }

    public function testApplySearchToQuerySkipsColumnsThatAreNotSearchable()
    {
        $columns = [
            ['campo' => 'name', 'searchable' => true],
            ['campo' => 'secret', 'searchable' => false],
        ];
        $query = $this->query();

        $this->call($this->controller, 'applySearchToQuery', [$query, 'acme', $columns]);

        $sql = $query->toSql();

        $this->assertStringContainsString('LOWER(name) LIKE ?', $sql);
        $this->assertStringNotContainsString('secret', $sql);
        $this->assertSame(['%acme%'], $query->getBindings());
    }

    public function testApplySearchToQuerySkipsTheUniqueIdColumn()
    {
        $columns = [
            ['campo' => '___id___', 'searchable' => true],
        ];
        $query = $this->query();

        $this->call($this->controller, 'applySearchToQuery', [$query, 'acme', $columns]);

        $this->assertSame([], $query->getBindings());
    }

    /*==================== downLevel ====================*/

    public function testDownLevelRemovesTheLastSegmentOfThePath()
    {
        $this->assertSame('clients', $this->call($this->controller, 'downLevel', ['clients/5']));
        $this->assertSame('', $this->call($this->controller, 'downLevel', ['clients']));
    }

    /*==================== setters ====================*/

    public function testSetCampoAndSetTituloStoreFields()
    {
        $this->assertSame('Clients', $this->read($this->controller, 'titulo'));
        $this->assertCount(4, $this->read($this->controller, 'campos'));
    }
}
