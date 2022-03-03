<?php

namespace iggyvolz\pslhttp;

use Psl\Network\StreamSocketInterface;
use Psr\Http\Message\MessageInterface;
use function Psl\Str\format;

abstract class BaseHttp
{
    protected static function readWord(StreamSocketInterface $socket): string
    {
        return self::readUntil($socket, " ");
    }

    protected static function readLine(StreamSocketInterface $socket): string
    {
        return self::readUntil($socket, "\r\n");
    }

    protected static function readUntil(StreamSocketInterface $socket, string $end): string
    {
        $result = "";
        while(!str_ends_with($result, $end)) {
            $result .= $socket->readFixedSize(1);
        }
        return substr($result, 0, -strlen($end));
    }

    protected static function writeMessage(MessageInterface $request, StreamSocketInterface $client): void
    {
        foreach ($request->getHeaders() as $name => $values) {
            foreach ($values as $value) {
                $client->writeAll(format("%s: %s\r\n", $name, $value));
            }
        }
        $client->writeAll("\r\n");
        $body = $request->getBody()->detach();
        if(!is_null($body)) {
            rewind($body);
            stream_copy_to_stream($body, $client->getStream());
        }
    }


    /**
     * @param StreamSocketInterface $connection
     * @return string[][]
     */
    protected static function extractHeaders(StreamSocketInterface $connection): array
    {
        $headers = [];
        while (($line = self::readLine($connection)) !== "") {
            if (str_contains($line, ":")) {
                $key = trim(substr($line, 0, strpos($line, ":")));
                $value = trim(substr($line, strpos($line, ":")));
                $headers[$key] ??= [];
                $headers[$key][] = $value;
            }
        }
        return $headers;
    }
}