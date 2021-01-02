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
     * @var string|null
     */
    private $consumerTag;

    /**
     * @var int|null
     */
    private $deliveryTag;

    /**
     * @var bool
     */
    private $redelivered;

    /**
     * @var bool
     */
    private $returned;

    /**
     * @var array
     */
    private $headers;

    public function __construct(
        string $content,
        string $exchange,
        string $routingKey,
        ?string $consumerTag = null,
        ?int $deliveryTag = null,
        bool $redelivered = false,
        bool $returned = false,
        array $headers = []
    ) {
        $this->content     = $content;
        $this->exchange    = $exchange;
        $this->routingKey  = $routingKey;
        $this->consumerTag = $consumerTag;
        $this->deliveryTag = $deliveryTag;
        $this->redelivered = $redelivered;
        $this->returned    = $returned;
        $this->headers     = $headers;
    }

    public function content(): string
    {
        return $this->content;
    }

    public function exchange(): string
    {
        return $this->exchange;
    }

    public function routingKey(): string
    {
        return $this->routingKey;
    }

    public function consumerTag(): ?string
    {
        return $this->consumerTag;
    }

    public function deliveryTag(): ?int
    {
        return $this->deliveryTag;
    }

    public function redelivered(): bool
    {
        return $this->redelivered;
    }

    public function returned(): bool
    {
        return $this->returned;
    }

    public function headers(): array
    {
        return $this->headers;
    }

    public function header(string $name, mixed $default = null): mixed
    {
        return $this->headers[$name] ?? $default;
    }
}
