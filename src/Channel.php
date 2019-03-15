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
use Amp\Promise;

final class Channel
{
    private const
        STATE_READY     = 1,
        STATE_OPEN      = 2,
        STATE_CLOSING   = 3,
        STATE_CLOSED    = 4,
        STATE_ERROR     = 5
    ;

    private const
        MODE_REGULAR       = 1, // Regular AMQP guarantees of published messages delivery.
        MODE_TRANSACTIONAL = 2, // Messages are published after 'tx.commit'.
        MODE_CONFIRM       = 3  // Broker sends asynchronously 'basic.ack's for delivered messages.
    ;

    /**
     * @var int
     */
    private $id;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var Settings
     */
    private $settings;

    /**
     * @var int
     */
    private $state = self::STATE_READY;

    /**
     * @var int
     */
    private $mode = self::MODE_REGULAR;

    /**
     * @var Consumer
     */
    private $consumer;

    /**
     * @var int
     */
    private $deliveryTag = 0;

    /**
     * @param int        $id
     * @param Connection $connection
     * @param Settings   $settings
     */
    public function __construct(int $id, Connection $connection, Settings $settings)
    {
        $this->id         = $id;
        $this->connection = $connection;
        $this->settings   = $settings;
        $this->consumer   = new Consumer($this);

    }

    /**
     * @return int
     */
    public function id(): int
    {
        return $this->id;
    }

    /**
     * @param string $outOfBand
     *
     * @return Promise<void>
     */
    public function open(string $outOfBand = ''): Promise
    {
        return call(function () use ($outOfBand) {
            if ($this->state !== self::STATE_READY) {
                throw new Exception\ChannelException("Trying to open not ready channel #{$this->id}.");
            }

            yield $this->connection->write((new Buffer)
                ->appendUint8(1)
                ->appendUint16($this->id)
                ->appendUint32(5 + \strlen($outOfBand))
                ->appendUint16(20)
                ->appendUint16(10)
                ->appendString($outOfBand)
                ->appendUint8(206)
            );

            yield $this->await(Protocol\ChannelOpenOkFrame::class);

            $this->state = self::STATE_OPEN;
        });
    }

    /**
     * @param int    $code
     * @param string $reason
     * 
     * @return Promise<void>
     */
    public function close(int $code = 0, string $reason = ''): Promise
    {
        return call(function () use ($code, $reason) {
            if ($this->state === self::STATE_CLOSED) {
                throw new Exception\ChannelException("Trying to close already closed channel #{$this->id}.");
            }

            if ($this->state === self::STATE_CLOSING) {
                return;
            }

            $this->state = self::STATE_CLOSING;

            $this->consumer->stop();

            yield $this->connection->write((new Buffer)
                ->appendUint8(1)
                ->appendUint16($this->id)
                ->appendUint32(11 + \strlen($reason))
                ->appendUint16(20)
                ->appendUint16(40)
                ->appendInt16($code)
                ->appendString($reason)
                ->appendInt16(0)
                ->appendInt16(0)
                ->appendUint8(206)
            );

            yield $this->await(Protocol\ChannelCloseOkFrame::class);

            $this->state = self::STATE_CLOSED;
        });
    }

    /**
     * @param int  $prefetchSize
     * @param int  $prefetchCount
     * @param bool $global
     *
     * @return Promise<Protocol\BasicQosOkFrame>
     */
    public function qos(int $prefetchSize = 0, int $prefetchCount = 0, bool $global = false): Promise
    {
        return call(function () use ($prefetchSize, $prefetchCount, $global) {
            yield $this->connection->write((new Buffer)
                ->appendUint8(1)
                ->appendUint16($this->id)
                ->appendUint32(11)
                ->appendUint16(60)
                ->appendUint16(10)
                ->appendInt32($prefetchSize)
                ->appendInt16($prefetchCount)
                ->appendBits([$global])
                ->appendUint8(206)
            );

            return $this->await(Protocol\BasicQosOkFrame::class);
        });
    }

