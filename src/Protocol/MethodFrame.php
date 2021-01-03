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

class MethodFrame extends AbstractFrame
{
    /**
     * @var int|null
     */
    public $classId;

    /**
     * @var int|null
     */
    public $methodId;

    public function __construct(?int $classId = null, ?int $methodId = null)
    {
        parent::__construct(Constants::FRAME_METHOD);

        $this->classId = $classId;
        $this->methodId = $methodId;
    }

    public function pack(): Buffer
    {
        $buffer = new Buffer;
        $buffer
            ->appendUint16((int)$this->classId)
            ->appendUint16((int)$this->methodId);

        return $buffer;
    }
}
