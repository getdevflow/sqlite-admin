<?php

declare(strict_types=1);

namespace Plugin\SqliteAdmin\Security;

use App\Application\Devflow;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Qubus\Exception\Data\TypeException;
use Qubus\Exception\Exception;
use ReflectionException;

final readonly class SqliteTableScope
{
    public function basePrefix(): string
    {
        return (string) Devflow::db()->basePrefix;
    }

    public function sitePrefix(): string
    {
        return (string) Devflow::db()->prefix;
    }

    /**
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws ContainerExceptionInterface
     * @throws TypeException
     * @throws InvalidArgumentException
     * @throws Exception
     */
    public function canSeeTable(string $table, DatabaseAccessPolicy $policy): bool
    {
        if ($policy->canSeeAllTables()) {
            return true;
        }

        return str_starts_with($table, $this->sitePrefix());
    }

    /**
     * Filters either:
     *
     * - list<string>
     * - list<array{name:string}>
     *
     * @param list<string|array<string,mixed>> $tables
     * @param DatabaseAccessPolicy $policy
     * @return list<string|array<string,mixed>>
     * @throws ContainerExceptionInterface
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     */
    public function filter(array $tables, DatabaseAccessPolicy $policy): array
    {
        return array_values(array_filter(
            $tables,
            function (string|array $item) use ($policy): bool {
                $table = is_array($item)
                    ? (string) ($item['name'] ?? '')
                    : $item;

                if ($table === '') {
                    return false;
                }

                return $this->canSeeTable($table, $policy);
            }
        ));
    }
}
