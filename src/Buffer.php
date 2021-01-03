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

namespace PHPinnacle\Ridge;

use PHPinnacle\Buffer\ByteBuffer;

final class Buffer extends ByteBuffer
{
    public function appendString(string $value): self
    {
        $this
            ->appendUint8(\strlen($value))
            ->append($value);

        return $this;
    }

    /**
     * @throws \PHPinnacle\Buffer\BufferOverflow
     */
    public function consumeString(): string
    {
        return $this->consume($this->consumeUint8());
    }

    public function appendText(string $value): self
    {
        $this
            ->appendUint32(\strlen($value))
            ->append($value);

        return $this;
    }

    /**
     * @throws \PHPinnacle\Buffer\BufferOverflow
     */
    public function consumeText(): string
    {
        return $this->consume($this->consumeUint32());
    }

    public function appendBits(array $bits): self
    {
        $value = 0;

        /**
         * @var int $n
         * @var bool $bit
         */
        foreach ($bits as $n => $bit) {
            $bit = $bit ? 1 : 0;
            $value |= $bit << $n;
        }

        $this->appendUint8($value);

        return $this;
    }

    /**
     * @throws \PHPinnacle\Buffer\BufferOverflow
     */
    public function consumeBits(int $n): array
    {
        $bits = [];
        $value = $this->consumeUint8();

        for ($i = 0; $i < $n; ++$i) {
            $bits[] = ($value & (1 << $i)) > 0;
        }

        return $bits;
    }

    public function appendTimestamp(\DateTimeInterface $value): self
    {
        $this->appendUint64($value->getTimestamp());

        return $this;
    }

    public function consumeTimestamp(): \DateTimeInterface
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        return new \DateTimeImmutable(\sprintf('@%s', $this->consumeUint64()));
    }

    /**
     * @throws \PHPinnacle\Ridge\Exception\ProtocolException
     */
    public function appendTable(array $table): self
    {
        $buffer = new self();

        /**
         * @var string|ByteBuffer $k
         * @var mixed $v
         */
        foreach ($table as $k => $v) {
            $k = (string)$k;

            $buffer->appendUint8(\strlen($k));
            $buffer->append($k);
            $buffer->appendValue($v);
        }

        $this
            ->appendUint32($buffer->size())
            ->append($buffer);

        return $this;
    }

    /**
     * @throws \PHPinnacle\Buffer\BufferOverflow
     */
    public function consumeTable(): array
    {
        $buffer = $this->shift($this->consumeUint32());
        $data = [];

        while (!$buffer->empty()) {
            $data[$buffer->consume($buffer->consumeUint8())] = $buffer->consumeValue();
        }

        return $data;
    }

    /**
     * @throws \PHPinnacle\Ridge\Exception\ProtocolException
     */
    public function appendArray(array $value): self
    {
        $buffer = new self();

        /** @var mixed $v */
        foreach ($value as $v) {
            $buffer->appendValue($v);
        }

        $this
            ->appendUint32($buffer->size())
            ->append($buffer);

        return $this;
    }

    /**
     * @throws \PHPinnacle\Buffer\BufferOverflow
     */
    public function consumeArray(): array
    {
        $buffer = $this->shift($this->consumeUint32());
        $data = [];

        while (!$buffer->empty()) {
            $data[] = $buffer->consumeValue();
        }

        return $data;
    }

    /**
     * @throws \PHPinnacle\Buffer\BufferOverflow
     */
    public function consumeDecimal(): int
    {
        $scale = $this->consumeUint8();
        $value = $this->consumeUint32();

        return $value * (10 ** $scale);
    }

    /**
     * @throws \PHPinnacle\Buffer\BufferOverflow
     * @throws \PHPinnacle\Ridge\Exception\ProtocolException
     */
    private function consumeValue(): float|\DateTimeInterface|null|array|bool|int|string
    {
        $fieldType = $this->consumeUint8();

        return match ($fieldType) {
            Constants::FIELD_BOOLEAN => $this->consumeUint8() > 0,
            Constants::FIELD_SHORT_SHORT_INT => $this->consumeInt8(),
            Constants::FIELD_SHORT_SHORT_UINT => $this->consumeUint8(),
            Constants::FIELD_SHORT_INT => $this->consumeInt16(),
            Constants::FIELD_SHORT_UINT => $this->consumeUint16(),
            Constants::FIELD_LONG_INT => $this->consumeInt32(),
            Constants::FIELD_LONG_UINT => $this->consumeUint32(),
            Constants::FIELD_LONG_LONG_INT => $this->consumeInt64(),
            Constants::FIELD_LONG_LONG_UINT => $this->consumeUint64(),
            Constants::FIELD_FLOAT => $this->consumeFloat(),
            Constants::FIELD_DOUBLE => $this->consumeDouble(),
            Constants::FIELD_DECIMAL => $this->consumeDecimal(),
            Constants::FIELD_SHORT_STRING => $this->consume($this->consumeUint8()),
            Constants::FIELD_LONG_STRING => $this->consume($this->consumeUint32()),
            Constants::FIELD_TIMESTAMP => $this->consumeTimestamp(),
            Constants::FIELD_ARRAY => $this->consumeArray(),
            Constants::FIELD_TABLE => $this->consumeTable(),
            Constants::FIELD_NULL => null,
            default => throw Exception\ProtocolException::unknownFieldType($fieldType),
        };
    }

    /**
     * @throws \PHPinnacle\Ridge\Exception\ProtocolException
     */
    private function appendValue(mixed $value): void
    {
        if (\is_string($value)) {
            $this->appendUint8(Constants::FIELD_LONG_STRING);
            $this->appendText($value);

            return;
        }

        if (\is_int($value)) {
            $this->appendUint8(Constants::FIELD_LONG_INT);
            $this->appendInt32($value);

            return;
        }

        if (\is_bool($value)) {
            $this->appendUint8(Constants::FIELD_BOOLEAN);
            $this->appendUint8((int)$value);

            return;
        }

        if (\is_float($value)) {
            $this->appendUint8(Constants::FIELD_DOUBLE);
            $this->appendDouble($value);

            return;
        }

        if (\is_array($value)) {
            if (\array_keys($value) === \range(0, \count($value) - 1)) {
                $this->appendUint8(Constants::FIELD_ARRAY);
                $this->appendArray($value);
            } else {
                $this->appendUint8(Constants::FIELD_TABLE);
                $this->appendTable($value);
            }

            return;
        }

        if (\is_null($value)) {
            $this->appendUint8(Constants::FIELD_NULL);

            return;
        }

        if ($value instanceof \DateTimeInterface) {
            $this->appendUint8(Constants::FIELD_TIMESTAMP);
            $this->appendTimestamp($value);

            return;
        }

        throw Exception\ProtocolException::unknownValueType($value);
    }
}
