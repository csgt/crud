<?php
namespace Csgt\Crud\Tests;

use Csgt\Crud\Tests\Fixtures\Client;
use Csgt\Crud\Tests\Fixtures\ClientsController;

/**
 * One group of tests per method of CrudController that shapes the listing
 * query. Everything is asserted on the generated SQL and bindings.
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
            ['name', 'CONCAT(name, id) AS composed'],
            array_map(function ($expression) {
                return $expression->getValue(\Illuminate\Database\Capsule\Manager::connection()->getQueryGrammar());
            }, $select)
        );
    }

    /*==================== getCamposShowMine ====================*/

    public function testGetCamposShowMineExcludesRelationsMultiAndHiddenFields()
    {
        $fields = array_column($this->call($this->controller, 'getCamposShowMine'), 'campo');

        $this->assertSame(['name', 'CONCAT(name, id) AS composed'], $fields);
    }

    /*==================== getCamposShowForeign ====================*/

    public function testGetCamposShowForeignGroupsColumnsByRelation()
    {
        $foreigns = $this->call($this->controller, 'getCamposShowForeign');

        $this->assertSame(['country'], array_keys($foreigns));
        $this->assertSame('name AS country_name', trim($foreigns['country'][0][0]));
    }

    /*==================== getCamposShowMulti ====================*/

    public function testGetCamposShowMultiReturnsOnlyMultiFields()
    {
        $multi = array_column($this->call($this->controller, 'getCamposShowMulti'), 'campo');

        $this->assertSame(['tags'], $multi);
    }

    /*==================== getCamposOrden ====================*/

    public function testGetCamposOrdenKeepsColumnOrderAndAppendsTheUniqueId()
    {
        $order = $this->call($this->controller, 'getCamposOrden');

        $this->assertSame(
            ['name', 'country.name AS country_name', 'tags', 'CONCAT(name, id) AS composed', '___id___'],
            $order
        );
    }

    /*==================== getFilterColumns ====================*/

    public function testGetFilterColumnsExposesIndexAndPlainLabel()
    {
        $columns = $this->call($this->controller, 'getFilterColumns');

        $this->assertSame([0, 1, 2, 3], array_column($columns, 'index'));
        $this->assertSame(['Name', 'Country', 'Tags', 'Composed'], array_column($columns, 'label'));
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
        $this->call($this->controller, 'applyOrderToQuery', [$query, 'CONCAT(name, id) AS composed', 'asc']);

        $this->assertStringContainsString('order by CONCAT(name, id) asc', $query->toSql());
    }

    public function testApplyOrderToQueryOrdersARelationWithACorrelatedSubquery()
    {
        $query = $this->query();
        $this->call($this->controller, 'applyOrderToQuery', [$query, 'country.name AS country_name', 'desc']);

        $sql = $query->toSql();

        $this->assertStringContainsString('order by (select countries.name from "countries"', $sql);
        $this->assertStringContainsString('"clients"."country_id" = "countries"."id"', $sql);
        $this->assertStringContainsString('limit 1) desc', $sql);
        $this->assertStringNotContainsString('country_name', $sql);
    }

    public function testApplyOrderToQueryFallsBackToAPlainOrderForAnUnknownRelation()
    {
        $query = $this->query();
        $this->call($this->controller, 'applyOrderToQuery', [$query, 'missing.name', 'asc']);

        $this->assertStringContainsString('order by "missing"."name" asc', $query->toSql());
    }

    /*==================== applyColumnFilter ====================*/

    public function testApplyColumnFilterMatchesALocalColumn()
    {
        $columns = $this->call($this->controller, 'getCamposShow');
        $query   = $this->query();

        $this->call($this->controller, 'applyColumnFilter', [$query, $columns[0], '%acme%']);

        $this->assertStringContainsString('where "name" like ?', $query->toSql());
        $this->assertSame(['%acme%'], $query->getBindings());
    }

    public function testApplyColumnFilterMatchesARelationColumnThroughWhereHas()
    {
        $columns = $this->call($this->controller, 'getCamposShow');
        $query   = $this->query();

        $this->call($this->controller, 'applyColumnFilter', [$query, $columns[1], '%mex%']);

        $sql = $query->toSql();

        $this->assertStringContainsString('exists (select * from "countries"', $sql);
        $this->assertStringContainsString('"name" like ?', $sql);
        $this->assertStringNotContainsString('country_name', $sql);
        $this->assertSame(['%mex%'], $query->getBindings());
    }

    public function testApplyColumnFilterMatchesAMultiFieldThroughItsRelation()
    {
        $columns = $this->call($this->controller, 'getCamposShow');
        $query   = $this->query();

        $this->call($this->controller, 'applyColumnFilter', [$query, $columns[2], '%vip%']);

        $sql = $query->toSql();

        $this->assertStringContainsString('exists (select * from "tags"', $sql);
        $this->assertStringContainsString('"client_tag"', $sql);
        $this->assertSame(['%vip%'], $query->getBindings());
    }

    public function testApplyColumnFilterUsesWhereRawForExpressions()
    {
        $columns = $this->call($this->controller, 'getCamposShow');
        $query   = $this->query();

        $this->call($this->controller, 'applyColumnFilter', [$query, $columns[3], '%abc%']);

        $this->assertStringContainsString('CONCAT(name, id) LIKE ?', $query->toSql());
        $this->assertStringNotContainsString('composed', $query->toSql());
        $this->assertSame(['%abc%'], $query->getBindings());
    }

    /*==================== applyFiltersToQuery ====================*/

    public function testApplyFiltersToQueryAppliesEveryValidFilter()
    {
        $query = $this->query();

        $applied = $this->call($this->controller, 'applyFiltersToQuery', [$query, [
            ['column' => 0, 'value' => 'acme'],
            ['column' => 1, 'value' => 'mex'],
        ]]);

        $this->assertTrue($applied);
        $this->assertSame(['%acme%', '%mex%'], $query->getBindings());
    }

    public function testApplyFiltersToQueryIgnoresEmptyUnknownAndMalformedFilters()
    {
        $query = $this->query();

        $applied = $this->call($this->controller, 'applyFiltersToQuery', [$query, [
            ['column' => 0, 'value' => '   '],
            ['column' => 99, 'value' => 'x'],
            ['column' => 'not-an-index', 'value' => 'x'],
            ['value' => 'no column'],
            'not an array',
        ]]);

        $this->assertFalse($applied);
        $this->assertSame([], $query->getBindings());
        $this->assertStringNotContainsString('where', $query->toSql());
    }

    public function testApplyFiltersToQueryIgnoresANonArrayPayload()
    {
        $query = $this->query();

        $this->assertFalse($this->call($this->controller, 'applyFiltersToQuery', [$query, 'nope']));
        $this->assertSame([], $query->getBindings());
    }

    /*==================== downLevel ====================*/

    public function testDownLevelRemovesTheLastSegmentOfThePath()
    {
        $this->assertSame('clients', $this->call($this->controller, 'downLevel', ['clients/5']));
        $this->assertSame('', $this->call($this->controller, 'downLevel', ['clients']));
    }

    /*==================== setters ====================*/

    public function testSetCampoRejectsUnknownKeys()
    {
        $this->assertSame('Clients', $this->controller->getTitulo());
        $this->assertCount(5, $this->read($this->controller, 'campos'));
    }
}
