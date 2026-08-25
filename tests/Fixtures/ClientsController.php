<?php
namespace Csgt\Crud\Tests\Fixtures;

use Csgt\Crud\CrudController;

/**
 * Controller minimo usado por las pruebas: declara los mismos campos que un CRUD
 * real declararia, sin tocar el framework mas alla de Eloquent.
 */
class ClientsController extends CrudController {
	public function __construct() {
		$this->setModelo(new Client);
		$this->setTitulo('Clients');

		$this->setCampo(['campo' => 'name', 'nombre' => 'Name']);
		$this->setCampo(['campo' => 'country.name', 'nombre' => 'Country']);
		$this->setCampo(['campo' => 'email', 'nombre' => 'Email']);
		$this->setCampo(['campo' => 'CONCAT(name, id)', 'nombre' => 'Composed']);
		$this->setCampo(['campo' => 'secret', 'nombre' => 'Secret', 'show' => false]);
	}
}
