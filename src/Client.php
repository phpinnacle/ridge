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

use function Amp\call;
use Amp\Deferred;
use Amp\Loop;
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
     * @var ProtocolReader
     */
    private $reader;

    /**
     * @var Buffer
     */
    private $readBuffer;

    /**
     * Read from stream watcher
     *
     * @var string|null
     */
    private $readWatcher;

    /**
     * @var ProtocolWriter
     */
    private $writer;

    /**
     * @var Buffer
     */
    private $writeBuffer;

    /**
     * Write to stream watcher
     *
     * @var string|null
     */
    private $writeWatcher;

    /**
     * Heartbeat watcher
     *
     * @var string|null
     */
    private $heartbeatWatcher;

    /**
     * @var Promise
     */
    private $flushPromise;

    /**
     * @var Promise|null
     */
    private $disconnectPromise;

    /**
     * @var Channel[]
     */
    private $channels = [];

    /**
     * @var int
     */
    private $nextChannelId = 1;

    /**
     * @var float
     */
    private $lastWrite = 0.0;

    /**
     * @var float
     */
    private $lastRead = 0.0;

    /**
     * @var int
     */
    private $frameMax = 0xFFFF;

    /**
     * @var int
     */
    private $channelMax = 0xFFFF;

    /**
     * @var resource
     */
    private $stream;

    /**
     * @var ConnectionAwaiter
     */
    private $awaitConnection;

    /**
     * @var ChannelAwaiter
     */
    private $awaitChannel;

    /**
     * @var array
     */
    private $cache = [];

    /**
     * @param Config $config
     */
    public function __construct(Config $config)
    {
        $this->config = $config;

        $this->init();
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

            $this->writeBuffer->append('AMQP');
            $this->writeBuffer->appendUint8(0);
            $this->writeBuffer->appendUint8(0);
            $this->writeBuffer->appendUint8(9);
            $this->writeBuffer->appendUint8(1);

            yield $this->flushWriteBuffer();

            $this->readWatcher = Loop::onReadable($this->getStream(), function() {
                yield $this->onDataAvailable();
            });

            return $this->doConnect();
        });
    }

    /**
     * @param int    $replyCode
     * @param string $replyText
     *
     * @return Promise<void>
     */
    public function disconnect($replyCode = 0, $replyText = ''): Promise
    {
        return call(function() use ($replyCode, $replyText) {
            if ($this->state === self::STATE_DISCONNECTING) {
                return;
            }

            if ($this->state !== self::STATE_CONNECTED) {
                throw new \RuntimeException('Client is not connected');
            }

            $this->state = self::STATE_DISCONNECTING;

            if ($replyCode === 0) {
                foreach($this->channels as $channel) {
                    yield $channel->close($replyCode, $replyText);
                }
            }

            yield $this->connectionClose($replyCode, $replyText, 0, 0);

            $this->cancelReadWatcher();
            $this->cancelWriteWatcher();
            $this->cancelHeartbeatWatcher();
            $this->closeStream();

            $this->init();
        });
    }

    /**
     * @return Promise<Channel>
     */
    public function channel(): Promise
    {
        return call(function() {
            try {
                $channelId = $this->findChannelId();

                $this->channels[$channelId] = new Channel($this, $channelId);

                yield $this->channelOpen($channelId);

                return $this->channels[$channelId];
            } catch(\Throwable $throwable) {
                throw new \RuntimeException('channel.open unexpected response', $throwable->getCode(), $throwable);
            }
        });
    }

    public function publish($channel, $body, array $headers = [], $exchange = '', $routingKey = '', $mandatory = false, $immediate = false)
    {
        $buffer = $this->writeBuffer;
        $ck = serialize([$channel, $headers, $exchange, $routingKey, $mandatory, $immediate]);
        $c = isset($this->cache[$ck]) ? $this->cache[$ck] : null;

        $flags = 0; $off0 = 0; $len0 = 0; $off1 = 0; $len1 = 0; $contentTypeLength = null; $contentType = null; $contentEncodingLength = null; $contentEncoding = null; $headersBuffer = null; $deliveryMode = null; $priority = null; $correlationIdLength = null; $correlationId = null; $replyToLength = null; $replyTo = null; $expirationLength = null; $expiration = null; $messageIdLength = null; $messageId = null; $timestamp = null; $typeLength = null; $type = null; $userIdLength = null; $userId = null; $appIdLength = null; $appId = null; $clusterIdLength = null; $clusterId = null;

        if ($c) {
            $buffer->append($c[0]);
        } else {
            $off0 = $buffer->getLength();
            $buffer->appendUint8(1);
            $buffer->appendUint16($channel);
            $buffer->appendUint32(9 + strlen($exchange) + strlen($routingKey));
            $buffer->appendUint16(60);
            $buffer->appendUint16(40);
            $buffer->appendInt16(0);
            $buffer->appendUint8(strlen($exchange)); $buffer->append($exchange);
            $buffer->appendUint8(strlen($routingKey)); $buffer->append($routingKey);
            $buffer->appendBits([$mandatory, $immediate]);
            $buffer->appendUint8(206);

            $s = 14;

            if (isset($headers['content-type'])) {
                $flags |= 32768;
                $contentType = $headers['content-type'];
                $s += 1;
                $s += $contentTypeLength = strlen($contentType);
                unset($headers['content-type']);
            }

            if (isset($headers['content-encoding'])) {
                $flags |= 16384;
                $contentEncoding = $headers['content-encoding'];
                $s += 1;
                $s += $contentEncodingLength = strlen($contentEncoding);
                unset($headers['content-encoding']);
            }

            if (isset($headers['delivery-mode'])) {
                $flags |= 4096;
                $deliveryMode = (int) $headers['delivery-mode'];
                $s += 1;
                unset($headers['delivery-mode']);
            }

            if (isset($headers['priority'])) {
                $flags |= 2048;
                $priority = (int) $headers['priority'];
                $s += 1;
                unset($headers['priority']);
            }

            if (isset($headers['correlation-id'])) {
                $flags |= 1024;
                $correlationId = $headers['correlation-id'];
                $s += 1;
                $s += $correlationIdLength = strlen($correlationId);
                unset($headers['correlation-id']);
            }

            if (isset($headers['reply-to'])) {
                $flags |= 512;
                $replyTo = $headers['reply-to'];
                $s += 1;
                $s += $replyToLength = strlen($replyTo);
                unset($headers['reply-to']);
            }

            if (isset($headers['expiration'])) {
                $flags |= 256;
                $expiration = $headers['expiration'];
                $s += 1;
                $s += $expirationLength = strlen($expiration);
                unset($headers['expiration']);
            }

            if (isset($headers['message-id'])) {
                $flags |= 128;
                $messageId = $headers['message-id'];
                $s += 1;
                $s += $messageIdLength = strlen($messageId);
                unset($headers['message-id']);
            }

            if (isset($headers['timestamp'])) {
                $flags |= 64;
                $timestamp = $headers['timestamp'];
                $s += 8;
                unset($headers['timestamp']);
            }

            if (isset($headers['type'])) {
                $flags |= 32;
                $type = $headers['type'];
                $s += 1;
                $s += $typeLength = strlen($type);
                unset($headers['type']);
            }

            if (isset($headers['user-id'])) {
                $flags |= 16;
                $userId = $headers['user-id'];
                $s += 1;
                $s += $userIdLength = strlen($userId);
                unset($headers['user-id']);
            }

            if (isset($headers['app-id'])) {
                $flags |= 8;
                $appId = $headers['app-id'];
                $s += 1;
                $s += $appIdLength = strlen($appId);
                unset($headers['app-id']);
            }

            if (isset($headers['cluster-id'])) {
                $flags |= 4;
                $clusterId = $headers['cluster-id'];
                $s += 1;
                $s += $clusterIdLength = strlen($clusterId);
                unset($headers['cluster-id']);
            }

            if (!empty($headers)) {
                $flags |= 8192;
                $headersBuffer = new Buffer();
                $headersBuffer->appendTable($headers);
                $s += $headersBuffer->getLength();
            }

            $buffer->appendUint8(2);
            $buffer->appendUint16($channel);
            $buffer->appendUint32($s);
            $buffer->appendUint16(60);
            $buffer->appendUint16(0);
            $len0 = $buffer->getLength() - $off0;
        }

        $buffer->appendUint64(strlen($body));

        if ($c) {
            $buffer->append($c[1]);
        } else {
            $off1 = $buffer->getLength();

            $buffer->appendUint16($flags);

            if ($flags & 32768) {
                $buffer->appendUint8($contentTypeLength); $buffer->append($contentType);
            }
            if ($flags & 16384) {
                $buffer->appendUint8($contentEncodingLength); $buffer->append($contentEncoding);
            }
            if ($flags & 8192) {
                $buffer->append($headersBuffer);
            }
            if ($flags & 4096) {
                $buffer->appendUint8($deliveryMode);
            }
            if ($flags & 2048) {
                $buffer->appendUint8($priority);
            }
            if ($flags & 1024) {
                $buffer->appendUint8($correlationIdLength); $buffer->append($correlationId);
            }
            if ($flags & 512) {
                $buffer->appendUint8($replyToLength); $buffer->append($replyTo);
            }
            if ($flags & 256) {
                $buffer->appendUint8($expirationLength); $buffer->append($expiration);
            }
            if ($flags & 128) {
                $buffer->appendUint8($messageIdLength); $buffer->append($messageId);
            }
            if ($flags & 64) {
                $buffer->appendTimestamp($timestamp);
            }
            if ($flags & 32) {
                $buffer->appendUint8($typeLength); $buffer->append($type);
            }
            if ($flags & 16) {
                $buffer->appendUint8($userIdLength); $buffer->append($userId);
            }
            if ($flags & 8) {
                $buffer->appendUint8($appIdLength); $buffer->append($appId);
            }
            if ($flags & 4) {
                $buffer->appendUint8($clusterIdLength); $buffer->append($clusterId);
            }

            $buffer->appendUint8(206);
            $len1 = $buffer->getLength() - $off1;
        }

        if (!$c) {
            $this->cache[$ck] = [$buffer->read($len0, $off0), $buffer->read($len1, $off1)];

            if (count($this->cache) > 100) {
                reset($this->cache);
                unset($this->cache[key($this->cache)]);
            }
        }

        for ($payloadMax = $this->frameMax - 8 /* frame preface and frame end */, $i = 0, $l = strlen($body); $i < $l; $i += $payloadMax) {
            $payloadSize = $l - $i; if ($payloadSize > $payloadMax) { $payloadSize = $payloadMax; }
            $buffer->appendUint8(3);
            $buffer->appendUint16($channel);
            $buffer->appendUint32($payloadSize);
            $buffer->append(substr($body, $i, $payloadSize));
            $buffer->appendUint8(206);
        }

        return $this->flushWriteBuffer();
    }

    /**
     * @param int    $channel
     * @param string $queue
     * @param string $consumerTag
     * @param bool   $noLocal
     * @param bool   $noAck
     * @param bool   $exclusive
     * @param bool   $nowait
     * @param array  $arguments
     *
     * @return Promise<Protocol\BasicConsumeOkFrame>
     */
    public function consume(
        int $channel, string $queue = '', string $consumerTag = '', bool $noLocal = false, bool $noAck = false,
        bool $exclusive = false, bool $nowait = false, array $arguments = []
    ) {
        $flags = [$noLocal, $noAck, $exclusive, $nowait];

        return call(function() use ($channel, $queue, $consumerTag, $flags, $arguments) {
            $buffer = new Buffer;
            $buffer->appendUint16(60);
            $buffer->appendUint16(20);
            $buffer->appendInt16(0);
            $buffer->appendUint8(\strlen($queue));
            $buffer->append($queue);
            $buffer->appendUint8(\strlen($consumerTag));
            $buffer->append($consumerTag);
            $buffer->appendBits($flags);
            $buffer->appendTable($arguments);

            $frame          = new Protocol\MethodFrame(60, 20);
            $frame->channel = $channel;
            $frame->size    = $buffer->getLength();
            $frame->payload = $buffer;

            $this->writer->appendFrame($frame, $this->writeBuffer);

            yield $this->flushWriteBuffer();

            unset($buffer, $frame);

            return $this->awaitChannel->awaitConsumeOk($channel);
        });
    }

    /**
     * @param int  $channel
     * @param int  $deliveryTag
     * @param bool $multiple
     *
     * @return Promise<bool>
     */
    public function ack(int $channel, int $deliveryTag = 0, bool $multiple = false): Promise
    {
        $buffer = $this->writeBuffer;
        $buffer->appendUint8(1);
        $buffer->appendUint16($channel);
        $buffer->appendUint32(13);
        $buffer->appendUint16(60);
        $buffer->appendUint16(80);
        $buffer->appendInt64($deliveryTag);
        $buffer->appendBits([$multiple]);
        $buffer->appendUint8(206);

        return $this->flushWriteBuffer();
    }

    /**
     * @param      $channel
     * @param int  $deliveryTag
     * @param bool $multiple
     * @param bool $requeue
     *
     * @return Promise<bool>
     */
    public function nack(int $channel, int $deliveryTag = 0, bool $multiple = false, bool $requeue = true)
    {
        $buffer = $this->writeBuffer;
        $buffer->appendUint8(1);
        $buffer->appendUint16($channel);
        $buffer->appendUint32(13);
        $buffer->appendUint16(60);
        $buffer->appendUint16(120);
        $buffer->appendInt64($deliveryTag);
        $buffer->appendBits([$multiple, $requeue]);
        $buffer->appendUint8(206);

        return $this->flushWriteBuffer();
    }

    public function reject($channel, $deliveryTag, $requeue = true)
    {
        $buffer = $this->writeBuffer;
        $buffer->appendUint8(1);
        $buffer->appendUint16($channel);
        $buffer->appendUint32(13);
        $buffer->appendUint16(60);
        $buffer->appendUint16(90);
        $buffer->appendInt64($deliveryTag);
        $buffer->appendBits([$requeue]);
        $buffer->appendUint8(206);

        return $this->flushWriteBuffer();
    }

    public function recover($channel, $requeue = false)
    {
        $buffer = $this->writeBuffer;
        $buffer->appendUint8(1);
        $buffer->appendUint16($channel);
        $buffer->appendUint32(5);
        $buffer->appendUint16(60);
        $buffer->appendUint16(100);
        $buffer->appendBits([$requeue]);
        $buffer->appendUint8(206);

        return $this->flushWriteBuffer();
    }

    /**
     * @param int    $channel
     * @param string $consumerTag
     * @param bool   $nowait
     *
     * @return Promise<Protocol\BasicCancelOkFrame>
     */
    public function cancel(int $channel, string $consumerTag, bool $nowait = false): Promise
    {
        return call(function () use ($channel, $consumerTag, $nowait) {
            $buffer = $this->writeBuffer;
            $buffer->appendUint8(1);
            $buffer->appendUint16($channel);
            $buffer->appendUint32(6 + \strlen($consumerTag));
            $buffer->appendUint16(60);
            $buffer->appendUint16(30);
            $buffer->appendUint8(\strlen($consumerTag));
            $buffer->append($consumerTag);
            $buffer->appendBits([$nowait]);
            $buffer->appendUint8(206);

            if ($nowait) {
                return $this->flushWriteBuffer();
            }

            yield $this->flushWriteBuffer();

            unset($buffer);

            return $this->awaitChannel->awaitCancelOk($channel);
        });
    }

    /**
     * @param int  $channel
     * @param int  $prefetchSize
     * @param int  $prefetchCount
     * @param bool $global
     *
     * @return Promise<Protocol\BasicQosOkFrame>
     */
    public function qos(int $channel, int $prefetchSize = 0, int $prefetchCount = 0, bool $global = false): Promise
    {
        return call(function () use ($channel, $prefetchSize, $prefetchCount, $global) {
            $buffer = $this->writeBuffer;
            $buffer->appendUint8(1);
            $buffer->appendUint16($channel);
            $buffer->appendUint32(11);
            $buffer->appendUint16(60);
            $buffer->appendUint16(10);
            $buffer->appendInt32($prefetchSize);
            $buffer->appendInt16($prefetchCount);
            $buffer->appendBits([$global]);
            $buffer->appendUint8(206);

            yield $this->flushWriteBuffer();

            unset($buffer);

            return $this->awaitChannel->awaitQosOk($channel);
        });
    }

    /**
     * @param string $virtualHost
     * @param string $capabilities
     * @param bool   $insist
     *
     * @return Promise<Protocol\ConnectionOpenOkFrame>
     */
    public function connectionOpen(string $virtualHost = '/', string $capabilities = '', bool $insist = false): Promise
    {
        return call(function () use ($virtualHost, $capabilities, $insist) {
            $buffer = $this->writeBuffer;
            $buffer->appendUint8(1);
            $buffer->appendUint16(0);
            $buffer->appendUint32(7 + \strlen($virtualHost) + \strlen($capabilities));
            $buffer->appendUint16(10);
            $buffer->appendUint16(40);
            $buffer->appendUint8(\strlen($virtualHost));
            $buffer->append($virtualHost);
            $buffer->appendUint8(\strlen($capabilities));
            $buffer->append($capabilities);
            $buffer->appendBits([$insist]);
            $buffer->appendUint8(206);

            yield $this->flushWriteBuffer();

            unset($buffer);

            return $this->awaitConnection->awaitConnectionOpenOk();
        });
    }

    /**
     * @param int    $replyCode
     * @param string $replyText
     * @param int    $closeClassId
     * @param int    $closeMethodId
     *
     * @return Promise<Protocol\ConnectionCloseOkFrame>
     */
    public function connectionClose(int $replyCode, string $replyText, int $closeClassId, int $closeMethodId): Promise
    {
        return call(function () use ($replyCode, $replyText, $closeClassId, $closeMethodId) {
            $buffer = $this->writeBuffer;
            $buffer->appendUint8(1);
            $buffer->appendUint16(0);
            $buffer->appendUint32(11 + \strlen($replyText));
            $buffer->appendUint16(10);
            $buffer->appendUint16(50);
            $buffer->appendInt16($replyCode);
            $buffer->appendUint8(\strlen($replyText));
            $buffer->append($replyText);
            $buffer->appendInt16($closeClassId);
            $buffer->appendInt16($closeMethodId);
            $buffer->appendUint8(206);

            yield $this->flushWriteBuffer();

            unset($buffer);

            return $this->awaitConnection->awaitConnectionCloseOk();
        });
    }

    /**
     * @param int    $channel
     * @param string $outOfBand
     *
     * @return Promise<Protocol\ChannelOpenOkFrame>
     */
    public function channelOpen(int $channel, string $outOfBand = ''): Promise
    {
        return call(function () use ($channel, $outOfBand) {
            $buffer = $this->writeBuffer;
            $buffer->appendUint8(1);
            $buffer->appendUint16($channel);
            $buffer->appendUint32(5 + \strlen($outOfBand));
            $buffer->appendUint16(20);
            $buffer->appendUint16(10);
            $buffer->appendUint8(\strlen($outOfBand));
            $buffer->append($outOfBand);
            $buffer->appendUint8(206);

            yield $this->flushWriteBuffer();

            unset($buffer);

            $frame = yield $this->awaitChannel->awaitChannelOpenOk($channel);

            return $this->qos(
                (int) $frame->channel,
                $this->config->qosSize(),
                $this->config->qosCount(),
                $this->config->qosGlobal()
            );
        });
    }

    /**
     * @param $channel
     * @param $replyCode
     * @param $replyText
     * @param $closeClassId
     * @param $closeMethodId
     *
     * @return Promise
     */
    public function channelClose($channel, $replyCode, $replyText, $closeClassId, $closeMethodId): Promise
    {
        return call(function () use ($channel, $replyCode, $replyText, $closeClassId, $closeMethodId) {
            $buffer = $this->writeBuffer;
            $buffer->appendUint8(1);
            $buffer->appendUint16($channel);
            $buffer->appendUint32(11 + \strlen($replyText));
            $buffer->appendUint16(20);
            $buffer->appendUint16(40);
            $buffer->appendInt16($replyCode);
            $buffer->appendUint8(\strlen($replyText));
            $buffer->append($replyText);
            $buffer->appendInt16($closeClassId);
            $buffer->appendInt16($closeMethodId);
            $buffer->appendUint8(206);

            return $this->flushWriteBuffer();
        });
    }

    /**
     * @param int    $channel
     * @param string $exchange
     * @param string $exchangeType
     * @param bool   $passive
     * @param bool   $durable
     * @param bool   $autoDelete
     * @param bool   $internal
     * @param bool   $nowait
     * @param array  $arguments
     *
     * @return Promise<Protocol\ExchangeDeclareOkFrame>
     */
    public function exchangeDeclare(
        int    $channel,
        string $exchange,
        string $exchangeType = 'direct',
        bool $passive = false,
        bool $durable = false,
        bool $autoDelete = false,
        bool $internal = false,
        bool $nowait = false,
        array $arguments = []
    ): Promise {
        $flags = [$passive, $durable, $autoDelete, $internal, $nowait];

        return call(function () use ($channel, $exchange, $exchangeType, $flags, $arguments) {
            $buffer = new Buffer;
            $buffer->appendUint16(40);
            $buffer->appendUint16(10);
            $buffer->appendInt16(0);
            $buffer->appendUint8(\strlen($exchange));
            $buffer->append($exchange);
            $buffer->appendUint8(\strlen($exchangeType));
            $buffer->append($exchangeType);
            $buffer->appendBits($flags);
            $buffer->appendTable($arguments);

            $frame          = new Protocol\MethodFrame(40, 10);
            $frame->channel = $channel;
            $frame->size    = $buffer->getLength();
            $frame->payload = $buffer;

            $this->writer->appendFrame($frame, $this->writeBuffer);

            yield $this->flushWriteBuffer();

            unset($buffer, $frame);

            return $this->awaitChannel->awaitExchangeDeclareOk($channel);
        });
    }

    /**
     * @param int    $channel
     * @param string $exchange
     * @param bool   $unused
     * @param bool   $nowait
     *
     * @return Promise<Protocol\ExchangeDeleteOkFrame>
     */
    public function exchangeDelete(int $channel, string $exchange, bool $unused = false, bool $nowait = false): Promise
    {
        return call(function () use ($channel, $exchange, $unused, $nowait) {
            $buffer = $this->writeBuffer;
            $buffer->appendUint8(1);
            $buffer->appendUint16($channel);
            $buffer->appendUint32(8 + \strlen($exchange));
            $buffer->appendUint16(40);
            $buffer->appendUint16(20);
            $buffer->appendInt16(0);
            $buffer->appendUint8(\strlen($exchange));
            $buffer->append($exchange);
            $buffer->appendBits([$unused, $nowait]);
            $buffer->appendUint8(206);

            yield $this->flushWriteBuffer();

            unset($buffer);

            return $this->awaitChannel->awaitExchangeDeleteOk($channel);
        });
    }

    /**
     * @param int $channel
     *
     * @return Promise
     */
    public function channelCloseOk(int $channel): Promise
    {
        $buffer = $this->writeBuffer;
        $buffer->appendUint8(1);
        $buffer->appendUint16($channel);
        $buffer->appendUint32(4);
        $buffer->appendUint16(20);
        $buffer->appendUint16(41);
        $buffer->appendUint8(206);

        return $this->flushWriteBuffer();
    }

    /**
     * @return Promise
     */
    public function connectionCloseOk(): Promise
    {
        $buffer = $this->writeBuffer;
        $buffer->appendUint8(1);
        $buffer->appendUint16(0);
        $buffer->appendUint32(4);
        $buffer->appendUint16(10);
        $buffer->appendUint16(51);
        $buffer->appendUint8(206);

        return $this->flushWriteBuffer();
    }

    /**
     * @return Promise<bool>
     */
    private function flushWriteBuffer(): Promise
    {
        /** @var Promise|null $flushWriteBufferPromise */
        $flushWriteBufferPromise = $this->flushPromise;

        if ($flushWriteBufferPromise !== null) {
            return $flushWriteBufferPromise;
        }

        $deferred = new Deferred();

        $this->writeWatcher = Loop::onWritable($this->getStream(), function() use ($deferred) {
            try {
                $this->write();

                if ($this->writeBuffer->isEmpty()) {
                    $this->cancelWriteWatcher();
                    $this->flushPromise = null;

                    $deferred->resolve(true);
                }
            } catch(\Exception $e) {
                $this->cancelWriteWatcher();
                $this->flushPromise = null;

                $deferred->fail($e);
            }
        });

        return $this->flushPromise = $deferred->promise();
    }

    /**
     * Execute connect
     *
     * @return Promise<void>
     */
    private function doConnect(): Promise
    {
        return call(function () {
            /** @var Protocol\ConnectionStartFrame $start */
            $start = yield $this->awaitConnection->awaitConnectionStart();

            yield $this->authResponse($start);

            /** @var Protocol\ConnectionTuneFrame $tune */
            $tune = yield $this->awaitConnection->awaitConnectionTune();

            $this->frameMax = $tune->frameMax;

            if ($tune->channelMax > 0) {
                $this->channelMax = $tune->channelMax;
            }

            yield $this->connectionTuneOk($tune->channelMax, $tune->frameMax, $this->config->heartbeat());
            yield $this->connectionOpen((string) ($this->options['vhost'] ?? '/'));

            $this->state = self::STATE_CONNECTED;

            $this->addHeartbeatTimer();
        });
    }

    /**
     * @param Protocol\ConnectionStartFrame $start
     *
     * @return Promise<bool>
     */
    private function authResponse(Protocol\ConnectionStartFrame $start): Promise
    {
        if (\strpos($start->mechanisms, "AMQPLAIN") === false) {
            throw new Exception\ClientException("Server does not support AMQPLAIN mechanism (supported: {$start->mechanisms}).");
        }

        $responseBuffer = new Buffer();

        $responseBuffer->appendTable([
            "LOGIN"    => $this->config->user(),
            "PASSWORD" => $this->config->password(),
        ]);

        $responseBuffer->discard(4);

        return $this->connectionStartOk([], "AMQPLAIN", $responseBuffer->read($responseBuffer->getLength()), "en_US");
    }

    /**
     * @param array  $properties
     * @param string $mechanism
     * @param string $response
     * @param string $locale
     *
     * @return Promise
     */
    private function connectionStartOk(array $properties, string $mechanism, string $response, string $locale = 'en_US'): Promise
    {
        $buffer = new Buffer;
        $buffer->appendUint16(10);
        $buffer->appendUint16(11);
        $buffer->appendTable($properties);
        $buffer->appendUint8(strlen($mechanism)); $buffer->append($mechanism);
        $buffer->appendUint32(strlen($response)); $buffer->append($response);
        $buffer->appendUint8(strlen($locale)); $buffer->append($locale);

        $frame = new Protocol\MethodFrame(10, 11);
        $frame->channel = 0;
        $frame->size    = $buffer->getLength();
        $frame->payload = $buffer;

        $this->writer->appendFrame($frame, $this->writeBuffer);

        return $this->flushWriteBuffer();
    }

    /**
     * @param int $channelMax
     * @param int $frameMax
     * @param int $heartbeat
     *
     * @return Promise
     */
    private function connectionTuneOk($channelMax = 0, $frameMax = 0, $heartbeat = 0): Promise
    {
        $buffer = $this->writeBuffer;
        $buffer->appendUint8(1);
        $buffer->appendUint16(0);
        $buffer->appendUint32(12);
        $buffer->appendUint16(10);
        $buffer->appendUint16(31);
        $buffer->appendInt16($channelMax);
        $buffer->appendInt32($frameMax);
        $buffer->appendInt16((int) ($heartbeat * 1000));
        $buffer->appendUint8(206);

        return $this->flushWriteBuffer();
    }

    /**
     * Add timer for heartbeat
     *
     * @return void
     */
    private function addHeartbeatTimer(): void
    {
        /** @var float $seconds */
        $seconds = $this->config->heartbeat();

        $this->heartbeatWatcher = Loop::repeat((int) ($seconds * 1000), function() {
            yield $this->onHeartbeat();
        });
    }

    /**
     * @return void
     */
    private function cancelHeartbeatWatcher(): void
    {
        if ($this->heartbeatWatcher !== null) {
            Loop::cancel($this->heartbeatWatcher);

            $this->heartbeatWatcher = null;
        }
    }

    /**
     * @return void
     */
    private function cancelReadWatcher(): void
    {
        if ($this->readWatcher !== null) {
            Loop::cancel($this->readWatcher);

            $this->readWatcher = null;
        }
    }

    /**
     * @return void
     */
    private function cancelWriteWatcher(): void
    {
        if ($this->writeWatcher !== null) {
            Loop::cancel($this->writeWatcher);

            $this->writeWatcher = null;
        }
    }

    /**
     * @return int
     */
    private function findChannelId(): int
    {
        // first check in range [next, max] ...
        for (
            $channelId = $this->nextChannelId;
            $channelId <= $this->channelMax;
            ++$channelId
        ) {
            if (!isset($this->channels[$channelId])) {
                $this->nextChannelId = $channelId + 1;

                return $channelId;
            }
        }

        // then check in range [min, next) ...
        for (
            $channelId = 1;
            $channelId < $this->nextChannelId;
            ++$channelId
        ) {
            if (!isset($this->channels[$channelId])) {
                $this->nextChannelId = $channelId + 1;

                return $channelId;
            }
        }

        throw new Exception\ClientException("No available channels");
    }

    /**
     * Initializes instance.
     */
    private function init()
    {
        $this->flushPromise      = null;
        $this->disconnectPromise = null;

        $this->readBuffer  = new Buffer;
        $this->writeBuffer = new Buffer;

        $this->reader = new ProtocolReader;
        $this->writer = new ProtocolWriter;

        $this->awaitConnection = new ConnectionAwaiter($this);
        $this->awaitChannel    = new ChannelAwaiter($this);
    }

    /**
     * Creates stream according to options passed in constructor.
     *
     * @return resource
     */
    protected function getStream()
    {
        if ($this->stream === null) {
            // see https://github.com/nrk/predis/blob/v1.0/src/Connection/StreamConnection.php
            $uri = "tcp://{$this->config->host()}:{$this->config->port()}";
            $flags = STREAM_CLIENT_CONNECT | STREAM_CLIENT_ASYNC_CONNECT;

//            if (isset($this->options["persistent"]) && !!$this->options["persistent"]) {
//                $flags |= STREAM_CLIENT_PERSISTENT;
//
//                if (!isset($this->options["path"])) {
//                    throw new Exception\ClientException("If you need persistent connection, you have to specify 'path' option.");
//                }
//
//                $uri .= (strpos($this->options["path"], "/") === 0) ? $this->options["path"] : "/" . $this->options["path"];
//            }

            $this->stream = @stream_socket_client($uri, $errno, $errstr, $this->config->timeout(), $flags);

            if (!$this->stream) {
                throw new Exception\ClientException(
                    "Could not connect to {$this->config->host()}:{$this->config->port()}: {$errstr}.",
                    $errno
                );
            }
//
//            if (isset($this->options["read_write_timeout"])) {
//                $readWriteTimeout = (float) $this->options["read_write_timeout"];
//                if ($readWriteTimeout < 0) {
//                    $readWriteTimeout = -1;
//                }
//                $readWriteTimeoutSeconds = floor($readWriteTimeout);
//                $readWriteTimeoutMicroseconds = ($readWriteTimeout - $readWriteTimeoutSeconds) * 10e6;
//                stream_set_timeout($this->stream, $readWriteTimeoutSeconds, $readWriteTimeoutMicroseconds);
//            }
//
//            if (isset($this->options["tcp_nodelay"]) && function_exists("socket_import_stream")) {
//                $socket = \socket_import_stream($this->stream);
//                \socket_set_option($socket, SOL_TCP, TCP_NODELAY, (int) $this->options["tcp_nodelay"]);
//            }

            \stream_set_blocking($this->stream, false);
        }

        return $this->stream;
    }

    /**
     * Closes stream.
     */
    private function closeStream()
    {
        @fclose($this->stream);

        $this->stream = null;
    }

    /**
     * Reads data from stream into {@link readBuffer}.
     */
    private function read()
    {
        $s = @\fread($this->stream, $this->frameMax);

        if ($s === false) {
            $info = \stream_get_meta_data($this->stream);

            if (isset($info["timed_out"]) && $info["timed_out"]) {
                throw new Exception\ClientException("Timeout reached while reading from stream.");
            }
        }

        if (@\feof($this->stream)) {
            throw new Exception\ClientException("Broken pipe or closed connection.");
        }

        $this->readBuffer->append($s);

        $this->lastRead = \microtime(true);
    }

    /**
     * Writes data from {@link writeBuffer} to stream.
     */
    private function write()
    {
        if (($written = @\fwrite($this->getStream(), $this->writeBuffer->read($this->writeBuffer->getLength()))) === false) {
            throw new Exception\ClientException("Could not write data to socket.");
        }

        if ($written === 0) {
            throw new Exception\ClientException("Broken pipe or closed connection.");
        }

        \fflush($this->getStream()); // flush internal PHP buffers

        $this->writeBuffer->discard($written);

        $this->lastWrite = \microtime(true);
    }

    /**
     * @inheritdoc
     *
     * @return Promise It does not return any result
     */
    private function onDataAvailable(): Promise
    {
        return call(function() {
            $this->read();

            while(($frame = $this->reader->consumeFrame($this->readBuffer)) !== null) {
                if (yield $this->awaitConnection->receive($frame)) {
                    continue;
                }

                if (yield $this->awaitChannel->receive($frame)) {
                    continue;
                }

                if ($frame->channel === 0) {
                    yield $this->onFrameReceived($frame);
                } else {
                    if (!isset($this->channels[$frame->channel])) {
                        throw new Exception\ClientException("Received frame #{$frame->type} on closed channel #{$frame->channel}.");
                    }

                    if (!$frame instanceof Protocol\ChannelCloseFrame) {
                        $this->channels[$frame->channel]->onFrameReceived($frame);
                    }
                }

                unset($frame);
            }
        });
    }

    /**
     * Callback after connection-level frame has been received.
     *
     * @param Protocol\AbstractFrame $frame
     *
     * @return Promise
     */
    private function onFrameReceived(Protocol\AbstractFrame $frame): Promise
    {
        return call(function () use ($frame) {
            if ($frame instanceof Protocol\MethodFrame) {
                if ($frame instanceof Protocol\ConnectionCloseFrame) {
                    throw new Exception\ClientException("Connection closed by server: " . $frame->replyText, $frame->replyCode);
                } else {
                    throw new Exception\ClientException("Unhandled method frame " . \get_class($frame) . ".");
                }
            } elseif ($frame instanceof Protocol\ContentHeaderFrame) {
                yield $this->disconnect(Constants::STATUS_UNEXPECTED_FRAME, "Got header frame on connection channel (#0).");
            } elseif ($frame instanceof Protocol\ContentBodyFrame) {
                yield $this->disconnect(Constants::STATUS_UNEXPECTED_FRAME, "Got body frame on connection channel (#0).");
            } elseif ($frame instanceof Protocol\HeartbeatFrame) {
                $this->lastRead = \microtime(true);
            } else {
                throw new Exception\ClientException("Unhandled frame " . \get_class($frame) . ".");
            }
        });
    }

    /**
     * @return Promise It does not return any result
     */
    private function onHeartbeat(): Promise
    {
        return call(function() {
            /** @var float $currentTime */
            $currentTime = \microtime(true);

            /** @var float|null $lastWrite */
            $lastWrite = $this->lastWrite;

            if (null === $lastWrite)
            {
                $lastWrite = $currentTime;
            }

            /** @var float $nextHeartbeat */
            $nextHeartbeat = $lastWrite + $this->config->heartbeat();

            if ($currentTime >= $nextHeartbeat)
            {
                $this->writer->appendFrame(new Protocol\HeartbeatFrame, $this->writeBuffer);

                yield $this->flushWriteBuffer();
            }

            unset($currentTime, $lastWrite, $nextHeartbeat);
        });
    }
}
