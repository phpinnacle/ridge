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

class ExchangeBindFrame extends MethodFrame
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
     * @var boolean
     */
    public $nowait = false;

    /**
     * @var array
     */
    public $arguments = [];

    /**
     * @param Buffer $buffer
     */
    public function __construct(Buffer $buffer)
    {
        parent::__construct(Constants::CLASS_EXCHANGE, Constants::METHOD_EXCHANGE_BIND);

        $this->reserved1   = $buffer->consumeInt16();
        $this->destination = $buffer->consumeString();
        $this->source      = $buffer->consumeString();
        $this->routingKey  = $buffer->consumeString();
        [$this->nowait]    = $buffer->consumeBits(1);
        $this->arguments   = $buffer->consumeTable();
    }
}
