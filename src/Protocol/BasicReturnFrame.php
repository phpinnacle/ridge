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

class BasicReturnFrame extends MethodFrame
{
    /**
     * @var int
     */
    public $replyCode;

    /**
     * @var string
     */
    public $replyText = '';

    /**
     * @var string
     */
    public $exchange;

    /**
     * @var string
     */
    public $routingKey;
    
    /**
     * @param Buffer $buffer
     */
    public function __construct(Buffer $buffer)
    {
        parent::__construct(Constants::CLASS_BASIC, Constants::METHOD_BASIC_RETURN);

        $this->replyCode  = $buffer->consumeInt16();
        $this->replyText  = $buffer->consumeString();
        $this->exchange   = $buffer->consumeString();
        $this->routingKey = $buffer->consumeString();
    }
}
