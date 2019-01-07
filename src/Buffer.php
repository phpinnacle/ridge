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

/**
 * Binary buffer implementation.
 *
 * Acts as queue:
 *
 * - read*() methods peeks from start.
 * - consume*() methods pops data from start.
 * - append*() methods add data to end.
 *
 * All integers are read from and written to buffer in big-endian order.
 */
final class Buffer
{
    /**
     * @var bool
     */
    private static $isLittleEndian;

    /**
     * @var bool
     */
    private static $native64BitPack;

    /**
     * @var string
     */
    private $data;

    /**
     * @var int
     */
    private $size;

    /**
     * @param string $buffer
     */
    public function __construct(string $buffer = '')
    {
        $this->data = $buffer;
        $this->size = \strlen($this->data);

        if (self::$native64BitPack === null) {
            self::$native64BitPack = PHP_INT_SIZE === 8;
            self::$isLittleEndian = \unpack("S", "\x01\x00")[1] === 1;
        }
    }

    /**
     * Returns number of bytes in buffer.
     *
     * @return int
     */
    public function size(): int
    {
        return $this->size;
    }

    /**
     * Returns true if buffer is empty.
     *
     * @return boolean
     */
    public function empty(): bool
    {
        return $this->size === 0;
    }

    /**
     * @return bool
     */
    public function ready(): bool
    {
        return \ord($this->data[-1]) === Constants::FRAME_END;
    }

    /**
     * @return void
     */
    public function clear(): void
    {
        $this->data = "";
        $this->size = 0;
    }

    /**
     * Reads first $n bytes from $offset.
     *
     * @param int $n
     * @param int $offset
     *
     * @return string
     */
    public function read(int $n, int $offset = 0): string
    {
        if ($this->size < $offset + $n) {
            throw new Exception\BufferUnderflow;
        } elseif ($offset === 0 && $this->size === $offset + $n) {
            return $this->data;
        } else {
            return \substr($this->data, $offset, $n);
        }
    }

    /**
     * Reads first $n bytes from buffer and discards them.
     *
     * @param int $n
     *
     * @return string
     */
    public function consume(int $n): string
    {
        if ($this->size < $n) {
            throw new Exception\BufferUnderflow;
        } elseif ($this->size === $n) {
            $buffer = $this->data;

            $this->data = "";
            $this->size = 0;

            return $buffer;
        } else {
            $buffer = \substr($this->data, 0, $n);

            $this->data = \substr($this->data, $n);
            $this->size -= $n;

            return $buffer;
        }
    }

    /**
     * Discards first $n bytes from buffer.
     *
     * @param int $n
     *
     * @return self
     */
    public function discard(int $n): self
    {
        if ($this->size < $n) {
            throw new Exception\BufferUnderflow;
        } elseif ($this->size === $n) {
            $this->data = "";
            $this->size = 0;

            return $this;
        } else {
            $this->data = \substr($this->data, $n);
            $this->size -= $n;

            return $this;
        }
    }

    /**
     * Returns new buffer with first $n bytes.
     *
     * @param int $n
     *
     * @return self
     */
    public function slice(int $n): self
    {
        if ($this->size < $n) {
            throw new Exception\BufferUnderflow;
        } elseif ($this->size === $n) {
            return new self($this->data);
        } else {
            return new self(\substr($this->data, 0, $n));
        }
    }

    /**
     * Returns new buffer with first $n bytes and discards them from current buffer.
     *
     * @param int $n
     *
     * @return self
     */
    public function consumeSlice(int $n): self
    {
        if ($this->size < $n) {
            throw new Exception\BufferUnderflow;
        } elseif ($this->size === $n) {
            $buffer = $this->data;

            $this->data = "";
            $this->size = 0;

            return new self($buffer);

        } else {
            $buffer = \substr($this->data, 0, $n);

            $this->data = \substr($this->data, $n);
            $this->size -= $n;

            return new self($buffer);
        }
    }

    /**
     * Appends bytes at the end of the buffer.
     *
     * @param string|self $s
     *
     * @return self
     */
    public function append($s): self
    {
        if ($s instanceof Buffer) {
            $s = $s->data;
        }

        $this->data .= $s;
        $this->size = \strlen($this->data);

        return $this;
    }

    /**
     * Reads unsigned 8-bit integer from buffer.
     *
     * @param int $offset
     *
     * @return int
     */
    public function readUint8(int $offset = 0): int
    {
        [, $ret] = \unpack("C", $this->read(1, $offset));

        return $ret;
    }

    /**
     * Reads signed 8-bit integer from buffer.
     *
     * @param int $offset
     *
     * @return int
     */
    public function readInt8(int $offset = 0): int
    {
        [, $ret] = \unpack("c", $this->read(1, $offset));

        return $ret;
    }

