<?php

use Amp\Loop;
use PHPinnacle\Ridge\Channel;
use PHPinnacle\Ridge\Client;
use PHPinnacle\Ridge\Message;

require __DIR__ . '/../vendor/autoload.php';

Loop::run(function () {
    if (!$dsn = \getenv('RIDGE_EXAMPLE_DSN')) {
        echo 'No example dsn! Please set RIDGE_EXAMPLE_DSN environment variable.', \PHP_EOL;

        Loop::stop();
    }

    $client = Client::create($dsn);

    yield $client->connect();

    /** @var Channel $channel */
    $channel = yield $client->channel();

    yield $channel->queueDeclare('test_queue', false, false, false, true);

    $body = \str_repeat("a", 10 << 20); // 10 MiB
    $body = '';

    yield $channel->publish($body, '', 'test_queue');

    yield $channel->consume(function (Message $message, Channel $channel) use ($body, $client) {
        echo 'YAA!';

        yield $channel->ack($message);
        // yield $client->disconnect();
    }, 'test_queue');
//
//    yield $channel->queueDeclare('basic_queue', false, false, false, true);
//
//    for ($i = 0; $i < 10; $i++) {
//        yield $channel->publish("test_$i", '', 'basic_queue');
//    }
//
//    yield $channel->consume(function (Message $message, Channel $channel) {
//        echo $message->content() . \PHP_EOL;
//
//        yield $channel->ack($message);
//    }, 'basic_queue');
});
