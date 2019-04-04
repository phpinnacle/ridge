<?php

namespace PHPinnacle\Ridge\Bench;

use PHPinnacle\Ridge\Message;
use function Amp\call;
use function Amp\Promise\wait;

class ConsumeBench extends AbstractBench
{
    public function init(): void
    {
        parent::init();

        wait(call(function () {
            $promises = [];
            $count = 10000;

            for ($i = 0; $i < $count; ++$i) {
                $promises[] = $this->channel->publish($this->body, 'bench_exchange');
            }

            $promises[] = $this->channel->publish('quit', 'bench_exchange');

            yield $promises;
        }));
    }

    /**
     * @Revs(1)
     * @Iterations(10)
     *
     * @return void
     * @throws \Throwable
     */
    public function benchConsume(): void
    {
        wait(call(function () {
            yield $this->channel->consume(function (Message $message) {
                if ($message->content() === 'quit') {
                    yield $this->client->disconnect();
                }
            }, 'bench_queue', '', false, true);
        }));
    }

    public function clear(): void
    {
    }
}
