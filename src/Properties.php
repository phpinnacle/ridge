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

final class Properties
{
    const UNKNOWN = 'unknown';

    /**
     * @var string
     */
    private $platform;

    /**
     * @var string
     */
    private $product;

    /**
     * @var string
     */
    private $version;

    /**
     * @example
     *   publisher_confirms
     *   exchange_exchange_bindings
     *   basic.nack
     *   consumer_cancel_notify
     *   connection.blocked
     *   consumer_priorities
     *   authentication_failure_close
     *   per_consumer_qos
     *   direct_reply_to
     *
     * @var bool[]
     */
    private $capabilities = [];

    /**
     * @var int
     */
    private $maxChannel = 0xFFFF;

    /**
     * @var int
     */
    private $maxFrame = 0xFFFF;

    /**
     * @param string $platform
     * @param string $product
     * @param string $version
     * @param array  $capabilities
     */
    public function __construct(string $platform, string $product, string $version, array $capabilities)
    {
        $this->platform     = $platform;
        $this->product      = $product;
        $this->version      = $version;
        $this->capabilities = $capabilities;
    }

    /**
     * @param array $config
     *
     * @return self
     */
    public static function create(array $config): self
    {
        return new self(
            $config['platform'] ?? self::UNKNOWN,
            $config['product'] ?? self::UNKNOWN,
            $config['version'] ?? self::UNKNOWN,
            $config['capabilities'] ?? []
        );
    }

    /**
     * @param int $maxChannel
     * @param int $maxFrame
     */
    public function tune(int $maxChannel, int $maxFrame): void
    {
        $this->maxChannel = $maxChannel;
        $this->maxFrame   = $maxFrame;
    }

    /**
     * @param string $ability
     *
     * @return bool
     */
    public function capable(string $ability): bool
    {
        return $this->capabilities[$ability] ?? false;
    }

    /**
     * @return string
     */
    public function platform(): string
    {
        return $this->platform;
    }

    /**
     * @return string
     */
    public function product(): string
    {
        return $this->product;
    }

    /**
     * @return string
     */
    public function version(): string
    {
        return $this->version;
    }

    /**
     * @return int
     */
    public function maxFrame(): int
    {
        return $this->maxFrame;
    }

    /**
     * @return int
     */
    public function maxChannel(): int
    {
        return $this->maxChannel;
    }
}

