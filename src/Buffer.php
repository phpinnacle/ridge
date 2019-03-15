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

namespace PHPinnacle\Ridge;

use PHPinnacle\Buffer\ByteBuffer;

final class Buffer extends ByteBuffer
{
    /**
     * @param string $value
     *
     * @return static
     */
    public function appendString(string $value): self
    {
        $this
            ->appendUint8(\strlen($value))
            ->append($value)
        ;

        return $this;
    }

    /**
     * @return string
     */
    public function consumeString(): string
    {
        return $this->consume($this->consumeUint8());
    }

    /**
     * @param string $value
     *
     * @return self
     */
    public function appendText(string $value): self
    {
        $this
            ->appendUint32(\strlen($value))
            ->append($value)
        ;

        return $this;
    }

    /**
     * @return string
     */
    public function consumeText(): string
    {
        return $this->consume($this->consumeUint32());
    }

    /**
     * @param array $bits
     *
     * @return self
     */
    public function appendBits(array $bits): self
    {
        $value = 0;

        foreach ($bits as $n => $bit) {
            $bit = $bit ? 1 : 0;
            $value |= $bit << $n;
        }

        $this->appendUint8($value);

        return $this;
    }

    /**
     * @param int $n
     *
     * @return bool[]
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

    /**
     * @param \DateTimeInterface $value
     *
     * @return self
     */
    public function appendTimestamp(\DateTimeInterface $value): self
    {
        $this->appendUint64($value->getTimestamp());

        return $this;
    }

    /**
     * @noinspection PhpDocMissingThrowsInspection
     *
     * @return \DateTimeInterface
     */
    public function consumeTimestamp(): \DateTimeInterface
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        return (new \DateTimeImmutable)->setTimestamp($this->consumeUint64());
    }

    /**
     * @param array $table
     *
     * @return self
     */
    public function appendTable(array $table): self
    {
        $buffer = new static;

        foreach ($table as $k => $v) {
            $k = (string) $k;

            $buffer->appendUint8(\strlen($k));
            $buffer->append($k);
            $buffer->appendValue($v);
        }

        $this
            ->appendUint32($buffer->size())
            ->append($buffer)
        ;

        return $this;
    }

    /**
     * @return array
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
     * @param array $value
     *
     * @return self
     */
    public function appendArray(array $value): self
    {
        $buffer = new static;

        foreach ($value as $v) {
            $buffer->appendValue($v);
        }

        $this
            ->appendUint32($buffer->size())
            ->append($buffer)
        ;

        return $this;
    }

    /**
     * @return array
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
     * @return int
     */
    public function consumeDecimal(): int
    {
        $scale = $this->consumeUint8();
        $value = $this->consumeUint32();

        return $value * (10 ** $scale);
    }

    /**
     * @return mixed
     * @throws Exception\ProtocolException
     */
    private function consumeValue()
    {
        $fieldType = $this->consumeUint8();

        switch ($fieldType) {
            case Constants::FIELD_BOOLEAN:
                return $this->consumeUint8() > 0;
            case Constants::FIELD_SHORT_SHORT_INT:
                return $this->consumeInt8();
            case Constants::FIELD_SHORT_SHORT_UINT:
                return $this->consumeUint8();
            case Constants::FIELD_SHORT_INT:
                return $this->consumeInt16();
            case Constants::FIELD_SHORT_UINT:
                return $this->consumeUint16();
            case Constants::FIELD_LONG_INT:
                return $this->consumeInt32();
            case Constants::FIELD_LONG_UINT:
                return $this->consumeUint32();
            case Constants::FIELD_LONG_LONG_INT:
                return $this->consumeInt64();
            case Constants::FIELD_LONG_LONG_UINT:
                return $this->consumeUint64();
            case Constants::FIELD_FLOAT:
                return $this->consumeFloat();
            case Constants::FIELD_DOUBLE:
                return $this->consumeDouble();
            case Constants::FIELD_DECIMAL:
                return $this->consumeDecimal();
            case Constants::FIELD_SHORT_STRING:
                return $this->consume($this->consumeUint8());
            case Constants::FIELD_LONG_STRING:
                return $this->consume($this->consumeUint32());
            case Constants::FIELD_TIMESTAMP:
                return $this->consumeTimestamp();
            case Constants::FIELD_ARRAY:
                return $this->consumeArray();
            case Constants::FIELD_TABLE:
                return $this->consumeTable();
            case Constants::FIELD_NULL:
                return null;
            default:
                throw Exception\ProtocolException::unknownFieldType($fieldType);
        }
    }

    /**
     * @param mixed  $value
     */
    private function appendValue($value)
    {
        if (\is_string($value)) {
            $this->appendUint8(Constants::FIELD_LONG_STRING);
            $this->appendText($value);
        } elseif (\is_int($value)) {
            $this->appendUint8(Constants::FIELD_LONG_INT);
            $this->appendInt32($value);
        } elseif (\is_bool($value)) {
            $this->appendUint8(Constants::FIELD_BOOLEAN);
            $this->appendUint8(\intval($value));
        } elseif (\is_float($value)) {
            $this->appendUint8(Constants::FIELD_DOUBLE);
            $this->appendDouble($value);
        } elseif (\is_array($value)) {
            if (\array_keys($value) === \range(0, \count($value) - 1)) {
                $this->appendUint8(Constants::FIELD_ARRAY);
                $this->appendArray($value);
            } else {
                $this->appendUint8(Constants::FIELD_TABLE);
                $this->appendTable($value);
            }
        } elseif (\is_null($value)) {
            $this->appendUint8(Constants::FIELD_NULL);
        } elseif ($value instanceof \DateTime) {
            $this->appendUint8(Constants::FIELD_TIMESTAMP);
            $this->appendTimestamp($value);
        } else {
            throw Exception\ProtocolException::unknownValueType($value);
        }
    }
}