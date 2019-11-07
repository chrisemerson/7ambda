<?php declare(strict_types=1);

namespace CEmerson\Sevenambda\Layer;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zend\Diactoros\ServerRequest;
use Zend\Diactoros\Stream;
use Zend\Diactoros\Uri;

class LambdaPSR7Mapper
{
    public static function mapLambdaRequestToPSR7ServerRequest($data): ServerRequestInterface
    {
        $event = json_decode($data['body'], true);

        $uri = new Uri(
            $event['headers']['X-Forwarded-Proto']
            . '://'
            . $event['requestContext']['domainName']
            . $event['requestContext']['path']
        );

        $bodyStream = fopen('php://memory','rb+');
        fwrite($bodyStream, $event['body'] ?? '');
        rewind($bodyStream);

        $headers = $event['headers'] ?? [];
        $cookies = [];

        foreach ($headers as $headerName => $value) {
            if ($headerName === "Cookie") {
                $cookieStrings = array_map('trim', explode(';', $value));

                foreach ($cookieStrings as $cookieString) {
                    $equalsSignPosition = strpos($cookieString, '=');

                    $name = substr($cookieString, 0, $equalsSignPosition);
                    $value = substr($cookieString, $equalsSignPosition + 1);

                    $cookies[$name] = $value;
                }
            }
        }

        $queryStringParams = [];

        if (!empty($event['queryStringParameters']) && count($event['queryStringParameters']) > 1) {
            $queryString = implode('&', array_map(
                function($key, $value) {
                    return $key . '=' . $value;
                },
                array_keys($event['queryStringParameters']),
                array_values($event['queryStringParameters'])
            ));

            parse_str($queryString, $queryStringParams);
        }

        $protocol = substr($event['requestContext']['protocol'], 5);

        $serverParams = $_SERVER;
        $serverParams['requestContext'] = $event['requestContext'];

        $serverRequest = new ServerRequest(
            $serverParams,
            [],
            $uri,
            $event['httpMethod'],
            new Stream($bodyStream),
            $headers,
            $cookies,
            $queryStringParams,
            null,
            $protocol
        );

        return $serverRequest;
    }

    public static function mapPSR7ResponseToLambdaResponse(ResponseInterface $response): string
    {
        if (!$response->hasHeader('Content-Type')) {
            $response = $response->withHeader('Content-Type', 'text/html');
        }

        $headers = [];

        foreach ($response->getHeaders() as $headerName => $headerValue) {
            $headers[$headerName] = $response->getHeaderLine($headerName);
        }

        $response->getBody()->rewind();

        return json_encode([
            'statusCode' => $response->getStatusCode(),
            'headers' => $headers,
            'body' => $response->getBody()->getContents(),
            'isBase64Encoded' => false
        ], JSON_HEX_TAG);
    }
}
