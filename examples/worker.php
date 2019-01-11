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

    try {
        yield $client->connect();
    } catch (\Throwable $error) {
        echo "[!] Connection error: {$error->getMessage()}.", \PHP_EOL;

        Loop::stop();
    }

    /** @var Channel $channel */
    $channel = yield $client->channel();

    yield $channel->queueDeclare('test_queue', false, true, false, false);

    echo '[*] Waiting for messages. To exit press CTRL+C', \PHP_EOL;

    $tag = yield $channel->consume(function (Message $message, Channel $channel) {
        echo "[x] Received message: {$message->content()}.", \PHP_EOL;

        // Do some work - we generate password hashes with a high cost
        // sleep() gets interrupted by Ctrl+C so it's not very good for demos
        // Performing multiple work units demonstrates that nothing is skipped
        for ($i = 0; $i < 3; $i++) {
            echo "WU {$i}", \PHP_EOL;

            password_hash(random_bytes(255), PASSWORD_BCRYPT, ["cost" => 15]);
        }

        echo "[x] Done ", $message->content(), \PHP_EOL;

        try {
            yield $channel->ack($message);

            echo "ACK SUCCESS:: {$message->content()}", \PHP_EOL;
        } catch (\Throwable $error) {

            echo "ACK FAILED:: {$error->getMessage()}", \PHP_EOL;
        }
    }, 'test_queue');

    $onSignal = function () use ($client, $channel, $tag) {
        echo \PHP_EOL, "[!] Consumer cancelled.", \PHP_EOL;

        yield $channel->cancel($tag);
        yield $client->disconnect();

        Loop::stop();
    };

    Loop::onSignal(\SIGINT, $onSignal);
    Loop::onSignal(\SIGTERM, $onSignal);
});
