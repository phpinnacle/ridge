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

use PHPinnacle\Ridge\Exception\ConfigurationException;

final class Config
{
    private const   DEFAULT_HOST  = 'localhost';
    private const   DEFAULT_PORT  = 5672;
    private const   DEFAULT_VHOST = '/';
    private const   DEFAULT_USER  = 'guest';
    private const   DEFAULT_PASS  = 'guest';

    /**
     * @var string
     */
    public $host;

    /**
     * @var int
     */
    public $port;

    /**
     * @var string
     */
    public $user;

    /**
     * @var string
     */
    public $pass;

    /**
     * @var string
     */
    public $vhost;

    /**
     * Connection timeout (in milliseconds)
     *
     * @var int
     */
    public $timeout = 1000;

    /**
     * Heartbeat interval (in milliseconds)
     *
     * @var int
     */
    public $heartbeat = 60000;

    /**
     * @var int
     */
    public $qosSize = 0;

    /**
     * @var int
     */
    public $qosCount = 0;

    /**
     * @var bool
     */
    public $qosGlobal = false;

    /**
     * @var bool
     */
    public $tcpNoDelay = false;

    /**
     * @var int
     */
    public $tcpAttempts = 2;

    /**
     * @var int
     */
    public $maxChannel = 0xFFFF;

    /**
     * @var int
     */
    public $maxFrame = 0xFFFF;

    public function __construct(
        string $host = self::DEFAULT_HOST,
        int $port = self::DEFAULT_PORT,
        string $user = self::DEFAULT_USER,
        string $pass = self::DEFAULT_PASS,
        string $vhost = null
    )
    {
        $this->host  = $host;
        $this->port  = $port;
        $this->user  = $user;
        $this->pass  = $pass;
        $this->vhost = $vhost ?: self::DEFAULT_VHOST;
    }

    /**
     * @throws \PHPinnacle\Ridge\Exception\ConfigurationException
     */
    public static function parse(string $dsn): self
    {
        if($dsn === '')
        {
            throw ConfigurationException::emptyDSN();
        }

        $parts = \parse_url($dsn);

        if($parts === false)
        {
            throw ConfigurationException::incorrectDSN($dsn);
        }

        \parse_str($parts['query'] ?? '', $options);

        if(isset($parts['path']) && $parts['path'] !== '')
        {
            /** @var string|false $vhost */
            $vhost = \substr($parts['path'], 1);

            if($vhost !== false)
            {
                $parts['path'] = $vhost;
            }
        }

        $self = new self(
            $parts['host'] ?? self::DEFAULT_HOST,
            $parts['port'] ?? self::DEFAULT_PORT,
            $parts['user'] ?? self::DEFAULT_USER,
            $parts['pass'] ?? self::DEFAULT_PASS,
            $parts['path'] ?? self::DEFAULT_VHOST,
        );

        if(isset($options['timeout']))
        {
            $self->timeout = (int) $options['timeout'];
        }

        if(isset($options['heartbeat']))
        {
            $self->heartbeat = (int) $options['heartbeat'];
        }

        if(isset($options['max_frame']))
        {
            $self->maxFrame = (int) $options['max_frame'];
        }

        if(isset($options['max_channel']))
        {
            $self->maxChannel = (int) $options['max_channel'];
        }

        if(isset($options['qos_size']))
        {
            $self->qosSize = (int) $options['qos_size'];
        }

        if(isset($options['qos_count']))
        {
            $self->qosCount = (int) $options['qos_count'];
        }

        if(isset($options['qos_global']))
        {
            $self->qosGlobal = (bool) $options['qos_global'];
        }

        if(isset($options['tcp_nodelay']))
        {
            $self->tcpNoDelay = (bool) $options['tcp_nodelay'];
        }

        if(isset($options['tcp_attempts']))
        {
            $self->tcpAttempts = (int) $options['tcp_attempts'];
        }

        return $self;
    }

    public function uri(): string
    {
        return \sprintf('tcp://%s:%d', $this->host, $this->port);
    }
}