    /**
     * @param callable $callback
     * @param string   $queue
     * @param string   $consumerTag
     * @param bool     $noLocal
     * @param bool     $noAck
     * @param bool     $exclusive
     * @param bool     $noWait
     * @param array    $arguments
     *
     * @return Promise<bool|Protocol\BasicConsumeOkFrame>
     */
    public function consume
    (
        callable $callback,
        string $queue = '',
        string $consumerTag = '',
        bool $noLocal = false,
        bool $noAck = false,
        bool $exclusive = false,
        bool $noWait = false,
        array $arguments = []
    ) : Promise
    {
        $flags = [$noLocal, $noAck, $exclusive, $noWait];

        return call(function () use ($callback, $queue, $consumerTag, $flags, $noWait, $arguments) {
            $buffer = new Buffer;
            $buffer
                ->appendUint16(60)
                ->appendUint16(20)
                ->appendInt16(0)
                ->appendString($queue)
                ->appendString($consumerTag)
                ->appendBits($flags)
                ->appendTable($arguments)
            ;

            $result = (bool) yield $this->connection->method($this->id, $buffer);

            if ($noWait === false) {
                /** @var Protocol\BasicConsumeOkFrame $result */
                $result = yield $this->await(Protocol\BasicConsumeOkFrame::class);

                if ('' === $consumerTag) {
                    $consumerTag = $result->consumerTag;
                }
            }

            $this->startConsuming();

            $this->consumer->listen($consumerTag, $callback);

            return $result;
        });
    }

    /**
     * @param string $consumerTag
     * @param bool   $noWait
     *
     * @return Promise<bool|Protocol\BasicCancelOkFrame>
     */
    public function cancel(string $consumerTag, bool $noWait = false): Promise
    {
        return call(function () use ($consumerTag, $noWait) {
            $result = (bool) yield $this->connection->write((new Buffer)
                ->appendUint8(1)
                ->appendUint16($this->id)
                ->appendUint32(6 + \strlen($consumerTag))
                ->appendUint16(60)
                ->appendUint16(30)
                ->appendString($consumerTag)
                ->appendBits([$noWait])
                ->appendUint8(206)
            );

            if ($noWait === false) {
                $result = yield $this->await(Protocol\BasicCancelOkFrame::class);
            }

            $this->consumer->cancel($consumerTag);

            return $result;
        });
    }

    /**
     * @param Message $message
     * @param bool    $multiple
     *
     * @return Promise<bool>
     */
    public function ack(Message $message, bool $multiple = false): Promise
    {
        return call(function () use ($message, $multiple) {
            $result = yield $this->connection->write((new Buffer)
                ->appendUint8(1)
                ->appendUint16($this->id)
                ->appendUint32(13)
                ->appendUint16(60)
                ->appendUint16(80)
                ->appendInt64($message->deliveryTag())
                ->appendBits([$multiple])
                ->appendUint8(206)
            );

            return (bool) $result;
        });
    }

    /**
     * @param Message $message
     * @param bool    $multiple
     * @param bool    $requeue
     *
     * @return Promise<bool>
     */
    public function nack(Message $message, bool $multiple = false, bool $requeue = true): Promise
    {
        return call(function () use ($message, $multiple, $requeue) {
            $result = yield $this->connection->write((new Buffer)
                ->appendUint8(1)
                ->appendUint16($this->id)
                ->appendUint32(13)
                ->appendUint16(60)
                ->appendUint16(120)
                ->appendInt64($message->deliveryTag())
                ->appendBits([$multiple, $requeue])
                ->appendUint8(206)
            );

            return (bool) $result;
        });
    }

    /**
     * @param Message $message
     * @param bool    $requeue
     *
     * @return Promise<bool>
     */
    public function reject(Message $message, bool $requeue = true): Promise
    {
        return call(function () use ($message, $requeue) {
            $result = $this->connection->write((new Buffer)
                ->appendUint8(1)
                ->appendUint16($this->id)
                ->appendUint32(13)
                ->appendUint16(60)
                ->appendUint16(90)
                ->appendInt64($message->deliveryTag())
                ->appendBits([$requeue])
                ->appendUint8(206)
            );

            return (bool) $result;
        });
    }

    /**
     * @param bool $requeue
     *
     * @return Promise<Protocol\BasicRecoverOkFrame>
     */
    public function recover(bool $requeue = false): Promise
    {
        return call(function () use ($requeue) {
            $this->connection->write((new Buffer)
                ->appendUint8(1)
                ->appendUint16($this->id)
                ->appendUint32(5)
                ->appendUint16(60)
                ->appendUint16(110)
                ->appendBits([$requeue])
                ->appendUint8(206)
            );

            return $this->await(Protocol\BasicRecoverOkFrame::class);
        });
    }

