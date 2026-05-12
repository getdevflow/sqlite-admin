<?php

declare(strict_types=1);

namespace Plugin\SqliteAdmin\Security;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Qubus\Exception\Data\TypeException;
use Qubus\Exception\Exception;
use ReflectionException;

use function App\Shared\Helpers\current_user_can;
use function App\Shared\Helpers\is_main_site;
use function App\Shared\Helpers\is_super_admin;

final class DatabasePermission
{
    /**
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     * @throws InvalidArgumentException
     * @throws Exception
     * @throws ReflectionException
     * @throws TypeException
     */
    public function canAccess(?string $siteId = null, ?string $userId = null): bool
    {
        if (is_main_site($siteId) && is_super_admin($userId)) {
            return true;
        }

        return current_user_can('database:manage');
    }

    /**
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     * @throws InvalidArgumentException
     * @throws Exception
     */
    public function canRunDangerousQueries(?string $siteId = null, ?string $userId = null): bool
    {
        return is_main_site($siteId) && is_super_admin($userId);
    }
}
