<?php
/**
 * This file is part of PHPinnacle/Ridge.
 *
 * (c) PHPinnacle Team <dev@phpinnacle.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace PHPinnacle\Ridge\Exception;

/**
 * Peer sent frame with invalid method class id.
 *
 * @author Jakub Kulhan <jakub.kulhan@gmail.com>
 */
class ClassInvalid extends ProtocolException
{
    /**
     * @var int
     */
    private $classId;

    /**
     * @param int $classId
     */
    public function __construct(int $classId)
    {
        parent::__construct("Unhandled method frame class '{$classId}'.");

        $this->classId = $classId;
    }

    /**
     * @return int
     */
    public function getClassId(): int
    {
        return $this->classId;
    }
}
