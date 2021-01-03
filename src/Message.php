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
     * @psalm-readonly
     *
     * @var string
     */
    public $content;

    /**
     * @psalm-readonly
     *
     * @var string
     */
    public $exchange;

    /**
     * @psalm-readonly
     *
     * @var string
     */
    public $routingKey;

    /**
     * @psalm-readonly
     *
     * @var string|null
     */
    public $consumerTag;

    /**
     * @psalm-readonly
     *
     * @var int|null
     */
    public $deliveryTag;

    /**
     * @psalm-readonly
     *
     * @var bool
     */
    public $redelivered;

    /**
     * @psalm-readonly
     *
     * @var bool
     */
    public $returned;

    /**
     * @psalm-readonly
     *
     * @var array
     */
    public $headers;

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
        $this->content = $content;
        $this->exchange = $exchange;
        $this->routingKey = $routingKey;
        $this->consumerTag = $consumerTag;
        $this->deliveryTag = $deliveryTag;
        $this->redelivered = $redelivered;
        $this->returned = $returned;
        $this->headers = $headers;
    }

    public function header(string $name, mixed $default = null): mixed
    {
        return $this->headers[$name] ?? $default;
    }
}
