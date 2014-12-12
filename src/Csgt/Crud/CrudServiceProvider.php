<?php namespace Csgt\Crud;

use Illuminate\Support\ServiceProvider;
use Illuminate\Foundation\AliasLoader;

class CrudServiceProvider extends ServiceProvider {

	protected $defer = false;

	public function boot() {
		$this->package('csgt/crud');
		AliasLoader::getInstance()->alias('Crud','Csgt\Crud\Crud');
		include __DIR__.'/../../routes.php';
	}

	public function register() {
		$this->app['crud'] = $this->app->share(function($app) {
    	return new Crud;
  	});
	}

	public function provides() {
		return array('crud');
	}

}
