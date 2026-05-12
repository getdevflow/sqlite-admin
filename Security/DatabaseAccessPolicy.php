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

final class DatabaseAccessPolicy
{
    /**
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     * @throws InvalidArgumentException
     * @throws Exception
     */
    public function canAccess(?string $siteId = null, ?string $userId = null): bool
    {
        return $this->isMainSiteSuperAdmin($siteId, $userId)
        || current_user_can('database:manage');
    }

    /**
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws ContainerExceptionInterface
     * @throws TypeException
     * @throws InvalidArgumentException
     * @throws Exception
     */
    public function canSeeAllTables(?string $siteId = null, ?string $userId = null): bool
    {
        return $this->isMainSiteSuperAdmin($siteId, $userId);
    }

    /**
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     * @throws InvalidArgumentException
     * @throws Exception
     */
    public function canCreateTable(?string $siteId = null, ?string $userId = null): bool
    {
        return $this->canAccess($siteId, $userId);
    }

    /**
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws ContainerExceptionInterface
     * @throws TypeException
     * @throws InvalidArgumentException
     * @throws Exception
     */
    public function canDropTable(?string $siteId = null, ?string $userId = null): bool
    {
        return $this->isMainSiteSuperAdmin($siteId, $userId)
        || current_user_can('database:drop');
    }

    /**
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     * @throws InvalidArgumentException
     * @throws Exception
     */
    public function canRunWriteSql(?string $siteId = null, ?string $userId = null): bool
    {
        return $this->isMainSiteSuperAdmin($siteId, $userId);
    }

    /**
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     * @throws InvalidArgumentException
     * @throws Exception
     */
    public function canDownloadDatabase(?string $siteId = null, ?string $userId = null): bool
    {
        return $this->isMainSiteSuperAdmin($siteId, $userId);
    }

    /**
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     * @throws InvalidArgumentException
     * @throws Exception
     */
    private function isMainSiteSuperAdmin(?string $siteId = null, ?string $userId = null): bool
    {
        return is_main_site($siteId) && is_super_admin($userId);
    }
}
