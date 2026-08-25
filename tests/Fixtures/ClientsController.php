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
        $this->setModelo(new Client);
        $this->setTitulo('Clients');

        $this->setCampo(['campo' => 'name', 'nombre' => 'Name']);
        $this->setCampo(['campo' => 'country.name', 'nombre' => 'Country']);
        $this->setCampo(['campo' => 'CONCAT(name, id)', 'nombre' => 'Composed']);
        $this->setCampo(['campo' => 'secret', 'nombre' => 'Secret', 'show' => false]);
    }
}
