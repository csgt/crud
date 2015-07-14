<?php namespace Csgt\Crud;

use Illuminate\Support\ServiceProvider;
use Illuminate\Foundation\AliasLoader;

class CrudServiceProvider extends ServiceProvider {

	protected $defer = false;

	public function boot() {
		$this->mergeConfigFrom(__DIR__ . '/config/csgtcrud.php', 'csgtcrud');
		AliasLoader::getInstance()->alias('Crud','Csgt\Crud\Crud');
		$this->loadViewsFrom(__DIR__ . '/resources/views/','csgtcrud');

		$this->publishes([
      __DIR__.'/config/csgtcrud.php' => config_path('csgtcrud.php'),
    ], 'config');
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