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

final class Message
{
    /**
     * @var string
     */
    private $content;

    /**
     * @var string
     */
    private $exchange;

    /**
     * @var string
     */
    private $routingKey;

    /**
     * @var string
     */
    private $consumerTag;

    /**
     * @var int
     */
    private $deliveryTag;

    /**
     * @var boolean
     */
    private $redelivered;

    /**
     * @var array
     */
    private $headers;

    /**
     * @param string  $exchange
     * @param string  $content
     * @param string  $routingKey
     * @param string  $consumerTag
     * @param int     $deliveryTag
     * @param boolean $redelivered
     * @param array   $headers
     */
    public function __construct(
        string $content,
        string $exchange,
        string $routingKey,
        string $consumerTag = null,
        int $deliveryTag = null,
        bool $redelivered = false,
        array $headers = []
    ) {
        $this->content     = $content;
        $this->exchange    = $exchange;
        $this->routingKey  = $routingKey;
        $this->consumerTag = $consumerTag;
        $this->deliveryTag = $deliveryTag;
        $this->redelivered = $redelivered;
        $this->headers     = $headers;
    }

    /**
     * @return string
     */
    public function content(): string
    {
        return $this->content;
    }

    /**
     * @return string
     */
    public function exchange(): string
    {
        return $this->exchange;
    }

    /**
     * @return string
     */
    public function routingKey(): string
    {
        return $this->routingKey;
    }

    /**
     * @return string
     */
    public function consumerTag(): ?string
    {
        return $this->consumerTag;
    }

    /**
     * @return int
     */
    public function deliveryTag(): ?int
    {
        return $this->deliveryTag;
    }

    /**
     * @return bool
     */
    public function redelivered(): bool
    {
        return $this->redelivered;
    }

    /**
     * @return array
     */
    public function headers(): array
    {
        return $this->headers;
    }

    /**
     * Returns header or default value.
     *
     * @param string $name
     * @param mixed  $default
     *
     * @return mixed
     */
    public function header(string $name, $default = null)
    {
        return $this->headers[$name] ?? $default;
    }
}
