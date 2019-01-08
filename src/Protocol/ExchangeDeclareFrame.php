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
    public $exchangeType;

    /**
     * @var boolean
     */
    public $passive;

    /**
     * @var boolean
     */
    public $durable;

    /**
     * @var boolean
     */
    public $autoDelete;

    /**
     * @var boolean
     */
    public $internal;

    /**
     * @var boolean
     */
    public $nowait;

    /**
     * @var array
     */
    public $arguments = [];

    /**
     * @param Buffer $buffer
     */
    public function __construct(Buffer $buffer)
    {
        parent::__construct(Constants::CLASS_EXCHANGE, Constants::METHOD_EXCHANGE_DECLARE);

        $this->reserved1    = $buffer->consumeInt16();
        $this->exchange     = $buffer->consumeString();
        $this->exchangeType = $buffer->consumeString();

        [$this->passive, $this->durable, $this->autoDelete, $this->internal, $this->nowait] = $buffer->consumeBits(5);

        $this->arguments = $buffer->consumeTable();
    }
}
