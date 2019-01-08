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

class BasicRejectFrame extends MethodFrame
{
    /**
     * @var int
     */
    public $deliveryTag;

    /**
     * @var boolean
     */
    public $requeue = true;
    
    /**
     * @param Buffer $buffer
     */
    public function __construct(Buffer $buffer)
    {
        parent::__construct(Constants::CLASS_BASIC, Constants::METHOD_BASIC_REJECT);

        $this->deliveryTag = $buffer->consumeInt64();
        [$this->requeue]   = $buffer->consumeBits(1);
    }
}
