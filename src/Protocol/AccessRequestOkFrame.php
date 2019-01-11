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

class AccessRequestOkFrame extends MethodFrame
{
    /**
     * @var int
     */
    public $reserved1;

    /**
     * @param Buffer $buffer
     */
    public function __construct(Buffer $buffer)
    {
        parent::__construct(Constants::CLASS_ACCESS, Constants::METHOD_ACCESS_REQUEST_OK);

        $this->reserved1 = $buffer->consumeInt16();
    }
}
