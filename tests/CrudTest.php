<?php
namespace Csgt\Crud\Tests;

use Csgt\Crud\Crud;

/**
 * Crud is a static class with no CrudController equivalent on this branch:
 * index/create/store/destroy/getData are driven by the Input, Request,
 * Session, View, Redirect and DB facades and need a booted Laravel
 * application, so they are out of scope here.
 *
 * This suite covers what is genuinely testable without an app: the pure
 * getUrl() helper and every setter that shapes the static state
 * (fields, wheres, joins, orders, buttons, slug, permissions...), including
 * the defaulting and branching logic inside setCampo().
 */
class CrudTest extends TestCase
{
    /*==================== getUrl ====================*/

    public function testGetUrlDropsTheLastSegment()
    {
        $this->assertSame('clients', $this->call(Crud::class, 'getUrl', ['clients/5']));
        $this->assertSame('', $this->call(Crud::class, 'getUrl', ['clients']));
    }

    public function testGetUrlDropsTwoSegmentsWhenEditing()
    {
        $this->assertSame('clients', $this->call(Crud::class, 'getUrl', ['clients/5/edit', true]));
    }

    /*==================== setTabla / setTablaId / setTitulo ====================*/

    public function testSetTablaSetTablaIdSetTituloStoreTheirValue()
    {
        Crud::setTabla('clients');
        Crud::setTablaId('id');
        Crud::setTitulo('Clients');

        $this->assertSame('clients', $this->read(Crud::class, 'tabla'));
        $this->assertSame('id', $this->read(Crud::class, 'tablaId'));
        $this->assertSame('Clients', $this->read(Crud::class, 'titulo'));
    }

    /*==================== boolean toggles ====================*/

    public function testBooleanTogglesStoreTheirValue()
    {
        Crud::setExport(false);
        Crud::setSearch(false);
        Crud::setStateSave(false);
        Crud::setSoftDelete(true);
        Crud::setResponsive(false);

        $this->assertFalse($this->read(Crud::class, 'showExport'));
        $this->assertFalse($this->read(Crud::class, 'showSearch'));
        $this->assertFalse($this->read(Crud::class, 'stateSave'));
        $this->assertTrue(Crud::getSoftDelete());
        $this->assertFalse($this->read(Crud::class, 'responsive'));
    }

    public function testSetResponsiveIsCallableStaticallyLikeEveryOtherSetter()
    {
        // It writes a static property, so it has to be reachable the same way
        // the rest of the API is: Crud::setResponsive(...).
        $method = new \ReflectionMethod(Crud::class, 'setResponsive');

        $this->assertTrue($method->isStatic());
    }

    /*==================== setPerPage / setTemplate / setPermisos ====================*/

    public function testSetPerPageSetTemplateSetPermisosStoreTheirValue()
    {
        Crud::setPerPage(10);
        Crud::setTemplate('custom/template');
        Crud::setPermisos(['add' => true, 'edit' => false, 'delete' => true]);

        $this->assertSame(10, $this->read(Crud::class, 'perPage'));
        $this->assertSame('custom/template', $this->read(Crud::class, 'template'));
        $this->assertSame(['add' => true, 'edit' => false, 'delete' => true], $this->read(Crud::class, 'permisos'));
    }

    /*==================== setSlug ====================*/

    public function testSetSlugAccumulatesColumnsAndOverridesFieldAndSeparator()
    {
        Crud::setSlug(['columnas' => ['first_name', 'last_name']]);
        Crud::setSlug(['campo' => 'url_slug']);
        Crud::setSlug(['separator' => '_']);

        $this->assertSame(['first_name', 'last_name'], $this->read(Crud::class, 'camposSlug'));
        $this->assertSame('url_slug', $this->read(Crud::class, 'colSlug'));
        $this->assertSame('_', $this->read(Crud::class, 'slugSeparator'));
    }

    /*==================== setWhere / setWhereIn / setWhereRaw / setLeftJoin / setGroupBy ====================*/

    public function testSetWhereDefaultsTheOperatorToEqualsWhenOnlyTwoArgumentsAreGiven()
    {
        Crud::setWhere('status', 'active');

        $this->assertSame([['columna' => 'status', 'operador' => '=', 'valor' => 'active']], $this->read(Crud::class, 'wheres'));
    }

    public function testSetWhereKeepsTheOperatorWhenThreeArgumentsAreGiven()
    {
        Crud::setWhere('age', '>', 18);

        $this->assertSame([['columna' => 'age', 'operador' => '>', 'valor' => 18]], $this->read(Crud::class, 'wheres'));
    }

    public function testSetWhereInSetWhereRawSetLeftJoinSetGroupByAccumulate()
    {
        Crud::setWhereIn('status', ['active', 'pending']);
        Crud::setWhereRaw('deleted_at IS NULL');
        Crud::setLeftJoin('countries', 'clients.country_id', '=', 'countries.id');
        Crud::setGroupBy('country_id');

        $this->assertSame([['columna' => 'status', 'valor' => ['active', 'pending']]], $this->read(Crud::class, 'wheresIn'));
        $this->assertSame(['deleted_at IS NULL'], $this->read(Crud::class, 'wheresRaw'));
        $this->assertSame([['tabla' => 'countries', 'col1' => 'clients.country_id', 'operador' => '=', 'col2' => 'countries.id']], $this->read(Crud::class, 'leftJoins'));
        $this->assertSame(['country_id'], $this->read(Crud::class, 'groups'));
    }

