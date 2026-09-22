<?php
namespace Csgt\Crud\Tests;

use Csgt\Crud\CrudServiceProvider;
use ReflectionClass;
use ReflectionProperty;
use RuntimeException;

/**
 * The provider refuses to boot on a Laravel it cannot work with.
 *
 * Installing the package over an incompatible framework does not fail at
 * install time, because the application brings its own illuminate packages.
 * Without this check the first sign of trouble was a missing method in the
 * middle of a listing.
 */
class CrudServiceProviderTest extends TestCase
{
    private function assertOn($version)
    {
        $reflection = new ReflectionClass(CrudServiceProvider::class);
        $provider   = $reflection->newInstanceWithoutConstructor();

        $app = new class($version)
        {
            private $version;

            public function __construct($version)
            {
                $this->version = $version;
            }

            public function version()
            {
                return $this->version;
            }
        };

        $property = new ReflectionProperty('Illuminate\Support\ServiceProvider', 'app');
        $property->setAccessible(true);
        $property->setValue($provider, $app);

        $method = $reflection->getMethod('assertLaravelVersion');
        $method->setAccessible(true);
        $method->invoke($provider);
    }

    public function testItBootsOnASupportedLaravel()
    {
        $this->assertOn('10.48.4');
        $this->assertOn(CrudServiceProvider::LARAVEL_MIN);

        // Llegar hasta aca sin excepcion es lo que se afirma.
        $this->assertTrue(true);
    }

    public function testItRefusesToBootBelowTheSupportedFloor()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/necesita Laravel/');

        $this->assertOn('5.7.28');
    }

    public function testItIgnoresAContainerThatDoesNotReportAComparableVersion()
    {
        // Lumen responde algo como "Lumen (8.3.4) (Laravel Components ^8.0)",
        // que no se puede comparar con el rango, asi que no se aborta por ello.
        $this->assertOn('Lumen (8.3.4) (Laravel Components ^8.0)');

        $this->assertTrue(true);
    }
}
