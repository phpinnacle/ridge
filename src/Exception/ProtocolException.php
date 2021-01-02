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

use PHPinnacle\Ridge\Constants;
use PHPinnacle\Ridge\Protocol\AbstractFrame;

class ProtocolException extends RidgeException
{
    public static function invalidFrameEnd(int $frameEnd): self
    {
        return new self(
            \sprintf(
                'Frame end byte invalid - expected 0x%02x, got 0x%02x.',
                Constants::FRAME_END,
                $frameEnd
            )
        );
    }

    public static function unknownFrameType(int $type): self
    {
        return new self(\sprintf('Unhandled frame type `%d`.', $type));
    }

    public static function unknownFrameClass(AbstractFrame $frame): self
    {
        return new self(\sprintf('Unhandled frame `%s`', \get_class($frame)));
    }

    public static function notEmptyHeartbeat(): self
    {
        return new self('Heartbeat frame must be empty.');
    }

    public static function unknownFieldType(int $fieldType): self
    {
        $cType = \ctype_print(\chr($fieldType)) ? ' (`' . \chr($fieldType) . '`)' : '';

        return new self(\sprintf('Unhandled field type 0x%02x%s.', $fieldType, $cType));
    }

    public static function unknownValueType(mixed $value): self
    {
        $class = (\is_object($value) ? ' (class `' . \get_class($value) . '`)' : '');

        return new self(\sprintf('Unhandled value type `%s`%s.', \gettype($value), $class));
    }
}
