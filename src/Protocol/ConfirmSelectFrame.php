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

class ConfirmSelectFrame extends MethodFrame
{
    /**
     * @var bool
     */
    public $nowait = false;
    
    public function __construct()
    {
        parent::__construct(Constants::CLASS_CONFIRM, Constants::METHOD_CONFIRM_SELECT);
    }
    
    /**
     * @param Buffer $buffer
     *
     * @return self
     */
    public static function unpack(Buffer $buffer): self
    {
        $self = new self;
        [$self->nowait] = $buffer->consumeBits(1);
        
        return $self;
    }
    
    /**
     * @return Buffer
     */
    public function pack(): Buffer
    {
        $buffer = parent::pack();
        $buffer->appendBits([$this->nowait]);
        
        return $buffer;
    }
}
