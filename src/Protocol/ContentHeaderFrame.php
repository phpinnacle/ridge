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
    const
        FLAG_CONTENT_TYPE     = 0x8000,
        FLAG_CONTENT_ENCODING = 0x4000,
        FLAG_HEADERS          = 0x2000,
        FLAG_DELIVERY_MODE    = 0x1000,
        FLAG_PRIORITY         = 0x0800,
        FLAG_CORRELATION_ID   = 0x0400,
        FLAG_REPLY_TO         = 0x0200,
        FLAG_EXPIRATION       = 0x0100,
        FLAG_MESSAGE_ID       = 0x0080,
        FLAG_TIMESTAMP        = 0x0040,
        FLAG_TYPE             = 0x0020,
        FLAG_USER_ID          = 0x0010,
        FLAG_APP_ID           = 0x0008,
        FLAG_CLUSTER_ID       = 0x0004
    ;

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
     * @var string
     */
    public $contentType;

    /**
     * @var string
     */
    public $contentEncoding;

    /**
     * @var array
     */
    public $headers;

    /**
     * @var int
     */
    public $deliveryMode;

    /**
     * @var int
     */
    public $priority;

    /**
     * @var string
     */
    public $correlationId;

    /**
     * @var string
     */
    public $replyTo;

    /**
     * @var string
     */
    public $expiration;

    /**
     * @var string
     */
    public $messageId;

    /**
     * @var \DateTimeInterface
     */
    public $timestamp;

    /**
     * @var string
     */
    public $typeHeader;

    /**
     * @var string
     */
    public $userId;

    /**
     * @var string
     */
    public $appId;

    /**
     * @var string
     */
    public $clusterId;

    public function __construct()
    {
        parent::__construct(Constants::FRAME_HEADER);
    }

    /**
     * @param Buffer $buffer
     *
     * @return ContentHeaderFrame
     */
    public static function unpack(Buffer $buffer): self
    {
        $self = new self;

        $self->classId  = $buffer->consumeUint16();
        $self->weight   = $buffer->consumeUint16();
        $self->bodySize = $buffer->consumeUint64();
        $self->flags    = $flags = $buffer->consumeUint16();
    
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
    
    /**
     * @return Buffer
     */
    public function pack(): Buffer
    {
        $buffer = new Buffer;
        $buffer
            ->appendUint16($this->classId)
            ->appendUint16($this->weight)
            ->appendUint64($this->bodySize)
        ;
    
        $flags = $this->flags;
    
        $buffer->appendUint16($flags);
    
        if ($flags & ContentHeaderFrame::FLAG_CONTENT_TYPE) {
            $buffer->appendString($this->contentType);
        }
    
        if ($flags & ContentHeaderFrame::FLAG_CONTENT_ENCODING) {
            $buffer->appendString($this->contentEncoding);
        }
    
        if ($flags & ContentHeaderFrame::FLAG_HEADERS) {
            $buffer->appendTable($this->headers);
        }
    
        if ($flags & ContentHeaderFrame::FLAG_DELIVERY_MODE) {
            $buffer->appendUint8($this->deliveryMode);
        }
    
        if ($flags & ContentHeaderFrame::FLAG_PRIORITY) {
            $buffer->appendUint8($this->priority);
        }
    
        if ($flags & ContentHeaderFrame::FLAG_CORRELATION_ID) {
            $buffer->appendString($this->correlationId);
        }
    
        if ($flags & ContentHeaderFrame::FLAG_REPLY_TO) {
            $buffer->appendString($this->replyTo);
        }
    
        if ($flags & ContentHeaderFrame::FLAG_EXPIRATION) {
            $buffer->appendString($this->expiration);
        }
    
        if ($flags & ContentHeaderFrame::FLAG_MESSAGE_ID) {
            $buffer->appendString($this->messageId);
        }
    
        if ($flags & ContentHeaderFrame::FLAG_TIMESTAMP) {
            $buffer->appendTimestamp($this->timestamp);
        }
    
        if ($flags & ContentHeaderFrame::FLAG_TYPE) {
            $buffer->appendString($this->typeHeader);
        }
    
        if ($flags & ContentHeaderFrame::FLAG_USER_ID) {
            $buffer->appendString($this->userId);
        }
    
        if ($flags & ContentHeaderFrame::FLAG_APP_ID) {
            $buffer->appendString($this->appId);
        }
    
        if ($flags & ContentHeaderFrame::FLAG_CLUSTER_ID) {
            $buffer->appendString($this->clusterId);
        }
    
        return $buffer;
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        $headers = $this->headers ?: [];

        if ($this->contentType !== null) {
            $headers["content-type"] = $this->contentType;
        }

        if ($this->contentEncoding !== null) {
            $headers["content-encoding"] = $this->contentEncoding;
        }

        if ($this->deliveryMode !== null) {
            $headers["delivery-mode"] = $this->deliveryMode;
        }

        if ($this->priority !== null) {
            $headers["priority"] = $this->priority;
        }

        if ($this->correlationId !== null) {
            $headers["correlation-id"] = $this->correlationId;
        }

        if ($this->replyTo !== null) {
            $headers["reply-to"] = $this->replyTo;
        }

        if ($this->expiration !== null) {
            $headers["expiration"] = $this->expiration;
        }

        if ($this->messageId !== null) {
            $headers["message-id"] = $this->messageId;
        }

        if ($this->timestamp !== null) {
            $headers["timestamp"] = $this->timestamp;
        }

        if ($this->typeHeader !== null) {
            $headers["type"] = $this->typeHeader;
        }

        if ($this->userId !== null) {
            $headers["user-id"] = $this->userId;
        }

        if ($this->appId !== null) {
            $headers["app-id"] = $this->appId;
        }

        if ($this->clusterId !== null) {
            $headers["cluster-id"] = $this->clusterId;
        }

        return $headers;
    }
}
