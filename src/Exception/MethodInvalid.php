<?php
/**
 * This file is part of PHPinnacle/Ridge.
 *
 * (c) PHPinnacle Team <dev@phpinnacle.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace PHPinnacle\Ridge\Exception;

final class MethodInvalid extends ProtocolException
{
    /**
     * @param int $classId
     * @param int $methodId
     */
    public function __construct(int $classId, int $methodId)
    {
        parent::__construct("Unhandled method frame method '{$methodId}' in class '{$classId}'.");
    }
}
