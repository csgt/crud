<?php
namespace Csgt\Crud\Tests\Fixtures;

use Csgt\Crud\CrudController;

/**
 * Minimal controller used by the tests: it declares the same fields a real CRUD
 * would declare, without touching the framework beyond Eloquent.
 */
class ClientsController extends CrudController
{
    public function __construct()
    {
        $this->setModel(new Client);
        $this->setTitle('Clients');

        $this->setField(['field' => 'name', 'name' => 'Name']);
        $this->setField(['field' => 'country.name AS country_name', 'name' => '<b>Country</b>', 'isforeign' => true]);
        $this->setField(['field' => 'tags', 'name' => 'Tags', 'type' => 'multi']);
        $this->setField(['field' => 'CONCAT(name, id) AS composed', 'name' => 'Composed']);
        $this->setField(['field' => 'secret', 'name' => 'Secret', 'show' => false, 'editable' => true]);
    }
}
