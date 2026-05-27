<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

if (is_array($env = @include dirname(__DIR__).'/.env.local.php')) {
    $_ENV = array_merge($_ENV, $env);
    $_SERVER = array_merge($_SERVER, $env);
} else {
    $envPath = dirname(__DIR__).'/.env';
    // Only attempt to load `.env` if it exists. On platforms like
    // Railway the environment is provided at runtime and there may be
    // no `.env` file present in the container; calling bootEnv when
    // the file is missing throws a PathException.
    if (file_exists($envPath)) {
        (new Dotenv())->bootEnv($envPath);
    }
    // If no `.env` is present we rely on runtime environment
    // variables (e.g. DATABASE_URL, APP_ENV) provided by the host.
}