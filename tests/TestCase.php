<?php
namespace Csgt\Crud\Tests;

use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Support\Facades\Facade;
use PHPUnit\Framework\TestCase as BaseTestCase;
use ReflectionMethod;

/**
 * Base for the package tests.
 *
 * The suite asserts on the SQL and the bindings each method builds, so it needs
 * Eloquent and a connection, but never a live database server: no query is ever
 * executed. That keeps the suite runnable anywhere PHP and composer are.
 */
abstract class TestCase extends BaseTestCase {
	protected static $capsule;

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();

		if (static::$capsule) return;

		//El codigo del paquete usa los alias globales que registra una app Laravel.
		foreach (['DB' => 'Illuminate\\Support\\Facades\\DB', 'Storage' => 'Illuminate\\Support\\Facades\\Storage'] as $alias => $facade) {
			if (!class_exists($alias, false)) class_alias($facade, $alias);
		}

		$container = new Container;

		static::$capsule = new Capsule($container);
		static::$capsule->addConnection([
			'driver'   => 'sqlite',
			'database' => ':memory:',
			'prefix'   => '',
		]);
		static::$capsule->setAsGlobal();
		static::$capsule->bootEloquent();

		$container->instance('db', static::$capsule->getDatabaseManager());
		Container::setInstance($container);
		Facade::setFacadeApplication($container);
	}

	/**
	 * Llama un metodo privado o protegido del controller bajo prueba.
	 */
	protected function call($object, $method, array $arguments = []) {
		$reflection = new ReflectionMethod($object, $method);
		$reflection->setAccessible(true);

		return $reflection->invokeArgs($object, $arguments);
	}

	/**
	 * Lee una propiedad privada o protegida del controller bajo prueba.
	 */
	protected function read($object, $property) {
		$class = new \ReflectionClass($object);

		while (!$class->hasProperty($property) && $class->getParentClass()) {
			$class = $class->getParentClass();
		}

		$reflection = $class->getProperty($property);
		$reflection->setAccessible(true);

		return $reflection->getValue($object);
	}
}