    /*==================== setOrderBy ====================*/

    public function testSetOrderByDefaultsToColumnZeroAscending()
    {
        Crud::setOrderBy([]);

        $this->assertSame([0 => 'asc'], $this->read(Crud::class, 'orders'));
    }

    public function testSetOrderByStoresTheGivenColumnAndDirection()
    {
        Crud::setOrderBy(['columna' => 2, 'direccion' => 'desc']);

        $this->assertSame([2 => 'desc'], $this->read(Crud::class, 'orders'));
    }

    /*==================== setHidden ====================*/

    public function testSetHiddenAppendsTheFieldAndItsValue()
    {
        Crud::setHidden(['campo' => 'company_id', 'valor' => 7]);

        $this->assertSame([['campo' => 'company_id', 'valor' => 7]], $this->read(Crud::class, 'camposHidden'));
    }

    /*==================== setBotonExtra ====================*/

    public function testSetBotonExtraAppliesItsDefaults()
    {
        Crud::setBotonExtra(['url' => '/export']);

        $this->assertSame([[
            'url'            => '/export',
            'titulo'         => '',
            'icon'           => 'glyphicon glyphicon-star',
            'class'          => 'default',
            'target'         => '',
            'confirm'        => false,
            'confirmmessage' => '¿Estas seguro?',
        ]], $this->read(Crud::class, 'botonesExtra'));
    }

    public function testSetBotonExtraKeepsTheGivenValues()
    {
        Crud::setBotonExtra(['url' => '/delete-all', 'titulo' => 'Delete all', 'confirm' => true]);

        $boton = $this->read(Crud::class, 'botonesExtra')[0];

        $this->assertSame('/delete-all', $boton['url']);
        $this->assertSame('Delete all', $boton['titulo']);
        $this->assertTrue($boton['confirm']);
    }

    /*==================== setCampo ====================*/

    public function testSetCampoBuildsAPlainField()
    {
        Crud::setCampo(['campo' => 'name']);

        $campo = $this->read(Crud::class, 'camposShow')[0];

        $this->assertSame('name', $campo['campo']);
        $this->assertSame('name', $campo['campoReal']);
        $this->assertSame('name', $campo['alias']);
        $this->assertSame('Name', $campo['nombre']);
        $this->assertTrue($campo['searchable']);
        $this->assertTrue($campo['show']);
        $this->assertTrue($campo['editable']);
        $this->assertSame($campo, $this->read(Crud::class, 'camposEdit')[0]);
    }

    public function testSetCampoWithADottedFieldDerivesTheRealColumnAndAlias()
    {
        Crud::setCampo(['campo' => 'country.name']);

        $campo = $this->read(Crud::class, 'camposShow')[0];

        $this->assertSame('name', $campo['campoReal']);
        $this->assertSame('country__name', $campo['alias']);
    }

    public function testSetCampoWithAnExpressionGetsAGeneratedAliasAndIsNotSearchable()
    {
        Crud::setCampo(['campo' => 'CONCAT(name, id)']);

        $campo = $this->read(Crud::class, 'camposShow')[0];

        $this->assertSame('CONCAT(name, id)', $campo['campoReal']);
        $this->assertStringStartsWith('a', $campo['alias']);
        $this->assertFalse($campo['searchable']);
    }

    public function testSetCampoMatchingTheTableIdIsNotEditableAndUsesAReservedAlias()
    {
        Crud::setTablaId('id');
        Crud::setCampo(['campo' => 'id']);

        $campo = $this->read(Crud::class, 'camposShow')[0];

        $this->assertSame('idsinenc0', $campo['alias']);
        $this->assertFalse($campo['editable']);
        $this->assertSame([], $this->read(Crud::class, 'camposEdit'));
    }

    public function testSetCampoWithShowFalseIsOnlyAddedToCamposEdit()
    {
        Crud::setCampo(['campo' => 'secret', 'show' => false]);

        $this->assertSame([], $this->read(Crud::class, 'camposShow'));
        $this->assertCount(1, $this->read(Crud::class, 'camposEdit'));
    }

    public function testSetCampoWithEditableFalseIsOnlyAddedToCamposShow()
    {
        Crud::setCampo(['campo' => 'created_at', 'editable' => false]);

        $this->assertCount(1, $this->read(Crud::class, 'camposShow'));
        $this->assertSame([], $this->read(Crud::class, 'camposEdit'));
    }

    public function testSetCampoAcceptsAValidCombobox()
    {
        Crud::setCampo(['campo' => 'country_id', 'tipo' => 'combobox', 'query' => 'SELECT id, name FROM countries', 'combokey' => 'id']);

        $campo = $this->read(Crud::class, 'camposShow')[0];

        $this->assertSame('combobox', $campo['tipo']);
        $this->assertSame('SELECT id, name FROM countries', $campo['query']);
        $this->assertSame('id', $campo['combokey']);
    }
}
