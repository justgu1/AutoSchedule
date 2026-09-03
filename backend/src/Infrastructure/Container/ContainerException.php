<?php

declare(strict_types=1);

namespace App\Infrastructure\Container;

final class ContainerException extends \RuntimeException
{
    private function __construct(string $message)
    {
        parent::__construct($message);
    }

    public static function classNotFound(string $class): self
    {
        return new self(sprintf('Cannot resolve "%s" — class does not exist.', $class));
    }

    public static function notInstantiable(string $class): self
    {
        return new self(sprintf(
            'Cannot resolve "%s" — it is an interface or abstract class with no binding registered.',
            $class,
        ));
    }

    public static function unresolvableParameter(string $class, string $parameter, string $type): self
    {
        return new self(sprintf(
            'Cannot resolve parameter $%s of type "%s" in %s::__construct — no binding registered and the type cannot be autowired.',
            $parameter,
            $type,
            $class,
        ));
    }

    /** @param list<string> $chain */
    public static function circularDependency(array $chain): self
    {
        return new self('Circular dependency detected: ' . implode(' -> ', $chain));
    }
}