    /**
     * @param bool $requeue
     *
     * @return Promise<bool>
     * @deprecated This method is deprecated in favour of the synchronous Recover/Recover-Ok.
     */
    public function recoverAsync(bool $requeue = false): Promise
    {
        return $this->connection->write((new Buffer)
            ->appendUint8(1)
            ->appendUint16($this->id)
            ->appendUint32(5)
            ->appendUint16(60)
            ->appendUint16(100)
            ->appendBits([$requeue])
            ->appendUint8(206)
        );
    }

    /**
     * @param string $queue
     * @param bool   $noAck
     *
     * @return Promise<Message|null>
     */
    public function get(string $queue = '', bool $noAck = false): Promise
    {
        static $getting = false;

        return call(function () use ($queue, $noAck, &$getting) {
            if ($getting) {
                throw Exception\ChannelException::getInProgress();
            }

            $getting = true;

            yield $this->connection->write((new Buffer)
                ->appendUint8(1)
                ->appendUint16($this->id)
                ->appendUint32(8 + \strlen($queue))
                ->appendUint16(60)
                ->appendUint16(70)
                ->appendInt16(0)
                ->appendString($queue)
                ->appendBits([$noAck])
                ->appendUint8(206)
            );

            $frame = yield Promise\first([
                $this->await(Protocol\BasicGetOkFrame::class),
                $this->await(Protocol\BasicGetEmptyFrame::class)
            ]);

            if ($frame instanceof Protocol\BasicGetEmptyFrame) {
                $getting = false;

                return null;
            }

            /** @var Protocol\ContentHeaderFrame $header */
            $header = yield $this->await(Protocol\ContentHeaderFrame::class);

            $buffer    = new Buffer;
            $remaining = $header->bodySize;

            while ($remaining > 0) {
                /** @var Protocol\ContentBodyFrame $body */
                $body = yield $this->await(Protocol\ContentBodyFrame::class);

                $buffer->append($body->payload);

                $remaining -= $body->size;

                if ($remaining < 0) {
                    $this->state = self::STATE_ERROR;

                    throw Exception\ChannelException::bodyOverflow($remaining);
                }
            }

            $getting = false;

            return new Message(
                $buffer->flush(),
                $frame->exchange,
                $frame->routingKey,
                null,
                $frame->deliveryTag,
                $frame->redelivered,
                $header->toArray()
            );
        });
    }

    /**
     * @param string $body
     * @param string $exchange
     * @param string $routingKey
     * @param array  $headers
     * @param bool   $mandatory
     * @param bool   $immediate
     *
     * @return Promise<int>
     */
    public function publish
    (
        string $body,
        string $exchange = '',
        string $routingKey = '',
        array $headers = [],
        bool $mandatory = false,
        bool $immediate = false
    ): Promise
    {
        return call(function () use ($body, $exchange, $routingKey, $headers, $mandatory, $immediate) {
            yield $this->doPublish($body, $exchange, $routingKey, $headers, $mandatory, $immediate);

            return ++$this->deliveryTag;
        });
    }

    /**
     * @return Promise<Protocol\TxSelectOkFrame>
     */
    public function txSelect(): Promise
    {
        return call(function () {
            if ($this->mode !== self::MODE_REGULAR) {
                throw new Exception\ChannelException("Channel not in regular mode, cannot change to transactional mode.");
            }

            yield $this->connection->write((new Buffer)
                ->appendUint8(1)
                ->appendUint16($this->id)
                ->appendUint32(4)
                ->appendUint16(90)
                ->appendUint16(10)
                ->appendUint8(206)
            );

            $frame = yield $this->await(Protocol\TxSelectOkFrame::class);

            $this->mode = self::MODE_TRANSACTIONAL;

            return $frame;
        });
    }

