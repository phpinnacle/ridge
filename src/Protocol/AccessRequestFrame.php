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

class AccessRequestFrame extends MethodFrame
{
    /**
     * @var string
     */
    public $realm;

    /**
     * @var boolean
     */
    public $exclusive = false;

    /**
     * @var boolean
     */
    public $passive = false;

    /**
     * @var boolean
     */
    public $active = false;

    /**
     * @var boolean
     */
    public $write = false;

    /**
     * @var boolean
     */
    public $read = false;

    public function __construct()
    {
        parent::__construct(Constants::CLASS_ACCESS, Constants::METHOD_ACCESS_REQUEST);

    }
    
    /**
     * @param Buffer $buffer
     *
     * @return self
     */
    public static function unpack(Buffer $buffer): self
    {
        $self = new self;
    
        $self->realm = $buffer->consumeString();
    
        [
            $self->exclusive,
            $self->passive,
            $self->active,
            $self->write,
            $self->read
        ] = $buffer->consumeBits(5);
        
        return $self;
    }
    
    /**
     * @return Buffer
     */
    public function pack(): Buffer
    {
        $buffer = parent::pack();
        $buffer->appendString($this->realm);
        $buffer->appendBits([$this->exclusive, $this->passive, $this->active, $this->write, $this->read]);
        
        return $buffer;
    }
}
