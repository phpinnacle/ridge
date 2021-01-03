<?php
/**
 * This file is part of PHPinnacle/Ridge.
 *
 * (c) PHPinnacle Team <dev@phpinnacle.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PHPinnacle\Ridge\Protocol;

use PHPinnacle\Ridge\Buffer;
use PHPinnacle\Ridge\Constants;

class ContentHeaderFrame extends AbstractFrame
{
    private const FLAG_CONTENT_TYPE = 0x8000;
    private const FLAG_CONTENT_ENCODING = 0x4000;
    private const FLAG_HEADERS = 0x2000;
    private const FLAG_DELIVERY_MODE = 0x1000;
    private const FLAG_PRIORITY = 0x0800;
    private const FLAG_CORRELATION_ID = 0x0400;
    private const FLAG_REPLY_TO = 0x0200;
    private const FLAG_EXPIRATION = 0x0100;
    private const FLAG_MESSAGE_ID = 0x0080;
    private const FLAG_TIMESTAMP = 0x0040;
    private const FLAG_TYPE = 0x0020;
    private const FLAG_USER_ID = 0x0010;
    private const FLAG_APP_ID = 0x0008;
    private const FLAG_CLUSTER_ID = 0x0004;

    /**
     * @var int
     */
    public $classId = Constants::CLASS_BASIC;

    /**
     * @var int
     */
    public $weight = 0;

    /**
     * @var int
     */
    public $bodySize;

    /**
     * @var int
     */
    public $flags = 0;

    /**
     * @var string|null
     */
    public $contentType;

    /**
     * @var string|null
     */
    public $contentEncoding;

    /**
     * @var array
     */
    public $headers;

    /**
     * @var int|null
     */
    public $deliveryMode;

    /**
     * @var int|null
     */
    public $priority;

    /**
     * @var string|null
     */
    public $correlationId;

    /**
     * @var string|null
     */
    public $replyTo;

    /**
     * @var string|null
     */
    public $expiration;

    /**
     * @var string|null
     */
    public $messageId;

    /**
     * @var \DateTimeInterface|null
     */
    public $timestamp;

    /**
     * @var string|null
     */
    public $typeHeader;

    /**
     * @var string|null
     */
    public $userId;

    /**
     * @var string|null
     */
    public $appId;

    /**
     * @var string|null
     */
    public $clusterId;

    public function __construct()
    {
        parent::__construct(Constants::FRAME_HEADER);
    }

    /**
     * @throws \PHPinnacle\Buffer\BufferOverflow
     */
    public static function unpack(Buffer $buffer): self
    {
        $self = new self;

        $self->classId = $buffer->consumeUint16();
        $self->weight = $buffer->consumeUint16();
        $self->bodySize = $buffer->consumeUint64();
        $self->flags = $flags = $buffer->consumeUint16();

        if ($flags & self::FLAG_CONTENT_TYPE) {
            $self->contentType = $buffer->consumeString();
        }

        if ($flags & self::FLAG_CONTENT_ENCODING) {
            $self->contentEncoding = $buffer->consumeString();
        }

        if ($flags & self::FLAG_HEADERS) {
            $self->headers = $buffer->consumeTable();
        }

        if ($flags & self::FLAG_DELIVERY_MODE) {
            $self->deliveryMode = $buffer->consumeUint8();
        }

        if ($flags & self::FLAG_PRIORITY) {
            $self->priority = $buffer->consumeUint8();
        }

        if ($flags & self::FLAG_CORRELATION_ID) {
            $self->correlationId = $buffer->consumeString();
        }

        if ($flags & self::FLAG_REPLY_TO) {
            $self->replyTo = $buffer->consumeString();
        }

        if ($flags & self::FLAG_EXPIRATION) {
            $self->expiration = $buffer->consumeString();
        }

        if ($flags & self::FLAG_MESSAGE_ID) {
            $self->messageId = $buffer->consumeString();
        }

        if ($flags & self::FLAG_TIMESTAMP) {
            $self->timestamp = $buffer->consumeTimestamp();
        }

        if ($flags & self::FLAG_TYPE) {
            $self->typeHeader = $buffer->consumeString();
        }

        if ($flags & self::FLAG_USER_ID) {
            $self->userId = $buffer->consumeString();
        }

        if ($flags & self::FLAG_APP_ID) {
            $self->appId = $buffer->consumeString();
        }

        if ($flags & self::FLAG_CLUSTER_ID) {
            $self->clusterId = $buffer->consumeString();
        }

        return $self;
    }

    public function pack(): Buffer
    {
        $buffer = new Buffer;
        $buffer
            ->appendUint16($this->classId)
            ->appendUint16($this->weight)
            ->appendUint64($this->bodySize);

        $flags = $this->flags;

        $buffer->appendUint16($flags);

        if ($flags & self::FLAG_CONTENT_TYPE && $this->contentType !== null) {
            $buffer->appendString($this->contentType);
        }

        if ($flags & self::FLAG_CONTENT_ENCODING && $this->contentEncoding !== null) {
            $buffer->appendString($this->contentEncoding);
        }

        if ($flags & self::FLAG_HEADERS) {
            $buffer->appendTable($this->headers);
        }

        if ($flags & self::FLAG_DELIVERY_MODE && $this->deliveryMode !== null) {
            $buffer->appendUint8($this->deliveryMode);
        }

        if ($flags & self::FLAG_PRIORITY && $this->priority) {
            $buffer->appendUint8($this->priority);
        }

        if ($flags & self::FLAG_CORRELATION_ID && $this->correlationId !== null) {
            $buffer->appendString($this->correlationId);
        }

        if ($flags & self::FLAG_REPLY_TO && $this->replyTo !== null) {
            $buffer->appendString($this->replyTo);
        }

        if ($flags & self::FLAG_EXPIRATION && $this->expiration !== null) {
            $buffer->appendString($this->expiration);
        }

        if ($flags & self::FLAG_MESSAGE_ID && $this->messageId !== null) {
            $buffer->appendString($this->messageId);
        }

        if ($flags & self::FLAG_TIMESTAMP && $this->timestamp !== null) {
            $buffer->appendTimestamp($this->timestamp);
        }

        if ($flags & self::FLAG_TYPE && $this->typeHeader !== null) {
            $buffer->appendString($this->typeHeader);
        }

        if ($flags & self::FLAG_USER_ID && $this->userId !== null) {
            $buffer->appendString($this->userId);
        }

        if ($flags & self::FLAG_APP_ID && $this->appId !== null) {
            $buffer->appendString($this->appId);
        }

        if ($flags & self::FLAG_CLUSTER_ID && $this->clusterId !== null) {
            $buffer->appendString($this->clusterId);
        }

        return $buffer;
    }

    public function toArray(): array
    {
        $headers = $this->headers ?: [];

        if ($this->contentType !== null) {
            $headers['content-type'] = $this->contentType;
        }

        if ($this->contentEncoding !== null) {
            $headers['content-encoding'] = $this->contentEncoding;
        }

        if ($this->deliveryMode !== null) {
            $headers['delivery-mode'] = $this->deliveryMode;
        }

        if ($this->priority !== null) {
            $headers['priority'] = $this->priority;
        }

        if ($this->correlationId !== null) {
            $headers['correlation-id'] = $this->correlationId;
        }

        if ($this->replyTo !== null) {
            $headers['reply-to'] = $this->replyTo;
        }

        if ($this->expiration !== null) {
            $headers['expiration'] = $this->expiration;
        }

        if ($this->messageId !== null) {
            $headers['message-id'] = $this->messageId;
        }

        if ($this->timestamp !== null) {
            $headers['timestamp'] = $this->timestamp;
        }

        if ($this->typeHeader !== null) {
            $headers['type'] = $this->typeHeader;
        }

        if ($this->userId !== null) {
            $headers['user-id'] = $this->userId;
        }

        if ($this->appId !== null) {
            $headers['app-id'] = $this->appId;
        }

        if ($this->clusterId !== null) {
            $headers['cluster-id'] = $this->clusterId;
        }

        return $headers;
    }
}
