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

class BasicDeliverFrame extends MessageFrame
{
    /**
     * @var string
     */
    public $consumerTag;

    public function __construct()
    {
        parent::__construct(Constants::CLASS_BASIC, Constants::METHOD_BASIC_DELIVER);
    }

    /**
     * @throws \PHPinnacle\Buffer\BufferOverflow
     */
    public static function unpack(Buffer $buffer): self
    {
        $self              = new self;
        $self->consumerTag = $buffer->consumeString();
        $self->deliveryTag = $buffer->consumeInt64();
        [$self->redelivered] = $buffer->consumeBits(1);
        $self->exchange   = $buffer->consumeString();
        $self->routingKey = $buffer->consumeString();

        return $self;
    }

    public function pack(): Buffer
    {
        $buffer = parent::pack();
        $buffer->appendString($this->consumerTag);
        $buffer->appendInt64($this->deliveryTag);
        $buffer->appendBits([$this->redelivered]);
        $buffer->appendString($this->exchange);
        $buffer->appendString($this->routingKey);

        return $buffer;
    }
}
