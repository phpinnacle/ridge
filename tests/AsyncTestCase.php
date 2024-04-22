<?php
/**
 * This file is part of PHPinnacle/Ridge.
 *
 * (c) PHPinnacle Team <dev@phpinnacle.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PHPinnacle\Ridge\Tests;

use Amp\Loop;
use function Amp\call;

abstract class AsyncTestCase extends RidgeTestCase
{
    /**
     * @var string|null
     */
    private $realTestName;

    protected function runTest(): mixed
    {
        $this->realTestName = $this->name();

        parent::setName('runTestAsync');

        return parent::runTest();
    }

    protected function runTestAsync(...$args)
    {
        $return = null;

        try {
            Loop::run(function () use (&$return, $args) {
                $client = self::client();

                yield $client->connect();

                $args = $args ?: [];
                \array_unshift($args, $client);

                $return = yield call([$this, $this->realTestName], ...$args);

                yield $client->disconnect();

                $info  = Loop::getInfo();
                $count = $info['enabled_watchers']['referenced'];

                if ($count !== 0) {
                    $message = "Still have {$count} loop watchers.";

                    foreach (['defer', 'delay', 'repeat', 'on_readable', 'on_writable'] as $key) {
                        $message .= " {$key} - {$info[$key]['enabled']}.";
                    }

                    self::markTestIncomplete($message);

                    Loop::stop();
                }
            });
        } finally {
            Loop::set((new Loop\DriverFactory)->create());

            \gc_collect_cycles();
        }

        return $return;
    }
}