    /**
     * @return Promise<Protocol\TxCommitOkFrame>
     */
    public function txCommit(): Promise
    {
        return call(function () {
            if ($this->mode !== self::MODE_TRANSACTIONAL) {
                throw new Exception\ChannelException("Channel not in transactional mode, cannot call 'tx.commit'.");
            }

            yield $this->connection->write((new Buffer)
                ->appendUint8(1)
                ->appendUint16($this->id)
                ->appendUint32(4)
                ->appendUint16(90)
                ->appendUint16(20)
                ->appendUint8(206)
            );

            return $this->await(Protocol\TxCommitOkFrame::class);
        });
    }

    /**
     * @return Promise<Protocol\TxRollbackOkFrame>
     */
    public function txRollback(): Promise
    {
        return call(function () {
            if ($this->mode !== self::MODE_TRANSACTIONAL) {
                throw new Exception\ChannelException("Channel not in transactional mode, cannot call 'tx.rollback'.");
            }

            yield $this->connection->write((new Buffer)
                ->appendUint8(1)
                ->appendUint16($this->id)
                ->appendUint32(4)
                ->appendUint16(90)
                ->appendUint16(30)
                ->appendUint8(206)
            );

            return $this->await(Protocol\TxRollbackOkFrame::class);
        });
    }

    /**
     * @param bool $noWait
     *
     * @return Promise<bool|Protocol\ConfirmSelectOkFrame>
     */
    public function confirmSelect(bool $noWait = false)
    {
        return call(function () use ($noWait) {
            if ($this->mode !== self::MODE_REGULAR) {
                throw new Exception\ChannelException("Channel not in regular mode, cannot change to transactional mode.");
            }

            $result = (bool) yield $this->connection->write((new Buffer)
                ->appendUint8(1)
                ->appendUint16($this->id)
                ->appendUint32(5)
                ->appendUint16(85)
                ->appendUint16(10)
                ->appendBits([$noWait])
                ->appendUint8(206)
            );

            if ($noWait === false) {
                $result = yield $this->await(Protocol\ConfirmSelectOkFrame::class);
            }

            $this->mode = self::MODE_CONFIRM;
            $this->deliveryTag = 0;

            return $result;
        });
    }

    /**
     * @param string $queue
     * @param bool   $passive
     * @param bool   $durable
     * @param bool   $exclusive
     * @param bool   $autoDelete
     * @param bool   $noWait
     * @param array  $arguments
     *
     * @return Promise<bool|Protocol\QueueDeclareOkFrame>
     */
    public function queueDeclare
    (
        string $queue = '',
        bool $passive = false,
        bool $durable = false,
        bool $exclusive = false,
        bool $autoDelete = false,
        bool $noWait = false,
        array $arguments = []
    ): Promise
    {
        $flags = [$passive, $durable, $exclusive, $autoDelete, $noWait];

        return call(function () use ($queue, $flags, $noWait, $arguments) {
            $buffer = new Buffer;
            $buffer
                ->appendUint16(50)
                ->appendUint16(10)
                ->appendInt16(0)
                ->appendString($queue)
                ->appendBits($flags)
                ->appendTable($arguments)
            ;

            $result = yield $this->connection->method($this->id, $buffer);

            return $noWait ? (bool) $result : $this->await(Protocol\QueueDeclareOkFrame::class);
        });
    }

    /**
     * @param string $queue
     * @param string $exchange
     * @param string $routingKey
     * @param bool   $noWait
     * @param array  $arguments
     *
     * @return Promise<bool|Protocol\QueueBindOkFrame>
     */
    public function queueBind
    (
        string $queue = '',
        string $exchange = '',
        string $routingKey = '',
        bool $noWait = false,
        array $arguments = []
    ): Promise
    {
        return call(function () use ($queue, $exchange, $routingKey, $noWait, $arguments) {
            $buffer = new Buffer;
            $buffer
                ->appendUint16(50)
                ->appendUint16(20)
                ->appendInt16(0)
                ->appendString($queue)
                ->appendString($exchange)
                ->appendString($routingKey)
                ->appendBits([$noWait])
                ->appendTable($arguments)
            ;

            $result = yield $this->connection->method($this->id, $buffer);

            return $noWait ? (bool) $result : $this->await(Protocol\QueueBindOkFrame::class);
        });
    }

