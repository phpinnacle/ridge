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

class BasicGetOkFrame extends MessageFrame
{
    /**
     * @var int
     */
    public $messageCount;

    public function __construct()
    {
        parent::__construct(Constants::CLASS_BASIC, Constants::METHOD_BASIC_GET_OK);
    }

    /**
     * @throws \PHPinnacle\Buffer\BufferOverflow
     */
    public static function unpack(Buffer $buffer): self
    {
        $self = new self;
        $self->deliveryTag = $buffer->consumeInt64();
        [$self->redelivered] = $buffer->consumeBits(1);
        $self->exchange = $buffer->consumeString();
        $self->routingKey = $buffer->consumeString();
        $self->messageCount = $buffer->consumeInt32();

        return $self;
    }

    public function pack(): Buffer
    {
        $buffer = parent::pack();
        $buffer->appendInt64($this->deliveryTag);
        $buffer->appendBits([$this->redelivered]);
        $buffer->appendString($this->exchange);
        $buffer->appendString($this->routingKey);
        $buffer->appendInt32($this->messageCount);

        return $buffer;
    }
}
