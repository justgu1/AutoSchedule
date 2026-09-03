<?php

declare(strict_types=1);

namespace App\Infrastructure\Container;

final class Container
{
    /** @var array<class-string, \Closure(self): object> */
    private array $bindings = [];

    /** @var array<class-string, object> */
    private array $instances = [];

    /** @var list<class-string> */
    private array $resolving = [];

    /** @param \Closure(self): object $factory */
    public function set(string $id, \Closure $factory): void
    {
        $this->bindings[$id] = $factory;
        unset($this->instances[$id]);
    }

    public function get(string $id): object
    {
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        $instance = $this->build($id);
        $this->instances[$id] = $instance;

        return $instance;
    }

    private function build(string $id): object
    {
        if (in_array($id, $this->resolving, true)) {
            throw ContainerException::circularDependency([...$this->resolving, $id]);
        }

        $this->resolving[] = $id;

        try {
            return isset($this->bindings[$id])
                ? ($this->bindings[$id])($this)
                : $this->autowire($id);
        } finally {
            array_pop($this->resolving);
        }
    }

    /** @param class-string $class */
    private function autowire(string $class): object
    {
        if (!class_exists($class) && !interface_exists($class)) {
            throw ContainerException::classNotFound($class);
        }

        $reflection = new \ReflectionClass($class);

        if (!$reflection->isInstantiable()) {
            throw ContainerException::notInstantiable($class);
        }

        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            return new $class();
        }

        $arguments = array_map(
            fn (\ReflectionParameter $parameter): mixed => $this->resolveParameter($class, $parameter),
            $constructor->getParameters(),
        );

        return $reflection->newInstanceArgs($arguments);
    }

    private function resolveParameter(string $class, \ReflectionParameter $parameter): mixed
    {
        $type = $parameter->getType();

        if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
            /** @var class-string $typeName */
            $typeName = $type->getName();

            return $this->get($typeName);
        }

        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        throw ContainerException::unresolvableParameter(
            $class,
            $parameter->getName(),
            $type instanceof \ReflectionNamedType ? $type->getName() : 'mixed',
        );
    }
}
