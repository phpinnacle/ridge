<?php
/**
 * This file is part of PHPinnacle/Ridge.
 *
 * (c) PHPinnacle Team <dev@phpinnacle.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace PHPinnacle\Ridge;

use Amp\Loop;

final class Heartbeat
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var string
     */
    private $watcher;

    /**
     * @var int
     */
    private $lastWrite;

    /**
     * @param Connection $connection
     */
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * @param int $interval
     *
     * @return void
     */
    public function enable(int $interval): void
    {
        $milliseconds = $interval * 1000;

        $this->watcher = Loop::repeat($milliseconds, function() use ($milliseconds) {
            $currentTime = $this->current();
            $lastWrite   = $this->lastWrite;

            if ($lastWrite === null) {
                $lastWrite = $currentTime;
            }

            /** @var int $nextHeartbeat */
            $nextHeartbeat = $lastWrite + $milliseconds;

            if ($currentTime >= $nextHeartbeat) {
                yield $this->connection->send(new Protocol\HeartbeatFrame);
            }

            unset($currentTime, $lastWrite, $nextHeartbeat);
        });
    }

    /**
     * @return void
     */
    public function disable(): void
    {
        if ($this->watcher !== null) {
            Loop::cancel($this->watcher);

            $this->watcher = null;
        }
    }

    /**
     * @return void
     */
    public function touch(): void
    {
        $this->lastWrite = $this->current();
    }

    /**
     * @return int
     */
    private function current(): int
    {
        return (int) \round(\microtime(true) * 1000);
    }
}
