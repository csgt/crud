<?php namespace Csgt\Crud;

use Illuminate\Support\ServiceProvider;
use Illuminate\Foundation\AliasLoader;

class CrudServiceProvider extends ServiceProvider {

	protected $defer = false;

	public function boot() {
		AliasLoader::getInstance()->alias('Crud','Csgt\Crud\Crud');
		$this->loadViewsFrom(__DIR__ . '/resources/views/','csgtcrud');
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
