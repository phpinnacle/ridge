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

class QueueDeclareFrame extends MethodFrame
{
    /**
     * @var int
     */
    public $reserved1 = 0;

    /**
     * @var string
     */
    public $queue = '';

    /**
     * @var boolean
     */
    public $passive = false;

    /**
     * @var boolean
     */
    public $durable = false;

    /**
     * @var boolean
     */
    public $exclusive = false;

    /**
     * @var boolean
     */
    public $autoDelete = false;

    /**
     * @var boolean
     */
    public $nowait = false;

    /**
     * @var array
     */
    public $arguments = [];

    public function __construct()
    {
        parent::__construct(Constants::CLASS_QUEUE, Constants::METHOD_QUEUE_DECLARE);
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
        $self->queue     = $buffer->consumeString();
    
        [$self->passive, $self->durable, $self->exclusive, $self->autoDelete, $self->nowait] = $buffer->consumeBits(5);
    
        $self->arguments = $buffer->consumeTable();
        
        return $self;
    }
    
    /**
     * @return Buffer
     */
    public function pack(): Buffer
    {
        $buffer = parent::pack();
        $buffer->appendInt16($this->reserved1);
        $buffer->appendString($this->queue);
        $buffer->appendBits([$this->passive, $this->durable, $this->exclusive, $this->autoDelete, $this->nowait]);
        $buffer->appendTable($this->arguments);
        
        return $buffer;
    }
}
