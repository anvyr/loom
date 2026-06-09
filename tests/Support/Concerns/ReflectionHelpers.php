<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\Support\Concerns;

use ReflectionClass;

/**
 * Convenience wrappers for accessing private/protected members via reflection.
 */
trait ReflectionHelpers
{
    /**
     * @param object|class-string $target  Object instance or class name (for static properties).
     */
    protected function getPrivateProperty(object|string $target, string $property): mixed
    {
        $ref = new ReflectionClass($target);
        $prop = $ref->getProperty($property);

        return $prop->getValue(is_object($target) ? $target : null);
    }

    /**
     * @param object|class-string $target  Object instance or class name (for static properties).
     */
    protected function setPrivateProperty(object|string $target, string $property, mixed $value): void
    {
        $ref = new ReflectionClass($target);
        $prop = $ref->getProperty($property);
        $prop->setValue(is_object($target) ? $target : null, $value);
    }

    /**
     * @param object|class-string $target  Object instance or class name (for static methods).
     */
    protected function callPrivateMethod(object|string $target, string $method, array $args = []): mixed
    {
        $ref = new ReflectionClass($target);
        $reflectionMethod = $ref->getMethod($method);

        return $reflectionMethod->invokeArgs(is_object($target) ? $target : null, $args);
    }

    /**
     * Instantiate an object without running its constructor.
     *
     * @template TObject of object
     *
     * @param class-string<TObject> $class
     *
     * @return TObject
     */
    protected function instantiateWithoutConstructor(string $class): object
    {
        $ref = new ReflectionClass($class);

        return $ref->newInstanceWithoutConstructor();
    }
}
