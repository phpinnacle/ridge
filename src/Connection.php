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

use PHPinnacle\Ridge\Exception\ConnectionException;
use function Amp\asyncCall, Amp\call, Amp\Socket\connect;
use Amp\Socket\ConnectContext;
use Amp\Loop;
use Amp\Promise;
use Amp\Socket\Socket;
use PHPinnacle\Ridge\Protocol\AbstractFrame;

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
     * @var Socket|null
     */
    private $socket;

    /**
     * @var callable[][][]
     * @psalm-var array<int, array<class-string<AbstractFrame>, array<int, callable>>>
     */
    private $callbacks = [];

    /**
     * @var int
     */
    private $lastWrite = 0;

    /**
     * @var string|null
     */
    private $heartbeatWatcherId;

    public function __construct(string $uri)
    {
        $this->uri    = $uri;
        $this->parser = new Parser;
    }

    /**
     * @throws \PHPinnacle\Ridge\Exception\ConnectionException
     */
    public function write(Buffer $payload): Promise
    {
        $this->lastWrite = Loop::now();

        if($this->socket !== null)
        {
            try
            {
                return $this->socket->write($payload->flush());
            }
            catch(\Throwable $throwable)
            {
                throw ConnectionException::writeFailed($throwable);
            }
        }

        throw ConnectionException::socketClosed();
    }

    /**
     * @throws \PHPinnacle\Ridge\Exception\ConnectionException
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
     * @psalm-param class-string<AbstractFrame> $frame
     */
    public function subscribe(int $channel, string $frame, callable $callback): void
    {
        $this->callbacks[$channel][$frame][] = $callback;
    }

    public function cancel(int $channel): void
    {
        unset($this->callbacks[$channel]);
    }

    /**
     * @throws \PHPinnacle\Ridge\Exception\ConnectionException
     */
    public function open(int $timeout, int $maxAttempts, bool $noDelay): Promise
    {
        return call(
            function() use ($timeout, $maxAttempts, $noDelay)
            {
                $context = new ConnectContext();

                if($maxAttempts > 0)
                {
                    $context = $context->withMaxAttempts($maxAttempts);
                }

                if($timeout > 0)
                {
                    $context = $context->withConnectTimeout($timeout);
                }

                if($noDelay)
                {
                    $context = $context->withTcpNoDelay();
                }

                $this->socket = yield connect($this->uri, $context);

                asyncCall(
                    function()
                    {
                        if($this->socket === null)
                        {
                            throw ConnectionException::socketClosed();
                        }

                        while(null !== $chunk = yield $this->socket->read())
                        {
                            $this->parser->append($chunk);

                            while($frame = $this->parser->parse())
                            {
                                /** @var AbstractFrame $frame */

                                $class = \get_class($frame);

                                /**
                                 * @psalm-var int $i
                                 * @psalm-var callable(AbstractFrame):Promise<bool> $callback
                                 */
                                foreach($this->callbacks[(int) $frame->channel][$class] ?? [] as $i => $callback)
                                {
                                    $result = yield call($callback, $frame);

                                    if($result)
                                    {
                                        unset($this->callbacks[(int) $frame->channel][$class][$i]);
                                    }
                                }
                            }
                        }

                        $this->socket = null;
                    }
                );
            }
        );
    }

    public function heartbeat(int $interval): void
    {
        $milliseconds = $interval * 1000;

        $this->heartbeatWatcherId = Loop::repeat(
            $milliseconds,
            function(string $watcherId) use ($milliseconds)
            {
                if($this->socket === null)
                {
                    Loop::cancel($watcherId);

                    return;
                }

                $currentTime = Loop::now();
                $lastWrite   = $this->lastWrite ?: $currentTime;

                /** @var int $nextHeartbeat */
                $nextHeartbeat = $lastWrite + $milliseconds;

                if($currentTime >= $nextHeartbeat)
                {
                    yield $this->write((new Buffer)
                        ->appendUint8(8)
                        ->appendUint16(0)
                        ->appendUint32(0)
                        ->appendUint8(206)
                    );
                }

                unset($currentTime, $lastWrite, $nextHeartbeat);
            }
        );
    }

    public function close(): void
    {
        $this->callbacks = [];

        if($this->heartbeatWatcherId !== null)
        {
            Loop::cancel($this->heartbeatWatcherId);

            $this->heartbeatWatcherId = null;
        }

        if($this->socket !== null)
        {
            $this->socket->close();
        }
    }
}
