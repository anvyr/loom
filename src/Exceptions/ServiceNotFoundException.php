<?php

declare(strict_types=1);

namespace Anvyr\Loom\Exceptions;

class ServiceNotFoundException extends ContainerException implements \Psr\Container\NotFoundExceptionInterface
{
}
