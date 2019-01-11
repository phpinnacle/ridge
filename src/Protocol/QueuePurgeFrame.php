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

class QueuePurgeFrame extends MethodFrame
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
    public $nowait = false;

    /**
     * @param Buffer $buffer
     */
    public function __construct(Buffer $buffer)
    {
        parent::__construct(Constants::CLASS_QUEUE, Constants::METHOD_QUEUE_PURGE);

        $this->reserved1 = $buffer->consumeInt16();
        $this->queue     = $buffer->consumeString();

        [$this->nowait] = $buffer->consumeBits(1);
    }
}
