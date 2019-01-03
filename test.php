<?php

use PHPinnacle\Ridge\Channel;
use PHPinnacle\Ridge\Client;
use PHPinnacle\Ridge\Config;
use PHPinnacle\Ridge\Message;

require __DIR__ . '/vendor/autoload.php';

\Amp\Loop::run(function () {
    $config = Config::dsn('amqp://admin:admin123@172.18.0.2');
    $client = new Client($config);

    yield $client->connect();

    /** @var Channel $channel */
    $channel = yield $client->channel();

    yield $channel->consume(function (Message $message, Channel $channel) {
        yield $channel->ack($message);
    }, 'queue_name');

    \Amp\Loop::stop();
});
