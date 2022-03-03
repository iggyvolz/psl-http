<?php

namespace iggyvolz\pslhttp;

use Nyholm\Psr7\ServerRequest;
use Psl\TCP\Server;
use Psl\TCP\ServerOptions;
use Psr\Http\Server\RequestHandlerInterface;
use function Psl\Async\run;
use function Psl\Str\format;

class HttpServer extends BaseHttp
{
    public function __construct(
        private readonly RequestHandlerInterface $requestHandler,
        public readonly string $host,
        public readonly int $port = 80,
        public readonly ?ServerOptions $serverOptions = null,
    )
    {
    }

    public function run(): void
    {
        $server = Server::create($this->host, $this->port, $this->serverOptions);
        foreach ($server->incoming() as $connection) {
            run(function() use($connection){
                try {
                    $method = self::readWord($connection);
                    $path = self::readWord($connection);
                    $httpVersion = substr(self::readWord($connection), 5);
                    $headers = self::extractHeaders($connection);
                    $serverRequest = new ServerRequest($method, $path, $headers, $connection->getStream(), $httpVersion);
                    $response = $this->requestHandler->handle($serverRequest);
                    $connection->writeAll(format("HTTP/%s %d %s\r\n", $response->getProtocolVersion(), $response->getStatusCode(), $response->getReasonPhrase()));
                    self::writeMessage($response, $connection);
                    $connection->close();
                } catch (\Throwable) {
                    $connection->write("HTTP/0.9 500 Internal Server Error\r\n\r\n");
                    $connection->close();
                    $connection->close();
                }
            });
        }
    }
}