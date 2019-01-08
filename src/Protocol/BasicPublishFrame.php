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

class BasicPublishFrame extends MethodFrame
{
    /**
     * @var int
     */
    public $reserved1 = 0;

    /**
     * @var string
     */
    public $exchange = '';

    /**
     * @var string
     */
    public $routingKey = '';

    /**
     * @var boolean
     */
    public $mandatory = false;

    /**
     * @var boolean
     */
    public $immediate = false;
    
    /**
     * @param Buffer $buffer
     */
    public function __construct(Buffer $buffer)
    {
        parent::__construct(Constants::CLASS_BASIC, Constants::METHOD_BASIC_PUBLISH);

        $this->reserved1  = $buffer->consumeInt16();
        $this->exchange   = $buffer->consumeString();
        $this->routingKey = $buffer->consumeString();

        [$this->mandatory, $this->immediate] = $buffer->consumeBits(2);
    }
}
