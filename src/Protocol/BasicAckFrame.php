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

class BasicAckFrame extends MethodFrame
{
    /**
     * @var int
     */
    public $deliveryTag = 0;

    /**
     * @var boolean
     */
    public $multiple = false;
    
    /**
     * @param Buffer $buffer
     */
    public function __construct(Buffer $buffer)
    {
        parent::__construct(Constants::CLASS_BASIC, Constants::METHOD_BASIC_ACK);
    
        $this->deliveryTag = $buffer->consumeInt64();
        [$this->multiple]  = $buffer->consumeBits(1);
    }
}
