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

class QueueDeclareOkFrame extends MethodFrame
{
    /**
     * @var string
     */
    public $queue;

    /**
     * @var int
     */
    public $messageCount;

    /**
     * @var int
     */
    public $consumerCount;
    
    public function __construct()
    {
        parent::__construct(Constants::CLASS_QUEUE, Constants::METHOD_QUEUE_DECLARE_OK);
    }
    
    /**
     * @param Buffer $buffer
     *
     * @return self
     */
    public static function unpack(Buffer $buffer): self
    {
        $self = new self;
        $self->queue         = $buffer->consumeString();
        $self->messageCount  = $buffer->consumeInt32();
        $self->consumerCount = $buffer->consumeInt32();

        return $self;
    }
    
    /**
     * @return Buffer
     */
    public function pack(): Buffer
    {
        $buffer = parent::pack();
        $buffer->appendString($this->queue);
        $buffer->appendInt32($this->messageCount);
        $buffer->appendInt32($this->consumerCount);
        
        return $buffer;
    }
}
