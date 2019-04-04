<?php

namespace PHPinnacle\Ridge\Bench;

use function Amp\call;
use function Amp\Promise\wait;

class ProduceBench extends AbstractBench
{
    /**
     * @Revs(1)
     * @Iterations(10)
     *
     * @return void
     * @throws \Throwable
     */
    public function benchPublish(): void
    {
        wait(call(function () {
            $promises = [];
            $count = 10000;

            for ($i = 0; $i < $count; ++$i) {
                $promises[] = $this->channel->publish($this->body, 'bench_exchange');
            }

            yield $promises;
        }));
    }
}

