<?php declare(strict_types=1);

namespace CEmerson\Sevenambda\Layer;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zend\Diactoros\ServerRequest;

class LambdaPSR7Mapper
{
    private $eventPayload;
    private $data;

    public function mapLambdaRequestToPSR7ServerRequest($eventPayload, $data): ServerRequestInterface
    {
        $this->eventPayload = $eventPayload;
        $this->data = $data;

        return new ServerRequest();
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
