<?php
namespace Csgt\Crud\Console;

use Illuminate\Console\GeneratorCommand;

//use Illuminate\Console\Command;

class MakeCrudCommand extends GeneratorCommand
{

    protected $name = 'make:crud';

    protected $description = 'Crear un controlador CRUD vacío';
    protected $type        = 'CrudController';

    protected function getStub()
    {
        return __DIR__ . '/stubs/crud.stub';
    }

    protected function getDefaultNamespace($rootNamespace)
    {
        return $rootNamespace . '\Http\Controllers';
    }

    protected function buildClass($name)
    {
        $controllerNamespace = $this->getNamespace($name);

        $replace = [];

        $replace["use {$controllerNamespace}\Controller;\n"] = '';

        return str_replace(
            array_keys($replace), array_values($replace), parent::buildClass($name)
        );
    }
}
