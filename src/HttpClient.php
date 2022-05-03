<?php

namespace iggyvolz\pslhttp;

use Nyholm\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use function Psl\Str\format;
use function Psl\Tcp\connect;

class HttpClient extends BaseHttp implements ClientInterface
{

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $https = $request->getUri()->getScheme() === "https";
        $connection = connect($request->getUri()->getHost(), $request->getUri()->getPort() ?? ($https ? 443 : 80), tls: $https);
        $connection->writeAll(format("%s %s HTTP/%s\r\n", $request->getMethod(), $request->getUri()->getPath() . (($request->getUri()->getQuery() === "" ? "" : "?" . $request->getUri()->getQuery())), $request->getProtocolVersion()));
        $this->writeMessage($request, $connection);
        $version = substr($this->readWord($connection), 5);
        $status = $this->readWord($connection);
        $reason = $this->readLine($connection);
        $headers = self::extractHeaders($connection);
        $body = $connection->getStream();
        return new Response($status, $headers, $body, $version, $reason);
    }
}