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

use CEmerson\Sevenambda\Layer\LambdaPSR7Mapper;
use CEmerson\Sevenambda\Layer\LambdaRuntime;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;

require_once __DIR__ . '/vendor/autoload.php';

$lambdaRuntime = new LambdaRuntime();
$handlerClass =  $lambdaRuntime->getHandler();

//Include the handler file
require_once('index.php');

//Poll for the next event to be processed
while (true) {
    //Get next event
    $data = $lambdaRuntime->getNextEventData();

    //Check if there was an error that runtime detected with the next event data
    if (isset($data['error']) && $data['error']) {
        continue;
    }

    try {
        //Handler is a reference to a class that implements RequestHandlerInterface
        $handler = new $handlerClass();

        if ($handler instanceof RequestHandlerInterface) {
            $request = LambdaPSR7Mapper::mapLambdaRequestToPSR7ServerRequest($data);

            //Execute handler
            /** @var ResponseInterface $response */
            $response = $handler->handle($request);

            $lambdaRuntime->addToResponse(
                LambdaPSR7Mapper::mapPSR7ResponseToLambdaResponse($response)
            );
        }
    } catch (Throwable $e) {
        error_log((string) $e);
    }

    //Report result
    $lambdaRuntime->flushResponse();
}
