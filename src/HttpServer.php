<?php

namespace iggyvolz\pslhttp;

use Nyholm\Psr7\ServerRequest;
use Nyholm\Psr7\Uri;
use Psl\IO\Exception\AlreadyClosedException;
use Psl\TCP\Server;
use Psl\TCP\ServerOptions;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use function Psl\Async\run;
use function Psl\Str\format;

class HttpServer extends BaseHttp
{
    private ?Server $server = null;

    public function __construct(
        private readonly RequestHandlerInterface $requestHandler,
        public readonly string $host = "0.0.0.0",
        public readonly int $port = 80,
        public readonly ?ServerOptions $serverOptions = null,
        private readonly LoggerInterface $logger = new NullLogger(),
    )
    {
    }

    public function run(): void
    {
        try {
            if(!is_null($this->server)) {
                throw new \RuntimeException("Server already running");
            }
            $this->server = Server::create($this->host, $this->port, $this->serverOptions);
            foreach ($this->server->incoming() as $connection) {
                run(function() use($connection){
                    try {
                        $method = self::readWord($connection);
                        $path = self::readWord($connection);
                        $httpVersion = substr(self::readLine($connection), 5);
                        $headers = self::extractHeaders($connection);
                        $serverRequest = new ServerRequest($method, "http://" . ($headers["host"][0] ?? "0.0.0.0") . "$path", $headers, $connection->getStream(), $httpVersion);
                        parse_str($serverRequest->getUri()->getQuery(), $result);
                        $serverRequest = $serverRequest->withQueryParams($result);
                        $response = $this->requestHandler->handle($serverRequest);
                        $connection->writeAll(format("HTTP/%s %d %s\r\n", $response->getProtocolVersion(), $response->getStatusCode(), $response->getReasonPhrase()));
                        self::writeMessage($response, $connection);
                        $this->logger->info("$method $path " . $response->getStatusCode());
                        if($response instanceof AfterMessageHandler) {
                            $response->handle($connection);
                        } else {
                            $connection->close();
                        }
                    } catch(\Psl\IO\Exception\RuntimeException) {
                        try { $connection->close(); } catch(\Throwable) {}
                    } catch (\Throwable $e) {
                        $this->logger->error("$method $path 500: $e");
                        $connection->write("HTTP/1.1 500 Internal Server Error\r\n\r\n");
                        $connection->close();
                    }
                });
            }
        } catch (AlreadyClosedException) {

        }
    }
    public function stop(): void
    {
        $this->server?->close();
        $this->server = null;
    }
    public function __destruct()
    {
        $this->stop();
    }
}
