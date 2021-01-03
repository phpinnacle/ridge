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

class ExchangeUnbindFrame extends MethodFrame
{
    /**
     * @var int
     */
    public $reserved1 = 0;

    /**
     * @var string
     */
    public $destination;

    /**
     * @var string
     */
    public $source;

    /**
     * @var string
     */
    public $routingKey = '';

    /**
     * @var bool
     */
    public $nowait = false;

    /**
     * @var array
     */
    public $arguments = [];

    public function __construct()
    {
        parent::__construct(Constants::CLASS_EXCHANGE, Constants::METHOD_EXCHANGE_UNBIND);
    }

    /**
     * @throws \PHPinnacle\Buffer\BufferOverflow
     */
    public static function unpack(Buffer $buffer): self
    {
        $self = new self;
        $self->reserved1 = $buffer->consumeInt16();
        $self->destination = $buffer->consumeString();
        $self->source = $buffer->consumeString();
        $self->routingKey = $buffer->consumeString();
        [$self->nowait] = $buffer->consumeBits(1);
        $self->arguments = $buffer->consumeTable();

        return $self;
    }

    public function pack(): Buffer
    {
        $buffer = parent::pack();
        $buffer->appendInt16($this->reserved1);
        $buffer->appendString($this->destination);
        $buffer->appendString($this->source);
        $buffer->appendString($this->routingKey);
        $buffer->appendBits([$this->nowait]);
        $buffer->appendTable($this->arguments);

        return $buffer;
    }
}