    /**
     * @param string $queue
     * @param string $exchange
     * @param string $routingKey
     * @param array  $arguments
     *
     * @return Promise<bool>
     */
    public function queueUnbind
    (
        string $queue = '',
        string $exchange = '',
        string $routingKey = '',
        array $arguments = []
    ): Promise
    {
        return call(function () use ($queue, $exchange, $routingKey, $arguments) {
            $buffer = new Buffer;
            $buffer
                ->appendUint16(50)
                ->appendUint16(50)
                ->appendInt16(0)
                ->appendString($queue)
                ->appendString($exchange)
                ->appendString($routingKey)
                ->appendTable($arguments)
            ;

            yield $this->connection->method($this->id, $buffer);

            return $this->await(Protocol\QueueUnbindOkFrame::class);
        });
    }

    /**
     * @param string $queue
     * @param bool   $noWait
     *
     * @return Promise<bool|Protocol\QueuePurgeOkFrame>
     */
    public function queuePurge(string $queue = '', bool $noWait = false): Promise
    {
        return call(function () use ($queue, $noWait) {
            $buffer = new Buffer;
            $buffer
                ->appendUint8(1)
                ->appendUint16($this->id)
                ->appendUint32(8 + \strlen($queue))
                ->appendUint16(50)
                ->appendUint16(30)
                ->appendInt16(0)
                ->appendString($queue)
                ->appendBits([$noWait])
                ->appendUint8(206)
            ;

            $result = yield $this->connection->write($buffer);

            return $noWait ? (bool) $result : $this->await(Protocol\QueuePurgeOkFrame::class);
        });
    }

    /**
     * @param string $queue
     * @param bool   $ifUnused
     * @param bool   $ifEmpty
     * @param bool   $noWait
     *
     * @return Promise<bool|Protocol\QueueDeleteOkFrame>
     */
    public function queueDelete
    (
        string $queue = '',
        bool $ifUnused = false,
        bool $ifEmpty = false,
        bool $noWait = false
    ): Promise
    {
        $flags = [$ifUnused, $ifEmpty, $noWait];

        return call(function () use ($queue, $flags, $noWait) {
            $buffer = new Buffer;
            $buffer
                ->appendUint8(1)
                ->appendUint16($this->id)
                ->appendUint32(8 + strlen($queue))
                ->appendUint16(50)
                ->appendUint16(40)
                ->appendInt16(0)
                ->appendString($queue)
                ->appendBits($flags)
                ->appendUint8(206)
            ;

            $result = yield $this->connection->write($buffer);

            return $noWait ? (bool) $result : $this->await(Protocol\QueueDeleteOkFrame::class);
        });
    }

    /**
     * @param string $exchange
     * @param string $exchangeType
     * @param bool   $passive
     * @param bool   $durable
     * @param bool   $autoDelete
     * @param bool   $internal
     * @param bool   $noWait
     * @param array  $arguments
     *
     * @return Promise<bool|Protocol\ExchangeDeclareOkFrame>
     */
    public function exchangeDeclare
    (
        string $exchange,
        string $exchangeType = 'direct',
        bool $passive = false,
        bool $durable = false,
        bool $autoDelete = false,
        bool $internal = false,
        bool $noWait = false,
        array $arguments = []
    ): Promise
    {
        $flags = [$passive, $durable, $autoDelete, $internal, $noWait];

        return call(function () use ($exchange, $exchangeType, $flags, $noWait, $arguments) {
            $buffer = new Buffer;
            $buffer
                ->appendUint16(40)
                ->appendUint16(10)
                ->appendInt16(0)
                ->appendString($exchange)
                ->appendString($exchangeType)
                ->appendBits($flags)
                ->appendTable($arguments)
            ;

            $result = yield $this->connection->method($this->id, $buffer);

            return $noWait ? (bool) $result : $this->await(Protocol\ExchangeDeclareOkFrame::class);
        });
    }

    /**
     * @param string $destination
     * @param string $source
     * @param string $routingKey
     * @param bool   $noWait
     * @param array  $arguments
     *
     * @return Promise<bool|Protocol\ExchangeBindOkFrame>
     */
    public function exchangeBind
    (
        string $destination,
        string $source,
        string $routingKey = '',
        bool $noWait = false,
        array $arguments = []
    ): Promise
    {
        return call(function () use ($destination, $source, $routingKey, $noWait, $arguments) {
            $buffer = new Buffer;
            $buffer
                ->appendUint16(40)
                ->appendUint16(30)
                ->appendInt16(0)
                ->appendString($destination)
                ->appendString($source)
                ->appendString($routingKey)
                ->appendBits([$noWait])
                ->appendTable($arguments)
            ;

            $result = yield $this->connection->method($this->id, $buffer);

            return $noWait ? (bool) $result : $this->await(Protocol\ExchangeBindOkFrame::class);
        });
    }

