<?php

namespace iggyvolz\pslhttp;

use Psl\Network\StreamSocketInterface;

interface AfterMessageHandler
{
    public function handle(StreamSocketInterface $connection): void;
}