<?php namespace Csgt\Crud;

use Illuminate\Routing\ResourceRegistrar as OriginalRegistrar;

class ResourceRegistrar extends OriginalRegistrar {

  protected $resourceDefaults = ['index', 'create', 'store', 'show', 'edit', 'update', 'destroy', 'data'];

  protected function addResourceData($name, $base, $controller, $options) {
    $uri = $this->getResourceUri($name).'/data';
    $action = $this->getResourceAction($name, $controller, 'data', $options);
    return $this->router->post($uri, $action);
  }
}