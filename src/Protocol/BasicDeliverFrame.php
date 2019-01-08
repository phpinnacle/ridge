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
    
    /**
     * @param Buffer $buffer
     */
    public function __construct(Buffer $buffer)
    {
        parent::__construct(Constants::CLASS_BASIC, Constants::METHOD_BASIC_DELIVER);

        $this->consumerTag   = $buffer->consumeString();
        $this->deliveryTag   = $buffer->consumeInt64();
        [$this->redelivered] = $buffer->consumeBits(1);
        $this->exchange      = $buffer->consumeString();
        $this->routingKey    = $buffer->consumeString();
    }
}
