<?php declare(strict_types=1);

/**
 * Copied from: https://github.com/akrabat/lambda-php/blob/2019-01-02-article/layer/php/runtime.php
 * Modified from: https://github.com/pagnihotry/PHP-Lambda-Runtime/blob/master/runtime/runtime.php
 * Copyright (c) 2018 Parikshit Agnihotry
 *
 * RKA Changes:
 *   - JSON encode result of handler function
 *   - Catch any Throwables and write to error log
 *
 * CJE Changes:
 *   - Encode Lambda request into PSR-7 compatible ServerRequest
 *   - Encode PSR-7 response into Lambda compatible Response
 */

use CEmerson\Sevenambda\Layer\LambdaRuntime;

require_once __DIR__ . '/vendor/autoload.php';

$lambdaRuntime = new LambdaRuntime();
$handler =  $lambdaRuntime->getHandler();

//Extract file name and function
[$handlerFile , $handlerFunction] = explode('.', $handler);

//Include the handler file
require_once($handlerFile . '.php');

//Poll for the next event to be processed
while (true) {
    //Get next event
    $data = $lambdaRuntime->getNextEventData();

    //Check if there was an error that runtime detected with the next event data
    if (isset($data['error']) && $data['error']) {
        continue;
    }

    //Process the events
    $eventPayload = $lambdaRuntime->getEventPayload();

    try {
        //Handler is of format Filename.function
        //Execute handler
        $functionReturn = $handlerFunction($eventPayload);
        $json = json_encode($functionReturn, true);
        $lambdaRuntime->addToResponse($json);
    } catch (Throwable $e) {
        error_log((string) $e);
    }

    //Report result
    $lambdaRuntime->flushResponse();
}
