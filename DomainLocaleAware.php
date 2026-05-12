<?php

declare(strict_types=1);

namespace Plugin\SqliteAdmin;

use App\Shared\Services\Registry;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use ReflectionException;

trait DomainLocaleAware
{
    /**
     * @throws ReflectionException
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    private function domain(): string
    {
        return Registry::getInstance()->get('sqlite-admin')['id'];
    }
}
