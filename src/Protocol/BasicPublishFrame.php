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
    
    public function __construct()
    {
        parent::__construct(Constants::CLASS_BASIC, Constants::METHOD_BASIC_PUBLISH);
    }
    
    /**
     * @param Buffer $buffer
     *
     * @return self
     */
    public static function unpack(Buffer $buffer): self
    {
        $self = new self;
        $self->reserved1  = $buffer->consumeInt16();
        $self->exchange   = $buffer->consumeString();
        $self->routingKey = $buffer->consumeString();
    
        [$self->mandatory, $self->immediate] = $buffer->consumeBits(2);
        
        return $self;
    }
    
    /**
     * @return Buffer
     */
    public function pack(): Buffer
    {
        $buffer = parent::pack();
        $buffer->appendInt16($this->reserved1);
        $buffer->appendString($this->exchange);
        $buffer->appendString($this->routingKey);
        $buffer->appendBits([$this->mandatory, $this->immediate]);
        
        return $buffer;
    }
}