    /**
     * Reads and discards unsigned 8-bit integer from buffer.
     *
     * @return int
     */
    public function consumeUint8(): int
    {
        [, $ret] = \unpack("C", $this->data);

        $this->discard(1);

        return $ret;
    }

    /**
     * Reads and discards signed 8-bit integer from buffer.
     *
     * @return int
     */
    public function consumeInt8(): int
    {
        [, $ret] = \unpack("c", $this->consume(1));

        return $ret;
    }

    /**
     * Appends unsigned 8-bit integer to buffer.
     *
     * @param int $value
     *
     * @return self
     */
    public function appendUint8(int $value): self
    {
        return $this->append(\pack("C", $value));
    }

    /**
     * Appends signed 8-bit integer to buffer.
     *
     * @param int $value
     *
     * @return self
     */
    public function appendInt8(int $value): self
    {
        return $this->append(\pack("c", $value));
    }

    /**
     * Reads unsigned 16-bit integer from buffer.
     *
     * @param int $offset
     *
     * @return int
     */
    public function readUint16(int $offset = 0): int
    {
        [, $ret] = \unpack("n", $this->read(2, $offset));

        return $ret;
    }

    /**
     * Reads signed 16-bit integer from buffer.
     *
     * @param int $offset
     *
     * @return int
     */
    public function readInt16(int $offset = 0): int
    {
        $s = $this->read(2, $offset);

        [, $ret] = \unpack("s", self::$isLittleEndian ? self::swapEndian16($s) : $s);

        return $ret;
    }

    /**
     * Reads and discards unsigned 16-bit integer from buffer.
     *
     * @return int
     */
    public function consumeUint16(): int
    {
        [, $ret] = \unpack("n", $this->data);

        $this->discard(2);

        return $ret;
    }

    /**
     * Reads and discards signed 16-bit integer from buffer.
     *
     * @return int
     */
    public function consumeInt16(): int
    {
        $s = $this->consume(2);

        [, $ret] = \unpack("s", self::$isLittleEndian ? self::swapEndian16($s) : $s);

        return $ret;
    }

    /**
     * Appends unsigned 16-bit integer to buffer.
     *
     * @param int $value
     *
     * @return self
     */
    public function appendUint16(int $value): self
    {
        return $this->append(\pack("n", $value));
    }

    /**
     * Appends signed 16-bit integer to buffer.
     *
     * @param int $value
     *
     * @return self
     */
    public function appendInt16(int $value): self
    {
        $s = \pack("s", $value);

        return $this->append(self::$isLittleEndian ? self::swapEndian16($s) : $s);
    }

    /**
     * Reads unsigned 32-bit integer from buffer.
     *
     * @param int $offset
     *
     * @return int
     */
    public function readUint32(int $offset = 0): int
    {
        $s = $this->read(4, $offset);

        [, $ret] = \unpack("N", $s);

        return $ret;
    }

    /**
     * Reads signed 32-bit integer from buffer.
     *
     * @param int $offset
     *
     * @return int
     */
    public function readInt32(int $offset = 0): int
    {
        $s = $this->read(4, $offset);

        [, $ret] = \unpack("l", self::$isLittleEndian ? self::swapEndian32($s) : $s);

        return $ret;
    }

    /**
     * Reads and discards unsigned 32-bit integer from buffer.
     *
     * @return int
     */
    public function consumeUint32(): int
    {
        [, $ret] = unpack("N", $this->data);

        $this->discard(4);

        return $ret;
    }

    /**
     * Reads and discards signed 32-bit integer from buffer.
     *
     * @return int
     */
    public function consumeInt32(): int
    {
        $s = $this->consume(4);

        [, $ret] = \unpack("l", self::$isLittleEndian ? self::swapEndian32($s) : $s);

        return $ret;
    }

    /**
     * Appends unsigned 32-bit integer to buffer.
     *
     * @param int $value
     *
     * @return self
     */
    public function appendUint32(int $value): self
    {
        $s = \pack("N", $value);

        return $this->append($s);
    }

    /**
     * Appends signed 32-bit integer to buffer.
     *
     * @param int $value
     *
     * @return self
     */
    public function appendInt32(int $value): self
    {
        $s = \pack("l", $value);

        return $this->append(self::$isLittleEndian ? self::swapEndian32($s) : $s);
    }

    /**
     * Reads unsigned 64-bit integer from buffer.
     *
     * @param int $offset
     *
     * @return int
     */
    public function readUint64(int $offset = 0): int
    {
        $s = $this->read(8, $offset);

        if (self::$native64BitPack) {
            [, $ret] = \unpack("Q", self::$isLittleEndian ? self::swapEndian64($s) : $s);
        } else {
            $d = \unpack("Lh/Ll", self::$isLittleEndian ? self::swapHalvedEndian64($s) : $s);
            $ret = $d["h"] << 32 | $d["l"];
        }

        return $ret;
    }

