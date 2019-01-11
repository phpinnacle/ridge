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

class ExchangeDeleteFrame extends MethodFrame
{
    /**
     * @var int
     */
    public $reserved1;

    /**
     * @var string
     */
    public $exchange;

    /**
     * @var boolean
     */
    public $ifUnused;

    /**
     * @var boolean
     */
    public $nowait;

    /**
     * @param Buffer $buffer
     */
    public function __construct(Buffer $buffer)
    {
        parent::__construct(Constants::CLASS_EXCHANGE, Constants::METHOD_EXCHANGE_DELETE);

        $this->reserved1 = $buffer->consumeInt16();
        $this->exchange  = $buffer->consumeString();

        [$this->ifUnused, $this->nowait] = $buffer->consumeBits(2);
    }
}
