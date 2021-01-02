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

final class MethodInvalid extends ProtocolException
{
    public function __construct(int $classId, int $methodId)
    {
        parent::__construct(\sprintf('Unhandled method frame method `%d` in class `%d`.', $methodId, $classId));
    }
}
