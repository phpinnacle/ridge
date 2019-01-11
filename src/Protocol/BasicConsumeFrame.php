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

class BasicConsumeFrame extends MethodFrame
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
     * @var string
     */
    public $consumerTag = '';

    /**
     * @var boolean
     */
    public $noLocal = false;

    /**
     * @var boolean
     */
    public $noAck = false;

    /**
     * @var boolean
     */
    public $exclusive = false;

    /**
     * @var boolean
     */
    public $nowait = false;

    /**
     * @var array
     */
    public $arguments = [];
    
    /**
     * @param Buffer $buffer
     */
    public function __construct(Buffer $buffer)
    {
        parent::__construct(Constants::CLASS_BASIC, Constants::METHOD_BASIC_CONSUME);

        $this->reserved1   = $buffer->consumeInt16();
        $this->queue       = $buffer->consumeString();
        $this->consumerTag = $buffer->consumeString();

        [$this->noLocal, $this->noAck, $this->exclusive, $this->nowait] = $buffer->consumeBits(4);

        $this->arguments = $buffer->consumeTable();
    }
}
