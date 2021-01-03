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

class BasicQosFrame extends MethodFrame
{
    /**
     * @var int
     */
    public $prefetchSize = 0;

    /**
     * @var int
     */
    public $prefetchCount = 0;

    /**
     * @var bool
     */
    public $global = false;

    public function __construct()
    {
        parent::__construct(Constants::CLASS_BASIC, Constants::METHOD_BASIC_QOS);
    }

    /**
     * @throws \PHPinnacle\Buffer\BufferOverflow
     */
    public static function unpack(Buffer $buffer): self
    {
        $self = new self;
        $self->prefetchSize = $buffer->consumeInt32();
        $self->prefetchCount = $buffer->consumeInt16();
        [$self->global] = $buffer->consumeBits(1);

        return $self;
    }

    public function pack(): Buffer
    {
        $buffer = parent::pack();
        $buffer->appendInt32($this->prefetchSize);
        $buffer->appendInt16($this->prefetchCount);
        $buffer->appendBits([$this->global]);

        return $buffer;
    }
}