    /**
     * Reads signed 64-bit integer from buffer.
     *
     * @param int $offset
     *
     * @return int
     */
    public function readInt64(int $offset = 0): int
    {
        $s = $this->read(8, $offset);

        if (self::$native64BitPack) {
            [, $ret] = \unpack("q", self::$isLittleEndian ? self::swapEndian64($s) : $s);
        } else {
            $d = \unpack("Lh/Ll", self::$isLittleEndian ? self::swapHalvedEndian64($s) : $s);
            $ret = $d["h"] << 32 | $d["l"];
        }

        return $ret;
    }

    /**
     * Reads and discards unsigned 64-bit integer from buffer.
     *
     * @return int
     */
    public function consumeUint64(): int
    {
        $s = $this->consume(8);

        if (self::$native64BitPack) {
            [, $ret] = \unpack("Q", self::$isLittleEndian ? self::swapEndian64($s) : $s);
        } else {
            $d = \unpack("Lh/Ll", self::$isLittleEndian ? self::swapHalvedEndian64($s) : $s);
            $ret = $d["h"] << 32 | $d["l"];
        }

        return $ret;
    }

    /**
     * Reads and discards signed 64-bit integer from buffer.
     *
     * @return int
     */
    public function consumeInt64(): int
    {
        $s = $this->consume(8);

        if (self::$native64BitPack) {
            [, $ret] = \unpack("q", self::$isLittleEndian ? self::swapEndian64($s) : $s);
        } else {
            $d = \unpack("Lh/Ll", self::$isLittleEndian ? self::swapHalvedEndian64($s) : $s);
            $ret = $d["h"] << 32 | $d["l"];
        }

        return $ret;
    }

    /**
     * Appends unsigned 64-bit integer to buffer.
     *
     * @param int $value
     *
     * @return self
     */
    public function appendUint64(int $value): self
    {
        if (self::$native64BitPack) {
            $s = \pack("Q", $value);

            if (self::$isLittleEndian) {
                $s = self::swapEndian64($s);
            }
        } else {
            $s = \pack("LL", ($value & 0xffffffff00000000) >> 32, $value & 0x00000000ffffffff);

            if (self::$isLittleEndian) {
                $s = self::swapHalvedEndian64($s);
            }
        }

        return $this->append($s);
    }

    /**
     * Appends signed 64-bit integer to buffer.
     *
     * @param int $value
     *
     * @return self
     */
    public function appendInt64(int $value): self
    {
        if (self::$native64BitPack) {
            $s = \pack("q", $value);

            if (self::$isLittleEndian) {
                $s = self::swapEndian64($s);
            }
        } else {
            $s = \pack("LL", ($value & 0xffffffff00000000) >> 32, $value & 0x00000000ffffffff);

            if (self::$isLittleEndian) {
                $s = self::swapHalvedEndian64($s);
            }
        }

        return $this->append($s);
    }

    /**
     * Reads and discards string from buffer.
     *
     * @return string
     */
    public function consumeString(): string
    {
        return $this->consume($this->consumeUint8());
    }

    /**
     * Appends string to buffer.
     *
     * @param string $value
     *
     * @return self
     */
    public function appendString(string $value): self
    {
        return $this
            ->appendUint8(\strlen($value))
            ->append($value)
        ;
    }

    /**
     * Reads and discards text from buffer.
     *
     * @return string
     */
    public function consumeText(): string
    {
        return $this->consume($this->consumeUint32());
    }

    /**
     * Appends text to buffer.
     *
     * @param string $value
     *
     * @return self
     */
    public function appendText(string $value): self
    {
        return $this
            ->appendUint32(\strlen($value))
            ->append($value)
        ;
    }

    /**
     * Reads float from buffer.
     *
     * @param int $offset
     *
     * @return float
     */
    public function readFloat(int $offset = 0): float
    {
        $s = $this->read(4, $offset);

        [, $ret] = \unpack("f", self::$isLittleEndian ? self::swapEndian32($s) : $s);

        return $ret;
    }

    /**
     * Reads and discards float from buffer.
     *
     * @return float
     */
    public function consumeFloat(): float
    {
        $s = $this->consume(4);

        [, $ret] = \unpack("f", self::$isLittleEndian ? self::swapEndian32($s) : $s);

        return $ret;
    }

    /**
     * Appends float to buffer.
     *
     * @param float $value
     *
     * @return self
     */
    public function appendFloat(float $value): self
    {
        $s = \pack("f", $value);

        return $this->append(self::$isLittleEndian ? self::swapEndian32($s) : $s);
    }

