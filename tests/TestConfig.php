<?php
namespace Csgt\Crud\Tests;

/**
 * Tiny in-memory config store used only by tests/bootstrap.php's config()
 * shim. The suite deliberately does not boot a full Laravel application (see
 * TestCase.php), so there is no real config repository to read from; tests
 * that need to simulate a configured value call TestConfig::set() and the
 * shim reads it back through the ordinary config() helper the source uses.
 */
class TestConfig
{
    private static $values = [];

    public static function set($key, $value)
    {
        self::$values[$key] = $value;
    }

    public static function get($key, $default = null)
    {
        return array_key_exists($key, self::$values) ? self::$values[$key] : $default;
    }

    public static function reset()
    {
        self::$values = [];
    }
}
