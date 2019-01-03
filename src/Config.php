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
    private $timeout;

    /**
     * @var int
     */
    private $heartbeat;

    /**
     * @var int
     */
    private $qosSize;

    /**
     * @var int
     */
    private $qosCount;

    /**
     * @var bool
     */
    private $qosGlobal;

    /**
     * @param string $host
     * @param int    $port
     * @param string $vhost
     * @param string $user
     * @param string $pass
     */
    public function __construct(string $host, int $port, string $vhost, string $user = null, string $pass = null)
    {
        $this->host  = $host;
        $this->port  = $port;
        $this->vhost = $vhost;
        $this->user  = $user;
        $this->pass  = $pass;
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

        $self->timeout   = \filter_var($options['timeout'], FILTER_VALIDATE_FLOAT);
        $self->heartbeat = \filter_var($options['heartbeat'], FILTER_VALIDATE_FLOAT);
        $self->qosSize   = \filter_var($options['qos_size'], FILTER_VALIDATE_INT);
        $self->qosCount  = \filter_var($options['qos_count'], FILTER_VALIDATE_INT);
        $self->qosGlobal = \filter_var($options['qos_global'], FILTER_VALIDATE_BOOLEAN);

        return $self;
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
     * @return float
     */
    public function timeout(): float
    {
        return $this->timeout;
    }

    /**
     * @return float
     */
    public function heartbeat(): float
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
