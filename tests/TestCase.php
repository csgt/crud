<?php
namespace Csgt\Crud\Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

/**
 * Base for the package tests.
 *
 * Crud is a static class, so its state (the declared fields, wheres, orders,
 * etc.) survives across tests unless reset. resetCrudState() puts every
 * private static property back to its class-declared default before each
 * test, so tests can run in isolation and in any order.
 */
abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->resetCrudState();
    }

    protected function resetCrudState()
    {
        $class     = new ReflectionClass(\Csgt\Crud\Crud::class);
        $defaults  = $class->getDefaultProperties();

        foreach ($defaults as $name => $value) {
            $property = $class->getProperty($name);
            $property->setAccessible(true);
            $property->setValue(null, $value);
        }
    }

    /**
     * Calls a private or protected static method of the class under test.
     */
    protected function call($class, $method, array $arguments = [])
    {
        $reflection = new ReflectionMethod($class, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs(null, $arguments);
    }

    /**
     * Reads a private or protected static property of the class under test.
     */
    protected function read($class, $property)
    {
        $reflection = new ReflectionProperty($class, $property);
        $reflection->setAccessible(true);

        return $reflection->getValue();
    }
}
