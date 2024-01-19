<?php
namespace Csgt\Crud;

use Illuminate\Foundation\AliasLoader;
use Illuminate\Support\ServiceProvider;

class CrudServiceProvider extends ServiceProvider
{

    protected $defer = false;

    public function boot()
    {
        $this->mergeConfigFrom(__DIR__ . '/config/csgtcrud.php', 'csgtcrud');
        AliasLoader::getInstance()->alias('Crud', 'Csgt\Crud\Crud');
        $this->loadViewsFrom(__DIR__ . '/resources/views/', 'csgtcrud');
        $this->loadTranslationsFrom(__DIR__ . '/resources/lang/', 'csgtcrud');

        $this->publishes([
            __DIR__ . '/config/csgtcrud.php' => config_path('csgtcrud.php'),
        ], 'config');
        $this->publishes([
            __DIR__ . '/resources/lang/' => base_path('/resources/lang/vendor/csgtcrud'),
        ], 'lang');
    }

    public function register()
    {
        $this->app['crud'] = $this->app->share(function ($app) {
            return new Crud;
        });
    }

    public function provides()
    {
        return ['crud'];
    }
}
