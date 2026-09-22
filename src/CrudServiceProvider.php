<?php
namespace Csgt\Crud;

use Illuminate\Support\ServiceProvider;

class CrudServiceProvider extends ServiceProvider
{

    /**
     * Rango de Laravel que soporta esta version del paquete. Un MAX vacio
     * significa que no se conoce un techo.
     */
    const PACKAGE_VERSION = '5.6';

    const LARAVEL_MIN = '5.5';

    const LARAVEL_MAX = '';

    protected $defer = false;

    public function boot()
    {
        $this->assertLaravelVersion();

        $registrar = new \Csgt\Crud\ResourceRegistrar($this->app['router']);
        $this->app->bind('Illuminate\Routing\ResourceRegistrar', function () use ($registrar) {
            return $registrar;
        });

        $this->mergeConfigFrom(__DIR__ . '/config/csgtcrud.php', 'csgtcrud');
        //AliasLoader::getInstance()->alias('Crud','Csgt\Crud\Crud');
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
        $this->commands([
            Console\MakeCrudCommand::class,
        ]);
    }

    /**
     * El composer.json de la aplicacion trae su propio illuminate, asi que
     * instalar este paquete sobre un Laravel incompatible no falla al
     * instalar. El error aparecia mucho despues, como un metodo inexistente
     * en medio de un listado. Se comprueba al arrancar para que el mensaje
     * diga que pasa y hacia donde moverse.
     */
    private function assertLaravelVersion()
    {
        if (!method_exists($this->app, 'version')) {
            return;
        }

        $version = (string) $this->app->version();

        // Lumen y otros contenedores no reportan una version comparable.
        if (!preg_match('/^(\d+\.\d+)/', $version, $matches)) {
            return;
        }

        $actual = $matches[1];

        if (version_compare($actual, self::LARAVEL_MIN, '<')) {
            throw new \RuntimeException(
                'csgt/crud ' . self::PACKAGE_VERSION . ' necesita Laravel ' . self::LARAVEL_MIN
                . ' o superior y esta corriendo sobre ' . $version
                . '. Use una version anterior del paquete.'
            );
        }

        if (self::LARAVEL_MAX !== '' && version_compare($actual, self::LARAVEL_MAX, '>=')) {
            throw new \RuntimeException(
                'csgt/crud ' . self::PACKAGE_VERSION . ' no funciona sobre Laravel ' . $version
                . ': soporta hasta antes de ' . self::LARAVEL_MAX
                . '. Mueva la aplicacion a una rama mas nueva del paquete.'
            );
        }
    }
}
