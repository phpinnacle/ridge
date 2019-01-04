<?php

use Amp\Loop;
use PHPinnacle\Ridge\Channel;
use PHPinnacle\Ridge\Client;
use PHPinnacle\Ridge\Message;

require __DIR__ . '/vendor/autoload.php';

Loop::run(function () {
    $client = new Client('amqp://admin:admin123@172.23.0.2');

    yield $client->connect();

    /** @var Channel $channel */
    $channel = yield $client->channel();

    yield $channel->queueDeclare('test_queue');

    for ($i = 0; $i < 100; $i++) {
        yield $channel->publish("test_$i", '', 'test_queue');
    }

    yield $channel->consume(function (Message $message, Channel $channel) {
        echo $message->content() . \PHP_EOL;

        yield $channel->ack($message);
    }, 'test_queue');
});
