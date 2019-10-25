<?php declare(strict_types=1);

namespace CEmerson\Sevenambda\Layer;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zend\Diactoros\ServerRequest;
use Zend\Diactoros\Uri;

class LambdaPSR7Mapper
{
    private $eventPayload;
    private $data;

    public function mapLambdaRequestToPSR7ServerRequest($eventPayload, $data): ServerRequestInterface
    {
        $this->eventPayload = json_decode($eventPayload);
        $this->data = $data;

        $uri = new Uri(
            $this->eventPayload->requestContext->domainName . $this->eventPayload->requestContext->path
        );

        $serverRequest = new ServerRequest(
            [],
            [],
            $uri,
            $this->eventPayload->httpMethod
        );

        return $serverRequest;
    }

    public function mapPSR7ResponseToLambdaResponse(ResponseInterface $response): string
    {
        $info = [
            'eventPayload' => $this->eventPayload,
            'data' => $this->data
        ];

        $response->getBody()->write(json_encode($info, JSON_HEX_TAG));
        $response->getBody()->rewind();

        return json_encode([
            'statusCode' => $response->getStatusCode(),
            'body' => $response->getBody()->getContents(),
            'isBase64Encoded' => false
        ]);
    }
}
