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
 * Peer sent frame with invalid method id.
 *
 * @author Jakub Kulhan <jakub.kulhan@gmail.com>
 */
class MethodInvalid extends ProtocolException
{
    /**
     * @var int
     */
    private $classId;

    /**
     * @var int
     */
    private $methodId;

    /**
     * @param int $classId
     * @param int $methodId
     */
    public function __construct(int $classId, int $methodId)
    {
        parent::__construct("Unhandled method frame method '{$methodId}' in class '{$classId}'.");

        $this->classId  = $classId;
        $this->methodId = $methodId;
    }

    /**
     * @return int
     */
    public function getClassId(): int
    {
        return $this->classId;
    }

    /**
     * @return int
     */
    public function getMethodId(): int
    {
        return $this->methodId;
    }
}
