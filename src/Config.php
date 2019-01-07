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

final class Config
{
    const
        DEFAULT_HOST  = 'localhost',
        DEFAULT_PORT  = 5672,
        DEFAULT_VHOST = '/',
        DEFAULT_USER  = 'guest',
        DEFAULT_PASS  = 'guest'
    ;

    const OPTIONS = [
        'timeout'    => 1000,
        'heartbeat'  => 1000,
        'qos_count'  => 0,
        'qos_size'   => 0,
        'qos_global' => false
    ];

    /**
     * @var string
     */
    private $host;

    /**
     * @var int
     */
    private $port;

    /**
     * @var string
     */
    private $user;

    /**
     * @var string
     */
    private $pass;

    /**
     * @var string
     */
    private $vhost;

    /**
     * @var int
     */
    private $timeout = 1;

    /**
     * @var int
     */
    private $heartbeat = 60;

    /**
     * @var int
     */
    private $qosSize = 0;

    /**
     * @var int
     */
    private $qosCount = 0;

    /**
     * @var bool
     */
    private $qosGlobal = false;

    /**
     * @param string $host
     * @param int    $port
     * @param string $vhost
     * @param string $user
     * @param string $pass
     */
    public function __construct(string $host, int $port, string $vhost = null, string $user = null, string $pass = null)
    {
        $this->host  = $host;
        $this->port  = $port;
        $this->vhost = $vhost ?: self::DEFAULT_VHOST;
        $this->user  = $user ?: self::DEFAULT_USER;
        $this->pass  = $pass ?: self::DEFAULT_PASS;
    }

    /**
     * @param string $dsn
     *
     * @return self
     */
    public static function dsn(string $dsn): self
    {
        $parts = \parse_url($dsn);

        \parse_str($parts['query'] ?? '', $query);

        $options = \array_replace(self::OPTIONS, $query);

        $self = new self(
            $parts['host'] ?? self::DEFAULT_HOST,
            $parts['port'] ?? self::DEFAULT_PORT,
            $parts['path'] ?? self::DEFAULT_VHOST,
            $parts['user'] ?? self::DEFAULT_USER,
            $parts['pass'] ?? self::DEFAULT_PASS
        );

        $self->timeout   = \filter_var($options['timeout'], FILTER_VALIDATE_INT);
        $self->heartbeat = \filter_var($options['heartbeat'], FILTER_VALIDATE_INT);
        $self->qosSize   = \filter_var($options['qos_size'], FILTER_VALIDATE_INT);
        $self->qosCount  = \filter_var($options['qos_count'], FILTER_VALIDATE_INT);
        $self->qosGlobal = \filter_var($options['qos_global'], FILTER_VALIDATE_BOOLEAN);

        return $self;
    }

    /**
     * @return string
     */
    public function uri(): string
    {
        return \sprintf('tcp://%s:%d', $this->host, $this->port);
    }

    /**
     * @return string
     */
    public function host(): string
    {
        return $this->host;
    }

    /**
     * @return int
     */
    public function port(): int
    {
        return $this->port;
    }

    /**
     * @return string
     */
    public function user(): ?string
    {
        return $this->user;
    }

    /**
     * @return string
     */
    public function password(): ?string
    {
        return $this->pass;
    }

    /**
     * @return string
     */
    public function vhost(): string
    {
        return $this->vhost;
    }

    /**
     * @return int
     */
    public function timeout(): int
    {
        return $this->timeout;
    }

    /**
     * @return int
     */
    public function heartbeat(): int
    {
        return $this->heartbeat;
    }

    /**
     * @return int
     */
    public function qosSize(): int
    {
        return $this->qosSize;
    }

    /**
     * @return int
     */
    public function qosCount(): int
    {
        return $this->qosCount;
    }

    /**
     * @return bool
     */
    public function qosGlobal(): bool
    {
        return $this->qosGlobal;
    }
}
