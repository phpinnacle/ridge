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
    public static function buffer(Buffer $buffer): self
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
     * @param array $headers
     *
     * @return self
     */
    public static function fromArray(array $headers): self
    {
        $instance = new self;

        if (isset($headers["content-type"])) {
            $instance->flags |= self::FLAG_CONTENT_TYPE;
            $instance->contentType = $headers["content-type"];

            unset($headers["content-type"]);
        }

        if (isset($headers["content-encoding"])) {
            $instance->flags |= self::FLAG_CONTENT_ENCODING;
            $instance->contentEncoding = $headers["content-encoding"];

            unset($headers["content-encoding"]);
        }

        if (isset($headers["delivery-mode"])) {
            $instance->flags |= self::FLAG_DELIVERY_MODE;
            $instance->deliveryMode = $headers["delivery-mode"];

            unset($headers["delivery-mode"]);
        }

        if (isset($headers["priority"])) {
            $instance->flags |= self::FLAG_PRIORITY;
            $instance->priority = $headers["priority"];

            unset($headers["priority"]);
        }

        if (isset($headers["correlation-id"])) {
            $instance->flags |= self::FLAG_CORRELATION_ID;
            $instance->correlationId = $headers["correlation-id"];

            unset($headers["correlation-id"]);
        }

        if (isset($headers["reply-to"])) {
            $instance->flags |= self::FLAG_REPLY_TO;
            $instance->replyTo = $headers["reply-to"];

            unset($headers["reply-to"]);
        }

        if (isset($headers["expiration"])) {
            $instance->flags |= self::FLAG_EXPIRATION;
            $instance->expiration = $headers["expiration"];

            unset($headers["expiration"]);
        }

        if (isset($headers["message-id"])) {
            $instance->flags |= self::FLAG_MESSAGE_ID;
            $instance->messageId = $headers["message-id"];

            unset($headers["message-id"]);
        }

        if (isset($headers["timestamp"])) {
            $instance->flags |= self::FLAG_TIMESTAMP;
            $instance->timestamp = $headers["timestamp"];

            unset($headers["timestamp"]);
        }

        if (isset($headers["type"])) {
            $instance->flags |= self::FLAG_TYPE;
            $instance->typeHeader = $headers["type"];

            unset($headers["type"]);
        }

        if (isset($headers["user-id"])) {
            $instance->flags |= self::FLAG_USER_ID;
            $instance->userId = $headers["user-id"];

            unset($headers["user-id"]);
        }

        if (isset($headers["app-id"])) {
            $instance->flags |= self::FLAG_APP_ID;
            $instance->appId = $headers["app-id"];

            unset($headers["app-id"]);
        }

        if (isset($headers["cluster-id"])) {
            $instance->flags |= self::FLAG_CLUSTER_ID;
            $instance->clusterId = $headers["cluster-id"];

            unset($headers["cluster-id"]);
        }

        if (!empty($headers)) {
            $instance->flags |= self::FLAG_HEADERS;
            $instance->headers = $headers;
        }

        return $instance;
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