    /**
     * Reads double from buffer.
     *
     * @param int $offset
     *
     * @return float
     */
    public function readDouble(int $offset = 0): float
    {
        $s = $this->read(8, $offset);

        [, $ret] = \unpack("d", self::$isLittleEndian ? self::swapEndian64($s) : $s);

        return $ret;
    }

    /**
     * Reads and discards double from buffer.
     *
     * @return float
     */
    public function consumeDouble(): float
    {
        $s = $this->consume(8);

        [, $ret] = \unpack("d", self::$isLittleEndian ? self::swapEndian64($s) : $s);

        return $ret;
    }

    /**
     * Appends double to buffer.
     *
     * @param float $value
     * 
     * @return self
     */
    public function appendDouble($value): self
    {
        $s = \pack("d", $value);

        return $this->append(self::$isLittleEndian ? self::swapEndian64($s) : $s);
    }

    /**
     * Consumes packed bits from buffer.
     *
     * @param int $n
     *
     * @return array
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
     * Appends packed bits to buffer.
     *
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

        return $this->appendUint8($value);
    }

    /**
     * @noinspection PhpDocMissingThrowsInspection
     *
     * Consumes AMQP timestamp from buffer.
     *
     * @return \DateTimeInterface
     */
    public function consumeTimestamp(): \DateTimeInterface
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        $d = (new \DateTimeImmutable)->setTimestamp($this->consumeUint64());

        return $d;
    }

    /**
     * @param \DateTimeInterface $value
     *
     * @return self
     */
    public function appendTimestamp(\DateTimeInterface $value): self
    {
        return $this->appendUint64($value->getTimestamp());
    }

    /**
     * Consumes AMQP table from buffer.
     *
     * @return array
     */
    public function consumeTable(): array
    {
        $buffer = $this->consumeSlice($this->consumeUint32());
        $data = [];

        while (!$buffer->empty()) {
            $data[$buffer->consume($buffer->consumeUint8())] = $buffer->consumeValue();
        }

        return $data;
    }

    /**
     * Appends AMQP table to buffer.
     *
     * @param array $table
     *
     * @return self
     */
    public function appendTable(array $table): self
    {
        $buffer = new self;

        foreach ($table as $k => $v) {
            $buffer->appendUint8(\strlen($k));
            $buffer->append($k);
            $buffer->appendValue($v);
        }

        $this->appendUint32($buffer->size());

        return $this->append($buffer);
    }

    /**
     * Consumes AMQP array from buffer.
     *
     * @return array
     */
    public function consumeArray(): array
    {
        $buffer = $this->consumeSlice($this->consumeUint32());
        $data = [];

        while (!$buffer->empty()) {
            $data[] = $buffer->consumeValue();
        }

        return $data;
    }

    /**
     * Appends AMQP array to buffer.
     *
     * @param array $value
     *
     * @return self
     */
    public function appendArray(array $value): self
    {
        $buffer = new self;

        foreach ($value as $v) {
            $buffer->appendValue($v);
        }

        $this->appendUint32($buffer->size());

        return $this->append($buffer);
    }

    /**
     * Consumes AMQP decimal value.
     *
     * @return int
     */
    public function consumeDecimal(): int
    {
        $scale = $this->consumeUint8();
        $value = $this->consumeUint32();

        return $value * \pow(10, $scale);
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return $this->data;
    }

    /**
     * Consumes AMQP table/array field value.
     *
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
            case Constants::FIELD_DECIMAL_VALUE:
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
     * Appends AMQP table/array field value to buffer.
     *
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

    /**
     * Swaps 16-bit integer endianness.
     *
     * @param string $s
     *
     * @return string
     */
    private static function swapEndian16(string $s): string
    {
        return $s[1] . $s[0];
    }

    /**
     * Swaps 32-bit integer endianness.
     *
     * @param string $s
     *
     * @return string
     */
    private static function swapEndian32(string $s): string
    {
        return $s[3] . $s[2] . $s[1] . $s[0];
    }

    /**
     * Swaps 64-bit integer endianness.
     *
     * @param string $s
     *
     * @return string
     */
    private static function swapEndian64(string $s): string
    {
        return $s[7] . $s[6] . $s[5] . $s[4] . $s[3] . $s[2] . $s[1] . $s[0];
    }

    /**
     * Swaps 64-bit integer endianness so integer can be read/written as two 32-bit integers.
     *
     * @param string $s
     *
     * @return string
     */
    private static function swapHalvedEndian64(string $s): string
    {
        return $s[3] . $s[2] . $s[1] . $s[0] . $s[7] . $s[6] . $s[5] . $s[4];
    }
}