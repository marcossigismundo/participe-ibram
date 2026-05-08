<?php
/**
 * Minimal dependency injection container.
 *
 * @package Ibram\ParticipeIbram\Bootstrap
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Bootstrap;

use OutOfBoundsException;

/**
 * Lightweight DI container with factory and singleton support.
 *
 * Avoids any external dependency. Services are registered as PHP callables
 * receiving the container itself for resolution chaining.
 */
final class Container
{
    /**
     * Map of service id => factory callable.
     *
     * @var array<string, callable>
     */
    private array $factories = [];

    /**
     * Service ids that should be resolved once and cached.
     *
     * @var array<string, true>
     */
    private array $shared = [];

    /**
     * Resolved singleton instances.
     *
     * @var array<string, mixed>
     */
    private array $instances = [];

    /**
     * Register a factory. Each call to {@see get()} returns a fresh instance.
     *
     * @param string   $id      Service identifier.
     * @param callable $factory `function (Container $c): mixed`.
     */
    public function set(string $id, callable $factory): void
    {
        $this->factories[$id] = $factory;
        unset($this->shared[$id], $this->instances[$id]);
    }

    /**
     * Register a factory whose result is cached and reused.
     *
     * @param string   $id      Service identifier.
     * @param callable $factory `function (Container $c): mixed`.
     */
    public function singleton(string $id, callable $factory): void
    {
        $this->factories[$id] = $factory;
        $this->shared[$id]    = true;
        unset($this->instances[$id]);
    }

    /**
     * Resolve a service by id.
     *
     * @param string $id Service identifier.
     * @return mixed
     *
     * @throws OutOfBoundsException When the service is not registered.
     */
    public function get(string $id)
    {
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        if (!isset($this->factories[$id])) {
            throw new OutOfBoundsException(sprintf(
                'Service "%s" not registered in container.',
                $id
            ));
        }

        $instance = ($this->factories[$id])($this);

        if (isset($this->shared[$id])) {
            $this->instances[$id] = $instance;
        }

        return $instance;
    }

    /**
     * Whether a service is registered.
     *
     * @param string $id Service identifier.
     */
    public function has(string $id): bool
    {
        return isset($this->factories[$id]);
    }

    /**
     * Replace an already cached singleton (useful in tests).
     *
     * @param string $id       Service identifier.
     * @param mixed  $instance Pre-built instance to store.
     */
    public function instance(string $id, $instance): void
    {
        $this->instances[$id] = $instance;
        $this->shared[$id]    = true;
        if (!isset($this->factories[$id])) {
            $this->factories[$id] = static function () use ($instance) {
                return $instance;
            };
        }
    }
}
