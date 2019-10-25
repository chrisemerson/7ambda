<?php declare(strict_types=1);

namespace CEmerson\Sevenambda\Layer;

/**
 * Copied from: https://github.com/akrabat/lambda-php/blob/2019-01-02-article/layer/php/runtime.php
 * Modified from: https://github.com/pagnihotry/PHP-Lambda-Runtime/blob/master/runtime/runtime.php
 * Copyright (c) 2018 Parikshit Agnihotry
 *
 * RKA Changes:
 *   - JSON encode result of handler function
 *   - Catch any Throwables and write to error log
 */

/**
 * PHP class to interact with AWS Runtime API
 */
final class LambdaRuntime
{
    const POST = 'POST';
    const GET = 'GET';

    /** @var string */
    private $url;

    /** @var string */
    private $functionCodePath;

    /** @var string */
    private $requestId;

    private $response;

    private $rawEventData;

    /** @var string */
    private $handler;

    public function __construct()
    {
        $this->url = 'http://' . getenv('AWS_LAMBDA_RUNTIME_API');
        $this->functionCodePath = getenv('LAMBDA_TASK_ROOT');
        $this->handler = getenv('_HANDLER');
    }

    /**
     * Get the current request ID being serviced by the runtime
     */
    public function getRequestId(): string
    {
        return $this->requestId;
    }

    /**
     * Get the handler setting defined in AWS Lambda configuration
     */
    public function getHandler(): string
    {
        return $this->handler;
    }

    /**
     * Get the buffered response
     */
    public function getResponse(): string
    {
        return $this->response;
    }

    /**
     * Reset the response buffer
     */
    public function resetResponse(): void
    {
        $this->response = '';
    }

    /**
     * Add string to the response buffer. This is printed out on success.
     */
    public function addToResponse(string $string): void
    {
        $this->response = $this->response . $string;
    }

    public function flushResponse(): void
    {
        $this->curl(
            '/2018-06-01/runtime/invocation/' . $this->getRequestId() . '/response',
            LambdaRuntime::POST,
            $this->getResponse()
        );

        $this->resetResponse();
    }

    /**
     * Get the Next event data
     */
    public function getNextEventData(): array
    {
        $this->rawEventData = $this->curl('/2018-06-01/runtime/invocation/next', LambdaRuntime::GET);

        if (!isset($this->rawEventData['headers']['lambda-runtime-aws-request-id'][0])) {
            //Handle error
            $this->reportError(
                'MissingEventData',
                'Event data is absent. EventData:' . var_export($this->rawEventData, true)
            );

            //setting up response so the while loop does not try to invoke the handler with unexpected data
            return ['error' => true];
        }

        $this->requestId = $this->rawEventData['headers']['lambda-runtime-aws-request-id'][0];

        return $this->rawEventData;
    }

    /**
     * Report error to Lambda runtime
     */
    public function reportError($errorType, $errorMessage): void
    {
        $errorArray = [
            'errorType' => $errorType,
            'errorMessage' => $errorMessage
        ];

        $errorPayload = json_encode($errorArray);

        $this->curl(
            '/2018-06-01/runtime/invocation/' . $this->getRequestId() . '/error',
            LambdaRuntime::POST,
            $errorPayload
        );
    }

    /**
     * Report initialization error with runtime
     */
    public function reportInitError($errorType, $errorMessage): void
    {
        $errorArray = [
            'errorType' => $errorType,
            'errorMessage' => $errorMessage
        ];

        $errorPayload = json_encode($errorArray);

        $this->curl(
            '/2018-06-01/runtime/init/error',
            LambdaRuntime::POST,
            $errorPayload
        );
    }

    /**
     * Internal function to make curl requests to the runtime API
     */
    private function curl(string $urlPath, string $method, string $payload = ''): array
    {
        $fullURL = $this->url . $urlPath;
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $fullURL);
        curl_setopt($ch, CURLOPT_NOBODY, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);

        $headers = [];

        // Parse curl headers
        curl_setopt($ch, CURLOPT_HEADERFUNCTION,
            function($curl, $header) use (&$headers)
            {
                $len = strlen($header);
                $header = explode(':', $header, 2);

                // ignore invalid headers
                if (count($header) < 2) {
                    return $len;
                }

                $name = strtolower(trim($header[0]));

                if (!array_key_exists($name, $headers)) {
                    $headers[$name] = [trim($header[1])];
                } else {
                    $headers[$name][] = trim($header[1]);
                }

                return $len;
            }
        );

        //handle post request
        if ($method == LambdaRuntime::POST) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);

            // Set HTTP Header for POST request
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Length: ' . strlen($payload)
            ]);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        return [
            'headers' => $headers,
            'body' => $response,
            'httpCode' => $httpCode
        ];
    }
}
