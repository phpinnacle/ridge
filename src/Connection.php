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
     * @var ProtocolWriter
     */
    private $writer;

    /**
     * @var ProtocolReader
     */
    private $reader;

    /**
     * @var Socket
     */
    private $socket;

    /**
     * @var Deferred[][][]
     */
    private $await = [];

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
        $this->writer = new ProtocolWriter;
        $this->reader = new ProtocolReader;
    }

    /**
     * @noinspection PhpDocMissingThrowsInspection
     *
     * @param Buffer $payload
     *
     * @return Promise
     */
    public function write(Buffer $payload): Promise
    {
        $this->lastWrite = Loop::now();

        /** @noinspection PhpUnhandledExceptionInspection */
        return $this->socket->write($payload->flush());
    }

    /**
     * @param Protocol\AbstractFrame $frame
     *
     * @return Promise
     */
    public function send(Protocol\AbstractFrame $frame): Promise
    {
        return $this->write($this->writer->buffer($frame));
    }

    /**
     * @param int    $channel
     * @param int    $class
     * @param int    $method
     * @param Buffer $payload
     *
     * @return Promise
     */
    public function method(int $channel, int $class, int $method, Buffer $payload): Promise
    {
        $frame = new Protocol\MethodFrame($class, $method);
        $frame->channel = $channel;
        $frame->payload = $payload;
        $frame->size    = $payload->size();

        return $this->send($frame);
    }

    /**
     * @param int    $channel
     * @param string $frame
     *
     * @return Promise
     */
    public function await(int $channel, string $frame): Promise
    {
        $deferred = new Deferred;

        $this->await[$channel][$frame][] = $deferred;

        return $deferred->promise();
    }

    /**
     * @param int $channel
     *
     * @return void
     */
    public function cancel(int $channel): void
    {
        unset($this->await[$channel]);
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
            $context = (new ClientConnectContext)
                ->withConnectTimeout($timeout)
                ->withMaxAttempts($maxAttempts)
            ;

            if ($noDelay) {
                $context->withTcpNoDelay();
            }

            $this->socket = yield connect($this->uri, $context);

            asyncCall(function () {
                while (null !== $chunk = yield $this->socket->read()) {
                    $this->consume($chunk);
                }

                unset($this->socket);
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
            if (!$this->socket) {
                Loop::cancel($watcher);

                return;
            }

            $currentTime = Loop::now();
            $lastWrite   = $this->lastWrite;

            if ($lastWrite === null) {
                $lastWrite = $currentTime;
            }

            /** @var int $nextHeartbeat */
            $nextHeartbeat = $lastWrite + $milliseconds;

            if ($currentTime >= $nextHeartbeat) {
                yield $this->send(new Protocol\HeartbeatFrame);
            }

            unset($currentTime, $lastWrite, $nextHeartbeat);
        });
    }

    /**
     * @return void
     */
    public function close(): void
    {
        if ($this->heartbeat !== null) {
            Loop::cancel($this->heartbeat);

            unset($this->heartbeat);
        }

        $this->socket->close();

        $this->await = [];
    }

    /**
     * @param string $chunk
     *
     * @return void
     */
    private function consume(string $chunk): void
    {
        $this->reader->append($chunk);

        while ($frame = $this->reader->frame()) {
            $class  = \get_class($frame);
            $defers = $this->await[$frame->channel][$class] ?? [];

            foreach ($defers as $i => $defer) {
                $defer->resolve($frame);

                unset($this->await[$frame->channel][$class][$i]);
            }
        }
    }
}
