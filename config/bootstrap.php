<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

if (is_array($env = @include dirname(__DIR__).'/.env.local.php')) {
    $_ENV = array_merge($_ENV, $env);
    $_SERVER = array_merge($_SERVER, $env);
} else {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}