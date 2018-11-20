<?php
namespace Csgt\Crud;

use Illuminate\Routing\ResourceRegistrar as OriginalRegistrar;

class ResourceRegistrar extends OriginalRegistrar
{

    protected $resourceDefaults = ['index', 'create', 'store', 'show', 'edit', 'update', 'destroy', 'data', 'detail'];

    protected function addResourceData($name, $base, $controller, $options)
    {
        $uri    = $this->getResourceUri($name) . '/data';
        $action = $this->getResourceAction($name, $controller, 'data', $options);

        return $this->router->post($uri, $action);
    }

    protected function addResourceDetail($name, $base, $controller, $options)
    {
        $uri    = $this->getResourceUri($name) . '/{' . $base . '}/detail';
        $action = $this->getResourceAction($name, $controller, 'detail', $options);

        return $this->router->get($uri, $action);
    }

}
