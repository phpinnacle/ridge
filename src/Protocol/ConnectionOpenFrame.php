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

class ConnectionOpenFrame extends MethodFrame
{
    /**
     * @var string
     */
    public $virtualHost = '/';

    /**
     * @var string
     */
    public $capabilities = '';

    /**
     * @var bool
     */
    public $insist = false;

    public function __construct()
    {
        parent::__construct(Constants::CLASS_CONNECTION, Constants::METHOD_CONNECTION_OPEN);

        $this->channel = Constants::CONNECTION_CHANNEL;
    }

    /**
     * @throws \PHPinnacle\Buffer\BufferOverflow
     */
    public static function unpack(Buffer $buffer): self
    {
        $self = new self;
        $self->virtualHost = $buffer->consumeString();
        $self->capabilities = $buffer->consumeString();
        [$self->insist] = $buffer->consumeBits(1);

        return $self;
    }

    public function pack(): Buffer
    {
        $buffer = parent::pack();
        $buffer->appendString($this->virtualHost);
        $buffer->appendString($this->capabilities);
        $buffer->appendBits([$this->insist]);

        return $buffer;
    }
}
