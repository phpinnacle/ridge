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

    /**
     * @param Buffer $buffer
     */
    public function __construct(Buffer $buffer)
    {
        parent::__construct(Constants::CLASS_QUEUE, Constants::METHOD_QUEUE_DECLARE);

        $this->reserved1 = $buffer->consumeInt16();
        $this->queue     = $buffer->consumeString();

        [$this->passive, $this->durable, $this->exclusive, $this->autoDelete, $this->nowait] = $buffer->consumeBits(5);

        $this->arguments = $buffer->consumeTable();
    }
}
