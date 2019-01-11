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

class QueueBindFrame extends MethodFrame
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
    public $exchange;

    /**
     * @var string
     */
    public $routingKey = '';

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
        parent::__construct(Constants::CLASS_QUEUE, Constants::METHOD_QUEUE_BIND);

        $this->reserved1  = $buffer->consumeInt16();
        $this->queue      = $buffer->consumeString();
        $this->exchange   = $buffer->consumeString();
        $this->routingKey = $buffer->consumeString();

        [$this->nowait] = $buffer->consumeBits(1);

        $this->arguments = $buffer->consumeTable();
    }
}
