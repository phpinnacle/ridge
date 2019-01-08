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

    /**
     * @param Buffer $buffer
     */
    public function __construct(Buffer $buffer)
    {
        parent::__construct(Constants::CLASS_QUEUE, Constants::METHOD_QUEUE_DECLARE_OK);

        $this->queue         = $buffer->consumeString();
        $this->messageCount  = $buffer->consumeInt32();
        $this->consumerCount = $buffer->consumeInt32();
    }
}
