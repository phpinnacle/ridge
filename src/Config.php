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
    private const
        DEFAULT_HOST  = 'localhost',
        DEFAULT_PORT  = 5672,
        DEFAULT_VHOST = '/',
        DEFAULT_USER  = 'guest',
        DEFAULT_PASS  = 'guest'
    ;

    private const OPTIONS = [
        'timeout'      => 1000,
        'heartbeat'    => 1000,
        'qos_count'    => 0,
        'qos_size'     => 0,
        'qos_global'   => false,
        'tcp_nodelay'  => false,
        'tcp_attempts' => 2,
        'max_frame'    => 0xFFFF,
        'max_channel'  => 0xFFFF,
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
     * @var bool
     */
    private $tcpNoDelay = false;

    /**
     * @var int
     */
    private $tcpAttempts = 2;

    /**
     * @var int
     */
    private $maxChannel = 0xFFFF;

    /**
     * @var int
     */
    private $maxFrame = 0xFFFF;

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
    public static function parse(string $dsn): self
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

        $self->maxFrame   = \filter_var($options['max_frame'], FILTER_VALIDATE_INT);
        $self->maxChannel = \filter_var($options['max_channel'], FILTER_VALIDATE_INT);

        $self->qosSize   = \filter_var($options['qos_size'], FILTER_VALIDATE_INT);
        $self->qosCount  = \filter_var($options['qos_count'], FILTER_VALIDATE_INT);
        $self->qosGlobal = \filter_var($options['qos_global'], FILTER_VALIDATE_BOOLEAN);

        $self->tcpNoDelay  = \filter_var($options['tcp_nodelay'], FILTER_VALIDATE_BOOLEAN);
        $self->tcpAttempts = \filter_var($options['tcp_attempts'], FILTER_VALIDATE_INT);

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
     * @param string|null $value
     *
     * @return string
     */
    public function user(string $value = null): string
    {
        return \is_null($value) ? $this->user : $this->user = $value;
    }

    /**
     * @param string|null $value
     *
     * @return string
     */
    public function password(string $value = null): string
    {
        return \is_null($value) ? $this->pass : $this->pass = $value;
    }

    /**
     * @param string|null $value
     *
     * @return string
     */
    public function vhost(string $value = null): string
    {
        return \is_null($value) ? $this->vhost : $this->vhost = $value;
    }

    /**
     * @param int|null $value
     *
     * @return int
     */
    public function timeout(int $value = null): int
    {
        return \is_null($value) ? $this->timeout : $this->timeout = $value;
    }

    /**
     * @param int|null $value
     *
     * @return int
     */
    public function heartbeat(int $value = null): int
    {
        return \is_null($value) ? $this->heartbeat : $this->heartbeat = $value;
    }

    /**
     * @param int|null $value
     *
     * @return int
     */
    public function qosSize(int $value = null): int
    {
        return \is_null($value) ? $this->qosSize : $this->qosSize = $value;
    }

    /**
     * @param int|null $value
     *
     * @return int
     */
    public function qosCount(int $value = null): int
    {
        return \is_null($value) ? $this->qosCount : $this->qosCount = $value;
    }

    /**
     * @param bool|null $value
     *
     * @return bool
     */
    public function qosGlobal(bool $value = null): bool
    {
        return \is_null($value) ? $this->qosGlobal : $this->qosGlobal = $value;
    }

    /**
     * @param bool|null $value
     *
     * @return bool
     */
    public function tcpNoDelay(bool $value = null): bool
    {
        return \is_null($value) ? $this->tcpNoDelay : $this->tcpNoDelay = $value;
    }

    /**
     * @param int|null $value
     *
     * @return int
     */
    public function tcpAttempts(int $value = null): int
    {
        return \is_null($value) ? $this->tcpAttempts : $this->tcpAttempts = $value;
    }

    /**
     * @param int|null $value
     *
     * @return int
     */
    public function maxChannel(int $value = null): int
    {
        return \is_null($value) ? $this->maxChannel : $this->maxChannel = $value;
    }

    /**
     * @param int|null $value
     *
     * @return int
     */
    public function maxFrame(int $value = null): int
    {
        return \is_null($value) ? $this->maxFrame : $this->maxFrame = $value;
    }
}
