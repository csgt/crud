<?php namespace Csgt\Crud;

use Illuminate\Support\ServiceProvider;
use Illuminate\Foundation\AliasLoader;

class CrudServiceProvider extends ServiceProvider {

	protected $defer = false;

	public function boot() {
		$registrar = new \Csgt\Crud\ResourceRegistrar($this->app['router']);
    $this->app->bind('Illuminate\Routing\ResourceRegistrar', function () use ($registrar) {
			return $registrar;
		});


		$this->mergeConfigFrom(__DIR__ . '/config/csgtcrud.php', 'csgtcrud');
		//AliasLoader::getInstance()->alias('Crud','Csgt\Crud\Crud');
		$this->loadViewsFrom(__DIR__ . '/resources/views/','csgtcrud');
		$this->loadTranslationsFrom(__DIR__.'/resources/lang/', 'csgtcrud');

		$this->publishes([
      __DIR__.'/config/csgtcrud.php' => config_path('csgtcrud.php'),
    ], 'config');
    $this->publishes([
      __DIR__.'/resources/lang/' => base_path('/resources/lang/vendor/csgtcrud'),
    ], 'lang');
	}

	 public function register() {
    $this->commands([
      Console\MakeCrudCommand::class
    ]);
  }
}