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
        $columns = $this->call($this->controller, 'getLocalShowFields');
        $select  = $this->call($this->controller, 'getSelect', [$columns]);

        $this->assertCount(count($columns), $select);
        $this->assertSame(
            ['name', 'CONCAT(name, id) AS composed'],
            array_map(function ($expression) {
                return $expression->getValue(\Illuminate\Database\Capsule\Manager::connection()->getQueryGrammar());
            }, $select)
        );
    }

    /*==================== getLocalShowFields ====================*/

    public function testGetLocalShowFieldsExcludesRelationsMultiAndHiddenFields()
    {
        $fields = array_column($this->call($this->controller, 'getLocalShowFields'), 'field');

        $this->assertSame(['name', 'CONCAT(name, id) AS composed'], $fields);
    }

    /*==================== getForeignShowFields ====================*/

    public function testGetForeignShowFieldsGroupsColumnsByRelation()
    {
        $foreigns = $this->call($this->controller, 'getForeignShowFields');

        $this->assertSame(['country'], array_keys($foreigns));
        $this->assertSame('name AS country_name', trim($foreigns['country'][0][0]));
    }

    /*==================== getShowMultipleFields ====================*/

    public function testGetShowMultipleFieldsReturnsOnlyMultiFields()
    {
        $multi = array_column($this->call($this->controller, 'getShowMultipleFields'), 'field');

        $this->assertSame(['tags'], $multi);
    }

    /*==================== getFieldOrder ====================*/

    public function testGetFieldOrderKeepsColumnOrderAndAppendsTheUniqueId()
    {
        $order = $this->call($this->controller, 'getFieldOrder');

        $this->assertSame(
            ['name', 'country.name AS country_name', 'tags', 'CONCAT(name, id) AS composed', '___id___'],
            $order
        );
    }

    /*==================== getFilterColumns ====================*/

    public function testGetFilterColumnsExposesIndexPlainLabelAndType()
    {
        $this->controller->setField([
            'field' => 'created_at',
            'name' => 'Created at',
            'type' => 'date',
        ]);
        $columns = $this->call($this->controller, 'getFilterColumns');

        $this->assertSame([0, 1, 2, 3, 4], array_column($columns, 'index'));
        $this->assertSame(['Name', 'Country', 'Tags', 'Composed', 'Created at'], array_column($columns, 'label'));
        $this->assertSame(['string', 'string', 'multi', 'string', 'date'], array_column($columns, 'type'));
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

    /*==================== resolveOrderDirection ====================*/

    public function testResolveOrderDirectionDefaultsToAscForMissingOrNullDir()
    {
        // $order['dir'] comes straight from the client; a malformed request can
        // omit it. strtolower(null) is deprecated since PHP 8.1, so this must
        // not pass null through, and must keep falling back to "asc".
        $this->assertSame('asc', $this->call($this->controller, 'resolveOrderDirection', [[]]));
        $this->assertSame('asc', $this->call($this->controller, 'resolveOrderDirection', [['dir' => null]]));
    }

    public function testResolveOrderDirectionRecognisesDescCaseInsensitively()
    {
        $this->assertSame('desc', $this->call($this->controller, 'resolveOrderDirection', [['dir' => 'desc']]));
        $this->assertSame('desc', $this->call($this->controller, 'resolveOrderDirection', [['dir' => 'DESC']]));
        $this->assertSame('asc', $this->call($this->controller, 'resolveOrderDirection', [['dir' => 'asc']]));
    }

    public function testResolveOrderDirectionFeedsApplyOrderToQueryTheSameAsBefore()
    {
        $direction = $this->call($this->controller, 'resolveOrderDirection', [['dir' => 'desc']]);
        $query     = $this->query();
        $this->call($this->controller, 'applyOrderToQuery', [$query, 'name', $direction]);

        $this->assertStringContainsString('order by "name" desc', $query->toSql());
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
        $columns = $this->call($this->controller, 'getShowFields');
        $query   = $this->query();

        $this->call($this->controller, 'applyColumnFilter', [$query, $columns[0], '%acme%']);

        $this->assertStringContainsString('where "name" like ?', $query->toSql());
        $this->assertSame(['%acme%'], $query->getBindings());
    }

    public function testApplyColumnFilterMatchesARelationColumnThroughWhereHas()
    {
        $columns = $this->call($this->controller, 'getShowFields');
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
        $columns = $this->call($this->controller, 'getShowFields');
        $query   = $this->query();

        $this->call($this->controller, 'applyColumnFilter', [$query, $columns[2], '%vip%']);

        $sql = $query->toSql();

        $this->assertStringContainsString('exists (select * from "tags"', $sql);
        $this->assertStringContainsString('"client_tag"', $sql);
        $this->assertSame(['%vip%'], $query->getBindings());
    }

    public function testApplyColumnFilterUsesWhereRawForExpressions()
    {
        $columns = $this->call($this->controller, 'getShowFields');
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

    /*==================== filter options ====================*/

    /**
     * Declara las dos columnas de lista cerrada en el controlador de este test.
     * No van en el fixture compartido porque correrian los indices de columna
     * que usan los demas tests de filtro.
     */
    private function withClosedListColumns()
    {
        $this->controller->setField([
            'field'     => 'status',
            'name'      => 'Status',
            'type'      => 'enum',
            'enumarray' => ['a' => 'Active', 'i' => 'Inactive'],
        ]);
        $this->controller->setField(['field' => 'active', 'name' => 'Active', 'type' => 'bool']);
    }

    public function testGetFilterColumnsExposesTheOptionsOfClosedListColumns()
    {
        $this->withClosedListColumns();

        $byLabel = [];
        foreach ($this->call($this->controller, 'getFilterColumns') as $column) {
            $byLabel[$column['label']] = $column;
        }

        $this->assertSame(
            [['value' => 'a', 'label' => 'Active'], ['value' => 'i', 'label' => 'Inactive']],
            $byLabel['Status']['options']
        );
        $this->assertSame(
            [['value' => '1', 'label' => 'Si'], ['value' => '0', 'label' => 'No']],
            $byLabel['Active']['options']
        );

        // Una columna de texto libre no ofrece lista, y su filtro sigue siendo
        // un campo de escritura.
        $this->assertSame([], $byLabel['Name']['options']);
    }

    public function testApplyFiltersToQueryMatchesAClosedListColumnExactly()
    {
        // Con LIKE '%a%' un enum de claves "a" e "i" devolveria tambien las filas
        // cuyo valor solo contiene la letra, por eso la igualdad importa.
        $this->withClosedListColumns();
        $query = $this->query();

        $applied = $this->call($this->controller, 'applyFiltersToQuery', [$query, [
            ['column' => 4, 'value' => 'a'],
        ]]);

        $this->assertTrue($applied);
        $this->assertStringContainsString('where "status" = ?', $query->toSql());
        $this->assertStringNotContainsString('like', $query->toSql());
        $this->assertSame(['a'], $query->getBindings());
    }

    public function testApplyFiltersToQueryMatchesABooleanColumnExactly()
    {
        $this->withClosedListColumns();
        $query = $this->query();

        $this->call($this->controller, 'applyFiltersToQuery', [$query, [
            ['column' => 5, 'value' => '0'],
        ]]);

        $this->assertStringContainsString('where "active" = ?', $query->toSql());
        $this->assertSame(['0'], $query->getBindings());
    }

    public function testApplyFiltersToQueryStillMatchesFreeTextColumnsWithLike()
    {
        $this->withClosedListColumns();
        $query = $this->query();

        $this->call($this->controller, 'applyFiltersToQuery', [$query, [
            ['column' => 0, 'value' => 'acme'],
        ]]);

        $this->assertStringContainsString('where "name" like ?', $query->toSql());
        $this->assertSame(['%acme%'], $query->getBindings());
    }

    /*==================== resolveLength ====================*/

    public function testResolveLengthUsesTheRawRequestLengthWhenNoMaximumIsConfigured()
    {
        // csgtcrud.max_page_length is null by default: this preserves the
        // historical behaviour of trusting the client's "length" as-is.
        $this->assertSame(9999999, $this->call($this->controller, 'resolveLength', [9999999]));
        $this->assertSame(0, $this->call($this->controller, 'resolveLength', [0]));
    }

    public function testResolveLengthClampsToTheConfiguredMaximum()
    {
        TestConfig::set('csgtcrud.max_page_length', 100);

        $length = $this->call($this->controller, 'resolveLength', [9999999]);

        $this->assertSame(100, $length);
        $this->assertStringContainsString('limit 100', $this->query()->limit($length)->toSql());
    }

    public function testResolveLengthFallsBackToPerPageForAZeroOrNegativeLengthWhenAMaximumIsConfigured()
    {
        TestConfig::set('csgtcrud.max_page_length', 100);

        $this->assertSame(50, $this->call($this->controller, 'resolveLength', [0]));
        $this->assertSame(50, $this->call($this->controller, 'resolveLength', [-5]));
    }

    /*==================== setField / enum guard ====================*/

    public function testSetFieldNormalizesAMissingNullOrNonArrayEnumarrayWithoutThrowing()
    {
        // count() on a non-array is a fatal TypeError on PHP 8, so a bad
        // enumarray must never reach it; it is normalized to [] instead of
        // aborting, since an enum with no options historically just rendered
        // an empty <select> and the application kept working.
        $cases = [
            'missing'   => ['field' => 'status_missing', 'type' => 'enum'],
            'null'      => ['field' => 'status_null', 'type' => 'enum', 'enumarray' => null],
            'non-array' => ['field' => 'status_string', 'type' => 'enum', 'enumarray' => 'not-an-array'],
        ];

        foreach ($cases as $case) {
            $this->controller->setField($case);
        }

        $fields = $this->read($this->controller, 'fields');
        $byField = array_column($fields, 'enumarray', 'field');

        $this->assertSame([], $byField['status_missing']);
        $this->assertSame([], $byField['status_null']);
        $this->assertSame([], $byField['status_string']);
    }

    public function testSetFieldDefaultsAMissingFiledisktoAnEmptyString()
    {
        // The securefile validation compares filedisk against '', so defaulting
        // it to boolean true made that check unreachable: true == '' is false.
        // A securefile declared without a disk was accepted and then failed at
        // render time in Storage::disk(true), which resolves to the config key
        // filesystems.disks.1.
        $this->controller->setField(['field' => 'contract', 'type' => 'string']);

        $fields = $this->read($this->controller, 'fields');
        $byField = array_column($fields, 'filedisk', 'field');

        $this->assertSame('', $byField['contract']);
    }

    public function testSetFieldKeepsAnExplicitFiledisk()
    {
        $this->controller->setField([
            'field'    => 'contract_secure',
            'type'     => 'securefile',
            'filepath' => 'contracts',
            'filedisk' => 's3',
        ]);

        $fields = $this->read($this->controller, 'fields');
        $byField = array_column($fields, 'filedisk', 'field');

        $this->assertSame('s3', $byField['contract_secure']);
    }

    /*==================== downLevel ====================*/

    public function testDownLevelRemovesTheLastSegmentOfThePath()
    {
        $this->assertSame('clients', $this->call($this->controller, 'downLevel', ['clients/5']));
        $this->assertSame('', $this->call($this->controller, 'downLevel', ['clients']));
    }

    /*==================== setters ====================*/

    public function testSetFieldAcceptsTheTimeType()
    {
        // index.blade.php renders "time" alongside "date" and "datetime", so
        // setField() has to accept it or the column can never be declared.
        $fields = $this->read($this->controller, 'fields');
        $time = array_values(array_filter($fields, function ($field) {
            return $field['field'] === 'opens_at';
        }));

        $this->assertCount(1, $time);
        $this->assertSame('time', $time[0]['type']);
    }

    public function testSetFieldRejectsUnknownKeys()
    {
        $this->assertSame('Clients', $this->controller->getTitle());
        $this->assertCount(6, $this->read($this->controller, 'fields'));
    }
}
