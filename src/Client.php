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

use function Amp\asyncCall;
use function Amp\call;
use Amp\Deferred;
use Amp\Promise;

final class Client
{
    private const
        STATE_NOT_CONNECTED = 0,
        STATE_CONNECTING    = 1,
        STATE_CONNECTED     = 2,
        STATE_DISCONNECTING = 3
    ;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var int
     */
    private $state = self::STATE_NOT_CONNECTED;

    /**
     * @var Channel[]
     */
    private $channels = [];

    /**
     * @var int
     */
    private $nextChannelId = 1;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @param Config $config
     */
    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * @param string $dsn
     *
     * @return self
     */
    public static function create(string $dsn): self
    {
        return new self(Config::parse($dsn));
    }

    /**
     * @return Promise<void>
     */
    public function connect(): Promise
    {
        return call(function () {
            if ($this->state !== self::STATE_NOT_CONNECTED) {
                throw new \RuntimeException('Client already connected/connecting');
            }

            $this->state = self::STATE_CONNECTING;

            $this->connection = new Connection($this->config->uri());

            yield $this->connection->open(
                $this->config->timeout(),
                $this->config->tcpAttempts(),
                $this->config->tcpNoDelay()
            );

            $buffer = new Buffer;
            $buffer
                ->append('AMQP')
                ->appendUint8(0)
                ->appendUint8(0)
                ->appendUint8(9)
                ->appendUint8(1)
            ;

            yield $this->connection->write($buffer);

            yield $this->connectionStart();
            yield $this->connectionTune();
            yield $this->connectionOpen();

            asyncCall(function () {
                yield $this->await(Protocol\ConnectionCloseFrame::class);

                $buffer = new Buffer;
                $buffer
                    ->appendUint8(1)
                    ->appendUint16(0)
                    ->appendUint32(4)
                    ->appendUint16(10)
                    ->appendUint16(51)
                    ->appendUint8(206)
                ;

                $this->connection->write($buffer);
                $this->connection->close();
            });

            $this->state = self::STATE_CONNECTED;
        });
    }

    /**
     * @param int    $code
     * @param string $reason
     *
     * @return Promise<void>
     */
    public function disconnect(int $code = 0, string $reason = ''): Promise
    {
        return call(function() use ($code, $reason) {
            if (\in_array($this->state, [self::STATE_NOT_CONNECTED, self::STATE_DISCONNECTING])) {
                return;
            }

            if ($this->state !== self::STATE_CONNECTED) {
                throw new Exception\ClientException('Client is not connected');
            }

            $this->state = self::STATE_DISCONNECTING;

            if ($code === 0) {
                $promises = [];

                foreach($this->channels as $id => $channel) {
                    $promises[] = $channel->close($code, $reason);
                }

                yield $promises;
            }

            yield $this->connectionClose($code, $reason);

            $this->connection->close();

            $this->state = self::STATE_NOT_CONNECTED;
        });
    }

    /**
     * @return Promise<Channel>
     */
    public function channel(): Promise
    {
        return call(function() {
            if ($this->state !== self::STATE_CONNECTED) {
                throw new Exception\ClientException('Client is not connected');
            }

            try {
                $id = $this->findChannelId();
                $channel = new Channel($id, $this->connection, new Settings($this->config->maxFrame()));

                $this->channels[$id] = $channel;

                yield $channel->open();
                yield $channel->qos($this->config->qosSize(), $this->config->qosCount(), $this->config->qosGlobal());

                asyncCall(function () use ($id) {
                    $frame = yield Promise\first([
                        $this->await(Protocol\ChannelCloseFrame::class, $id),
                        $this->await(Protocol\ChannelCloseOkFrame::class, $id)
                    ]);

                    $this->connection->cancel($id);

                    if ($frame instanceof Protocol\ChannelCloseFrame) {
                        $buffer = new Buffer;
                        $buffer
                            ->appendUint8(1)
                            ->appendUint16($id)
                            ->appendUint32(4)
                            ->appendUint16(20)
                            ->appendUint16(41)
                            ->appendUint8(206)
                        ;

                        yield $this->connection->write($buffer);
                    }

                    unset($this->channels[$id]);
                });

                return $channel;
            } catch(\Throwable $throwable) {
                throw new Exception\ClientException('channel.open unexpected response', $throwable->getCode(), $throwable);
            }
        });
    }

    /**
     * @return bool
     */
    public function isConnected(): bool
    {
        return $this->state === self::STATE_CONNECTED;
    }

