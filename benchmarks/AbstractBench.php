<?php

namespace PHPinnacle\Ridge\Bench;

use PHPinnacle\Ridge\Channel;
use PHPinnacle\Ridge\Client;
use function Amp\call;
use function Amp\Promise\wait;

/**
 * @BeforeMethods({"init"})
 * @AfterMethods({"clear"})
 */
abstract class AbstractBench
{
    /**
     * @var Client
     */
    protected $client;

    /**
     * @var Channel
     */
    protected $channel;

    /**
     * @var string
     */
    protected $body = <<<EOT
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

    /**
     * @return void
     * @throws \Throwable
     */
    public function init(): void
    {
        wait(call(function () {
            if (!$dsn = \getenv('RIDGE_BENCHMARK_DSN')) {
                throw new \RuntimeException('No benchmark DSN! Please setup RIDGE_BENCHMARK_DSN environment variable.');
            }

            $this->client = Client::create($dsn);

            yield $this->client->connect();

            $this->channel = yield $this->client->channel();

            yield $this->channel->queueDeclare('bench_queue');
            yield $this->channel->exchangeDeclare('bench_exchange');
            yield $this->channel->queueBind('bench_queue', 'bench_exchange');
        }));
    }

    /**
     * @return void
     * @throws \Throwable
     */
    public function clear(): void
    {
        wait(call(function () {
            yield $this->channel->queueDelete('bench_queue');
            yield $this->channel->exchangeDelete('bench_exchange');

            yield $this->client->disconnect();
        }));
    }
}

