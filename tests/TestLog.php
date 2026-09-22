<?php
namespace Csgt\Crud\Tests;

/**
 * Minimal stand-in for Laravel's log, bound as the 'log' container service so
 * the Log facade resolves in the suite (see TestCase.php, which never boots
 * illuminate/foundation). Records every message so a test can assert on it.
 */
class TestLog
{
    public $messages = [];

    public function warning($message, $context = [])
    {
        $this->messages[] = $message;
    }

    public function reset()
    {
        $this->messages = [];
    }
}
