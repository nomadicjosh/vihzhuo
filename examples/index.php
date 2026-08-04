<?php

declare(strict_types=1);

use Qubus\Http\Emitter\SapiEmitter;
use Qubus\Http\ServerRequestFactory;
use Vihzhuo\Vihzhuo;

require dirname(__DIR__) . '/vendor/autoload.php';

$config = require dirname(__DIR__) . '/config/config.php';
if (!is_array($config)) {
    throw new RuntimeException('Vihzhuo configuration must return an array.');
}

$application = new Vihzhuo(array_filter($config, 'is_string', ARRAY_FILTER_USE_KEY));
$response = $application->handleRequest(ServerRequestFactory::fromGlobals());

new SapiEmitter()->emit($response);