    /**
     * @param string $destination
     * @param string $source
     * @param string $routingKey
     * @param bool   $noWait
     * @param array  $arguments
     *
     * @return Promise<bool|Protocol\ExchangeUnbindOkFrame>
     */
    public function exchangeUnbind
    (
        string $destination,
        string $source,
        string $routingKey = '',
        bool $noWait = false,
        array $arguments = []
    ): Promise
    {
        return call(function () use ($destination, $source, $routingKey, $noWait, $arguments) {
            $buffer = new Buffer;
            $buffer
                ->appendUint16(40)
                ->appendUint16(40)
                ->appendInt16(0)
                ->appendString($destination)
                ->appendString($source)
                ->appendString($routingKey)
                ->appendBits([$noWait])
                ->appendTable($arguments)
            ;

            $result = yield $this->connection->method($this->id, $buffer);

            return $noWait ? (bool) $result : $this->await(Protocol\ExchangeUnbindOkFrame::class);
        });
    }

    /**
     * @param string $exchange
     * @param bool   $unused
     * @param bool   $noWait
     *
     * @return Promise<Protocol\ExchangeDeleteOkFrame>
     */
    public function exchangeDelete(string $exchange, bool $unused = false, bool $noWait = false): Promise
    {
        return call(function () use ($exchange, $unused, $noWait) {
            $buffer = new Buffer;
            $buffer
                ->appendUint8(1)
                ->appendUint16($this->id)
                ->appendUint32(8 + \strlen($exchange))
                ->appendUint16(40)
                ->appendUint16(20)
                ->appendInt16(0)
                ->appendString($exchange)
                ->appendBits([$unused, $noWait])
                ->appendUint8(206)
            ;

            $result = yield $this->connection->write($buffer);

            return $noWait ? (bool) $result : $this->await(Protocol\ExchangeDeleteOkFrame::class);
        });
    }

