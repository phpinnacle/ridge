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

class ExchangeDeleteFrame extends MethodFrame
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
     * @var boolean
     */
    public $ifUnused = true;

    /**
     * @var boolean
     */
    public $nowait = false;

    public function __construct()
    {
        parent::__construct(Constants::CLASS_EXCHANGE, Constants::METHOD_EXCHANGE_DELETE);
    }
    
    /**
     * @param Buffer $buffer
     *
     * @return self
     */
    public static function unpack(Buffer $buffer): self
    {
        $self = new self;
    
        $self->reserved1 = $buffer->consumeInt16();
        $self->exchange  = $buffer->consumeString();
    
        [$self->ifUnused, $self->nowait] = $buffer->consumeBits(2);
        
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
        $buffer->appendBits([$this->ifUnused, $this->nowait]);
        
        return $buffer;
    }
}