    /**
     * @return Promise
     */
    private function connectionStart(): Promise
    {
        return call(function () {
            /** @var Protocol\ConnectionStartFrame $start */
            $start = yield $this->await(Protocol\ConnectionStartFrame::class);

            if (\strpos($start->mechanisms, "AMQPLAIN") === false) {
                throw new Exception\ClientException("Server does not support AMQPLAIN mechanism (supported: {$start->mechanisms}).");
            }

            $buffer = new Buffer;
            $buffer
                ->appendTable([
                    "LOGIN"    => $this->config->user(),
                    "PASSWORD" => $this->config->password(),
                ])
                ->discard(4)
            ;

            $frameBuffer = new Buffer;
            $frameBuffer
                ->appendUint16(10)
                ->appendUint16(11)
                ->appendTable([])
                ->appendString("AMQPLAIN")
                ->appendText((string) $buffer)
                ->appendString("en_US")
            ;

            return $this->connection->method(0, 10, 11, $frameBuffer);
        });
    }

    /**
     * @return Promise
     */
    private function connectionTune(): Promise
    {
        return call(function () {
            /** @var Protocol\ConnectionTuneFrame $tune */
            $tune = yield $this->await(Protocol\ConnectionTuneFrame::class);

            $heartbeat = $this->config->heartbeat();

            if ($heartbeat !== 0) {
                $heartbeat = \min($heartbeat, $tune->heartbeat);
            }

            $channelMax = \min($this->config->maxChannel(), $tune->channelMax);
            $frameMax   = \min($this->config->maxFrame(), $tune->frameMax);

            $buffer = new Buffer;
            $buffer
                ->appendUint8(1)
                ->appendUint16(0)
                ->appendUint32(12)
                ->appendUint16(10)
                ->appendUint16(31)
                ->appendInt16($channelMax)
                ->appendInt32($frameMax)
                ->appendInt16($heartbeat)
                ->appendUint8(206);

            yield $this->connection->write($buffer);

            if ($heartbeat > 0) {
                $this->connection->heartbeat($heartbeat);
            }
        });
    }

    /**
     * @return Promise<Protocol\ConnectionOpenOkFrame>
     */
    private function connectionOpen(): Promise
    {
        return call(function () {
            $vhost = $this->config->vhost();
            $capabilities = '';
            $insist = false;

            $buffer = new Buffer;
            $buffer
                ->appendUint8(1)
                ->appendUint16(0)
                ->appendUint32(7 + \strlen($vhost) + \strlen($capabilities))
                ->appendUint16(10)
                ->appendUint16(40)
                ->appendString($vhost)
                ->appendString($capabilities) // TODO: process server capabilities
                ->appendBits([$insist])
                ->appendUint8(206)
            ;

            yield $this->connection->write($buffer);

            return $this->await(Protocol\ConnectionOpenOkFrame::class);
        });
    }

    /**
     * @param int    $code
     * @param string $reason
     *
     * @return Promise
     */
    private function connectionClose(int $code, string $reason): Promise
    {
        return call(function () use ($code, $reason) {
            $buffer = new Buffer;
            $buffer
                ->appendUint8(1)
                ->appendUint16(0)
                ->appendUint32(11 + \strlen($reason))
                ->appendUint16(10)
                ->appendUint16(50)
                ->appendInt16($code)
                ->appendString($reason)
                ->appendInt16(0)
                ->appendInt16(0)
                ->appendUint8(206)
            ;

            yield $this->connection->write($buffer);

            return $this->await(Protocol\ConnectionCloseOkFrame::class);
        });
    }

    /**
     * @return int
     */
    private function findChannelId(): int
    {
        // first check in range [next, max] ...
        for ($id = $this->nextChannelId; $id <= $this->config->maxChannel(); ++$id) {
            if (!isset($this->channels[$id])) {
                $this->nextChannelId = $id + 1;

                return $id;
            }
        }

        // then check in range [min, next) ...
        for ($id = 1; $id < $this->nextChannelId; ++$id) {
            if (!isset($this->channels[$id])) {
                $this->nextChannelId = $id + 1;

                return $id;
            }
        }

        throw new Exception\ClientException("No available channels");
    }

    /**
     * @param string $frame
     * @param int    $channel
     *
     * @return Promise
     */
    private function await(string $frame, int $channel = 0): Promise
    {
        $deferred = new Deferred;

        $this->connection->subscribe($channel, $frame, function (Protocol\AbstractFrame $frame) use ($deferred) {
            $deferred->resolve($frame);

            return true;
        });

        return $deferred->promise();
    }
}
