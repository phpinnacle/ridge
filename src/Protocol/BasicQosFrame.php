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

class BasicQosFrame extends MethodFrame
{
    /**
     * @var int
     */
    public $prefetchSize = 0;

    /**
     * @var int
     */
    public $prefetchCount = 0;

    /**
     * @var boolean
     */
    public $global = false;
    
    /**
     * @param Buffer $buffer
     */
    public function __construct(Buffer $buffer)
    {
        parent::__construct(Constants::CLASS_BASIC, Constants::METHOD_BASIC_QOS);

        $this->prefetchSize  = $buffer->consumeInt32();
        $this->prefetchCount = $buffer->consumeInt16();

        [$this->global] = $buffer->consumeBits(1);
    }
}
