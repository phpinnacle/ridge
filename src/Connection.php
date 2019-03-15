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

use function Amp\asyncCall, Amp\call, Amp\Socket\connect;
use Amp\Deferred;
use Amp\Loop;
use Amp\Promise;
use Amp\Socket\ClientConnectContext;
use Amp\Socket\Socket;

final class Connection
{
    /**
     * @var string
     */
    private $uri;

    /**
     * @var Parser
     */
    private $parser;

    /**
     * @var Socket
     */
    private $socket;

    /**
     * @var Deferred[][][]
     */
    private $callbacks = [];

    /**
     * @var int
     */
    private $lastWrite = 0;

    /**
     * @var string
     */
    private $heartbeat;

    /**
     * @param string $uri
     */
    public function __construct(string $uri)
    {
        $this->uri    = $uri;
        $this->parser = new Parser;
    }

    /**
     * @noinspection PhpDocMissingThrowsInspection
     *
     * @param Buffer $payload
     *
     * @return Promise<int>
     */
    public function write(Buffer $payload): Promise
    {
        $this->lastWrite = Loop::now();

        /** @noinspection PhpUnhandledExceptionInspection */
        return $this->socket->write($payload->flush());
    }

    /**
     * @param int    $channel
     * @param Buffer $payload
     *
     * @return Promise<int>
     */
    public function method(int $channel, Buffer $payload): Promise
    {
        return $this->write((new Buffer)
            ->appendUint8(1)
            ->appendUint16($channel)
            ->appendUint32($payload->size())
            ->append($payload)
            ->appendUint8(206)
        );
    }

    /**
     * @param int      $channel
     * @param string   $frame
     * @param callable $callback
     */
    public function subscribe(int $channel, string $frame, callable $callback): void
    {
        $this->callbacks[$channel][$frame][] = $callback;
    }

    /**
     * @param int $channel
     *
     * @return void
     */
    public function cancel(int $channel): void
    {
        unset($this->callbacks[$channel]);
    }

    /**
     * @param int  $timeout
     * @param int  $maxAttempts
     * @param bool $noDelay
     *
     * @return Promise
     */
    public function open(int $timeout, int $maxAttempts, bool $noDelay): Promise
    {
        return call(function () use ($timeout, $maxAttempts, $noDelay) {
            $context = new ClientConnectContext;

            if ($maxAttempts > 0) {
                $context = $context->withMaxAttempts($maxAttempts);
            }

            if ($timeout > 0) {
                $context = $context->withConnectTimeout($timeout);
            }

            if ($noDelay) {
                $context = $context->withTcpNoDelay();
            }

            $this->socket = yield connect($this->uri, $context);

            asyncCall(function () {
                while (null !== $chunk = yield $this->socket->read()) {
                    $this->parser->append($chunk);

                    while ($frame = $this->parser->parse()) {
                        $class = \get_class($frame);

                        foreach ($this->callbacks[$frame->channel][$class] ?? [] as $i => $callback) {
                            if (yield call($callback, $frame)) {
                                unset($this->callbacks[$frame->channel][$class][$i]);
                            }
                        }
                    }
                }

                $this->socket = null;
            });
        });
    }

    /**
     * @param int $interval
     *
     * @return void
     */
    public function heartbeat(int $interval): void
    {
        $milliseconds = $interval * 1000;

        $this->heartbeat = Loop::repeat($milliseconds, function($watcher) use ($milliseconds) {
            if (null === $this->socket) {
                Loop::cancel($watcher);

                return;
            }

            $currentTime = Loop::now();
            $lastWrite   = $this->lastWrite ?: $currentTime;

            /** @var int $nextHeartbeat */
            $nextHeartbeat = $lastWrite + $milliseconds;

            if ($currentTime >= $nextHeartbeat) {
                yield $this->write((new Buffer)
                    ->appendUint8(8)
                    ->appendUint16(0)
                    ->appendUint32(0)
                    ->appendUint8(206)
                );
            }

            unset($currentTime, $lastWrite, $nextHeartbeat);
        });
    }

    /**
     * @return void
     */
    public function close(): void
    {
        $this->callbacks = [];

        if ($this->heartbeat !== null) {
            Loop::cancel($this->heartbeat);

            $this->heartbeat = null;
        }

        $this->socket->close();
    }
}
