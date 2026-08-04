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

        // BUG: the collection returned keeps the *original field index* as its
        // key (1, the position of the country field among the 5 declared
        // fields) instead of being keyed by relation name. Callers that do
        // `foreach ($foreigns as $relation => $fields)` (as data() does) get
        // the index as $relation, not "country" — so it is unusable as-is to
        // build `$model->{$relation}()`.
        $this->assertSame([1], $foreigns->keys()->toArray());
        $this->assertSame(['country' => 'name AS country_name'], $foreigns->get(1));
    }

    /*==================== getShowMultipleFields ====================*/

    public function testGetShowMultipleFieldsIsBrokenOnACollectionOfFields()
    {
        // BUG: getShowMultipleFields() runs array_filter() directly on
        // $this->fields, but $this->fields is an Illuminate\Support\Collection
        // (set up by setField()), not a plain array. This throws for any
        // controller that has declared at least one field.
        $this->expectException(\TypeError::class);

        $this->call($this->controller, 'getShowMultipleFields');
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

    public function testGetSelectIsBrokenBecauseItAccessesFieldsAsObjects()
    {
        // BUG: getSelect() maps with `DB::raw($field->field)`, treating each
        // field as an object with a public "field" property. But each field is
        // a plain Collection (built with collect($arr) in setField()), and
        // Collection only exposes array access, so ->field throws.
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Property [field] does not exist on this collection instance.');

        $this->call($this->controller, 'getSelect', [$this->call($this->controller, 'getLocalShowFields')]);
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

    public function testEmptyStateDoesNotResetMultiFieldsToAnEmptyArray()
    {
        // BUG: the each() closure `use ($ret)` captures $ret by value, not by
        // reference, so the assignment `$ret[$multi] = []` mutates a copy and
        // is silently lost. The multi field keeps whatever getLocalEditFields()
        // put there (its "default", here null) instead of becoming [].
        $state = $this->call($this->controller, 'emptyState');

        $this->assertArrayHasKey('tags', $state);
        $this->assertNull($state['tags']);
    }

    /*==================== setters ====================*/

    public function testSetFieldRegistersEveryDeclaredField()
    {
        $this->assertSame('Clients', $this->controller->getTitle());
        $this->assertCount(5, $this->read($this->controller, 'fields'));
    }
}
