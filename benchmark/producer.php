<?php

use Amp\Loop;
use PHPinnacle\Ridge\Channel;
use PHPinnacle\Ridge\Client;

require_once __DIR__ . '/../vendor/autoload.php';

Loop::run(function () use ($argv) {
    if (!$dsn = \getenv('RIDGE_BENCHMARK_DSN')) {
        echo 'No benchmark dsn! Please set RIDGE_BENCHMARK_DSN environment variable.', \PHP_EOL;

        Loop::stop();
    }

    $client = Client::create($dsn);

    yield $client->connect();

    /** @var Channel $channel */
    $channel = yield $client->channel();

    yield $channel->queueDeclare('bench_queue');
    yield $channel->exchangeDeclare('bench_exchange');
    yield $channel->queueBind('bench_queue', 'bench_exchange');

    $body = <<<EOT
abcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyz
abcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyz
abcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyz
abcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyz
abcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyz
abcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyz
abcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyz
abcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyz
abcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyz
abcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyza
EOT;

    $time = \microtime(true);
    $max  = isset($argv[1]) ? (int) $argv[1] : 1;

    $promises = [];

    for ($i = 0; $i < $max; $i++) {
        $promises[] = $channel->publish($body, 'bench_exchange');
    }

    $promises[] = $channel->publish('quit', 'bench_exchange');

    yield $promises;

    yield $client->disconnect();

    echo \microtime(true) - $time, \PHP_EOL;
});
