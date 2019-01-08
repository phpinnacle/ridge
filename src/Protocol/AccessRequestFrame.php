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

class AccessRequestFrame extends MethodFrame
{
    /**
     * @var string
     */
    public $realm;

    /**
     * @var boolean
     */
    public $exclusive;

    /**
     * @var boolean
     */
    public $passive;

    /**
     * @var boolean
     */
    public $active;

    /**
     * @var boolean
     */
    public $write;

    /**
     * @var boolean
     */
    public $read;

    /**
     * @param Buffer $buffer
     */
    public function __construct(Buffer $buffer)
    {
        parent::__construct(Constants::CLASS_ACCESS, Constants::METHOD_ACCESS_REQUEST);

        $this->realm = $buffer->consumeString();

        [
            $this->exclusive,
            $this->passive,
            $this->active,
            $this->write,
            $this->read
        ] = $buffer->consumeBits(5);
    }
}
