<?php
namespace Csgt\Crud\Tests;

use Csgt\Crud\Tests\Fixtures\ClientsController;

/**
 * One group of tests per method of CrudController that shapes the listing
 * query on this branch. `fields` here is a Collection (not a plain array like
 * on 5.9/6.0), which changes several of the method shapes below.
 */
class CrudControllerTest extends TestCase
{
    protected $controller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->controller = new ClientsController;
    }

    /*==================== getShowFields ====================*/

    public function testGetShowFieldsExcludesHiddenFields()
    {
        $fields = $this->call($this->controller, 'getShowFields')->pluck('field')->values()->toArray();

        $this->assertSame(
            ['name', 'country.name AS country_name', 'tags', 'CONCAT(name, id) AS composed'],
            $fields
        );
    }

    /*==================== getLocalShowFields ====================*/

    public function testGetLocalShowFieldsExcludesRelationsAndMultiFields()
    {
        $fields = $this->call($this->controller, 'getLocalShowFields')->pluck('field')->values()->toArray();

        $this->assertSame(['name', 'CONCAT(name, id) AS composed'], $fields);
    }

    /*==================== getForeignShowFields ====================*/

    public function testGetForeignShowFieldsGroupsColumnsByRelation()
    {
        $foreigns = $this->call($this->controller, 'getForeignShowFields');

        // data() iterates this as `foreach ($foreigns as $relation => $fields)`
        // and then calls `$model->{$relation}()`, so the key has to be the
        // relation name and each value a list of column lists.
        $this->assertSame(['country'], array_keys($foreigns));
        $this->assertSame('name AS country_name', $foreigns['country'][0][0]);
    }

    /*==================== getMultiFields ====================*/

    public function testGetMultiFieldsReturnsTheNamesOfTheMultiRelations()
    {
        $this->assertSame(['tags'], $this->call($this->controller, 'getMultiFields')->values()->toArray());
    }

    /*==================== getLocalEditFields ====================*/

    public function testGetLocalEditFieldsExcludesRelationsButKeepsMultiFields()
    {
        $fields = $this->call($this->controller, 'getLocalEditFields')->pluck('field')->values()->toArray();

        $this->assertSame(['name', 'tags', 'CONCAT(name, id) AS composed', 'secret'], $fields);
    }

    /*==================== getFieldOrder ====================*/

    public function testGetFieldOrderKeepsColumnOrderAndAppendsTheUniqueId()
    {
        $order = $this->call($this->controller, 'getFieldOrder')->toArray();

        $this->assertSame(
            ['name', 'country.name AS country_name', 'tags', 'CONCAT(name, id) AS composed', '___id___'],
            $order
        );
    }

    /*==================== getMultiFields ====================*/

    public function testGetMultiFieldsReturnsTheFieldNamesOfTypeMulti()
    {
        $this->assertSame(['tags'], $this->call($this->controller, 'getMultiFields')->values()->toArray());
    }

    /*==================== getSelect ====================*/

    public function testGetSelectReturnsOneExpressionPerLocalShownField()
    {
        $grammar = \Illuminate\Database\Capsule\Manager::connection()->getQueryGrammar();

        $select = $this->call($this->controller, 'getSelect', [
            $this->call($this->controller, 'getLocalShowFields'),
        ]);

        $this->assertSame(
            ['name', 'CONCAT(name, id) AS composed'],
            $select->map(function ($expression) use ($grammar) {
                return $expression->getValue($grammar);
            })->values()->toArray()
        );
    }

    /*==================== downLevel ====================*/

    public function testDownLevelRemovesTheLastSegmentOfThePath()
    {
        $this->assertSame('clients', $this->call($this->controller, 'downLevel', ['clients/5']));
        $this->assertSame('', $this->call($this->controller, 'downLevel', ['clients']));
    }

    /*==================== emptyState ====================*/

    public function testEmptyStateFillsDefaultsForLocalEditableFields()
    {
        $state = $this->call($this->controller, 'emptyState');

        $this->assertSame(0, $state['id']);
        $this->assertNull($state['name']);
        $this->assertNull($state['secret']);
        $this->assertArrayNotHasKey('country.name AS country_name', $state);
    }

    public function testEmptyStateResetsMultiFieldsToAnEmptyArray()
    {
        $state = $this->call($this->controller, 'emptyState');

        $this->assertArrayHasKey('tags', $state);
        $this->assertSame([], $state['tags']);
    }

    /*==================== setters ====================*/

    public function testSetFieldRegistersEveryDeclaredField()
    {
        $this->assertSame('Clients', $this->controller->getTitle());
        $this->assertCount(5, $this->read($this->controller, 'fields'));
    }
}
