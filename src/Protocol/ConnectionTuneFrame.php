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

class ConnectionTuneFrame extends MethodFrame
{
    /**
     * @var int
     */
    public $channelMax = 0;

    /**
     * @var int
     */
    public $frameMax = 0;

    /**
     * @var int
     */
    public $heartbeat = 0;
    
    /**
     * @param Buffer $buffer
     */
    public function __construct(Buffer $buffer)
    {
        parent::__construct(Constants::CLASS_CONNECTION, Constants::METHOD_CONNECTION_TUNE);

        $this->channel    = Constants::CONNECTION_CHANNEL;
        $this->channelMax = $buffer->consumeInt16();
        $this->frameMax   = $buffer->consumeInt32();
        $this->heartbeat  = $buffer->consumeInt16();
    }
}
