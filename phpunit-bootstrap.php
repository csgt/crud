<?php

require __DIR__ . '/vendor/autoload.php';

if (!class_exists('DB')) {
    class_alias(Illuminate\Database\Capsule\Manager::class, 'DB');
}

if (!function_exists('config')) {
    function config($key = null, $default = null)
    {
        if ($key === 'csgtcrud.usar_encripcion') {
            return false;
        }

        return $default;
    }
}

if (!function_exists('response')) {
    function response()
    {
        return new class {
            public function json($data, $status = 200, array $headers = [])
            {
                return new Symfony\Component\HttpFoundation\JsonResponse($data, $status, $headers);
            }
        };
    }
}
