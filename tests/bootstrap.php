<?php

require __DIR__ . '/../vendor/autoload.php';

if (!function_exists('config')) {
    /**
     * Minimal shim of Laravel's config() helper for the test suite.
     *
     * A real application always provides this (it ships with
     * illuminate/foundation), but the suite deliberately runs without that
     * package so it can stay free of a booted application or a database
     * server (see tests/TestCase.php). Source code that reads config() goes
     * through Csgt\Crud\CrudController::config(), which falls back to the
     * given default when the helper does not exist at all; this shim makes it
     * exist here too, backed by Csgt\Crud\Tests\TestConfig, so tests can
     * simulate a configured value.
     */
    function config($key = null, $default = null)
    {
        return \Csgt\Crud\Tests\TestConfig::get($key, $default);
    }
}