    /**
     * @param string $body
     * @param string $exchange
     * @param string $routingKey
     * @param array  $headers
     * @param bool   $mandatory
     * @param bool   $immediate
     *
     * @return Promise
     */
    public function doPublish
    (
        string $body,
        string $exchange = '',
        string $routingKey = '',
        array $headers = [],
        bool $mandatory = false,
        bool $immediate = false
    ): Promise
    {
        $flags = 0;
        $contentType = '';
        $contentEncoding = '';
        $type = '';
        $replyTo = '';
        $expiration = '';
        $messageId = '';
        $correlationId = '';
        $userId = '';
        $appId = '';
        $clusterId = '';

        $deliveryMode = null;
        $priority = null;
        $timestamp = null;

        $headersBuffer = null;

        $buffer = new Buffer;
        $buffer
            ->appendUint8(1)
            ->appendUint16($this->id)
            ->appendUint32(9 + \strlen($exchange) + \strlen($routingKey))
            ->appendUint16(60)
            ->appendUint16(40)
            ->appendInt16(0)
            ->appendString($exchange)
            ->appendString($routingKey)
            ->appendBits([$mandatory, $immediate])
            ->appendUint8(206)
        ;

        $size = 14;

        if (isset($headers['content-type'])) {
            $flags |= 32768;
            $contentType = $headers['content-type'];
            $size += 1 + \strlen($contentType);
            unset($headers['content-type']);
        }

        if (isset($headers['content-encoding'])) {
            $flags |= 16384;
            $contentEncoding = $headers['content-encoding'];
            $size += 1 + \strlen($contentEncoding);
            unset($headers['content-encoding']);
        }

        if (isset($headers['delivery-mode'])) {
            $flags |= 4096;
            $deliveryMode = (int) $headers['delivery-mode'];
            $size += 1;
            unset($headers['delivery-mode']);
        }

        if (isset($headers['priority'])) {
            $flags |= 2048;
            $priority = (int) $headers['priority'];
            $size += 1;
            unset($headers['priority']);
        }

        if (isset($headers['correlation-id'])) {
            $flags |= 1024;
            $correlationId = $headers['correlation-id'];
            $size += 1 + \strlen($correlationId);
            unset($headers['correlation-id']);
        }

        if (isset($headers['reply-to'])) {
            $flags |= 512;
            $replyTo = $headers['reply-to'];
            $size += 1 + \strlen($replyTo);
            unset($headers['reply-to']);
        }

        if (isset($headers['expiration'])) {
            $flags |= 256;
            $expiration = $headers['expiration'];
            $size += 1 + \strlen($expiration);
            unset($headers['expiration']);
        }

        if (isset($headers['message-id'])) {
            $flags |= 128;
            $messageId = $headers['message-id'];
            $size += 1 + \strlen($messageId);
            unset($headers['message-id']);
        }

        if (isset($headers['timestamp'])) {
            $flags |= 64;
            $timestamp = $headers['timestamp'];
            $size += 8;
            unset($headers['timestamp']);
        }

        if (isset($headers['type'])) {
            $flags |= 32;
            $type = $headers['type'];
            $size += 1 + \strlen($type);
            unset($headers['type']);
        }

        if (isset($headers['user-id'])) {
            $flags |= 16;
            $userId = $headers['user-id'];
            $size += 1 + \strlen($userId);
            unset($headers['user-id']);
        }

        if (isset($headers['app-id'])) {
            $flags |= 8;
            $appId = $headers['app-id'];
            $size += 1 + \strlen($appId);
            unset($headers['app-id']);
        }

        if (isset($headers['cluster-id'])) {
            $flags |= 4;
            $clusterId = $headers['cluster-id'];
            $size += 1 + \strlen($clusterId);
            unset($headers['cluster-id']);
        }

        if (!empty($headers)) {
            $flags |= 8192;
            $headersBuffer = new Buffer;
            $headersBuffer->appendTable($headers);
            $size += $headersBuffer->size();
        }

        $buffer
            ->appendUint8(2)
            ->appendUint16($this->id)
            ->appendUint32($size)
            ->appendUint16(60)
            ->appendUint16(0)
            ->appendUint64(\strlen($body))
            ->appendUint16($flags)
        ;

        if ($flags & 32768) {
            $buffer->appendString($contentType);
        }

        if ($flags & 16384) {
            $buffer->appendString($contentEncoding);
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
            $buffer->appendString($correlationId);
        }

        if ($flags & 512) {
            $buffer->appendString($replyTo);
        }

        if ($flags & 256) {
            $buffer->appendString($expiration);
        }

        if ($flags & 128) {
            $buffer->appendString($messageId);
        }

        if ($flags & 64) {
            $buffer->appendTimestamp($timestamp);
        }

        if ($flags & 32) {
            $buffer->appendString($type);
        }

        if ($flags & 16) {
            $buffer->appendString($userId);
        }

        if ($flags & 8) {
            $buffer->appendString($appId);
        }

        if ($flags & 4) {
            $buffer->appendString($clusterId);
        }

        $buffer->appendUint8(206);

        if (!empty($body)) {
            $chunks = \str_split($body, $this->settings->maxFrame());

            foreach ($chunks as $chunk) {
                $buffer
                    ->appendUint8(3)
                    ->appendUint16($this->id)
                    ->appendUint32(\strlen($chunk))
                    ->append($chunk)
                    ->appendUint8(206)
                ;
            }
        }

        return $this->connection->write($buffer);
    }
    
    /**
     * @param string $frame
     *
     * @return Promise<Protocol\AbstractFrame>
     */
    private function await(string $frame): Promise
    {
        $deferred = new Deferred;

        $this->connection->subscribe($this->id, $frame, function (Protocol\AbstractFrame $frame) use ($deferred) {
            $deferred->resolve($frame);

            return true;
        });

        return $deferred->promise();
    }

    /**
     * @return void
     */
    private function startConsuming(): void
    {
        $this->connection->subscribe($this->id, Protocol\BasicDeliverFrame::class, [$this->consumer, 'onDeliver']);
        $this->connection->subscribe($this->id, Protocol\ContentHeaderFrame::class, [$this->consumer, 'onHeader']);
        $this->connection->subscribe($this->id, Protocol\ContentBodyFrame::class, [$this->consumer, 'onBody']);
    }
}
