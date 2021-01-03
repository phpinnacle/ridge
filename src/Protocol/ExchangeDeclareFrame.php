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

class ExchangeDeclareFrame extends MethodFrame
{
    /**
     * @var int
     */
    public $reserved1;

    /**
     * @var string
     */
    public $exchange;

    /**
     * @var string
     */
    public $exchangeType = 'direct';

    /**
     * @var bool
     */
    public $passive = false;

    /**
     * @var bool
     */
    public $durable = false;

    /**
     * @var bool
     */
    public $autoDelete = false;

    /**
     * @var bool
     */
    public $internal = false;

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
        parent::__construct(Constants::CLASS_EXCHANGE, Constants::METHOD_EXCHANGE_DECLARE);
    }

    /**
     * @throws \PHPinnacle\Buffer\BufferOverflow
     */
    public static function unpack(Buffer $buffer): self
    {
        $self = new self;
        $self->reserved1 = $buffer->consumeInt16();
        $self->exchange = $buffer->consumeString();
        $self->exchangeType = $buffer->consumeString();

        [$self->passive, $self->durable, $self->autoDelete, $self->internal, $self->nowait] = $buffer->consumeBits(5);

        $self->arguments = $buffer->consumeTable();

        return $self;
    }

    public function pack(): Buffer
    {
        $buffer = parent::pack();
        $buffer->appendInt16($this->reserved1);
        $buffer->appendString($this->exchange);
        $buffer->appendString($this->exchangeType);
        $buffer->appendBits([$this->passive, $this->durable, $this->autoDelete, $this->internal, $this->nowait]);
        $buffer->appendTable($this->arguments);

        return $buffer;
    }
}
