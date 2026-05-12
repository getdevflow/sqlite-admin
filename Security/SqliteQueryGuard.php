<?php

declare(strict_types=1);

namespace Plugin\SqliteAdmin\Security;

use InvalidArgumentException;
use Plugin\SqliteAdmin\Database\SqliteStatementSplitter;
use Plugin\SqliteAdmin\DomainLocaleAware;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Qubus\Exception\Data\TypeException;
use Qubus\Exception\Exception;
use ReflectionException;

use function Qubus\Security\Helpers\t__;

final readonly class SqliteQueryGuard
{
    use DomainLocaleAware;

    public function __construct(
        private DatabaseAccessPolicy $policy,
        private SqliteTableScope $scope,
        private SqliteStatementSplitter $splitter,
    ) {
    }

    /**
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws ContainerExceptionInterface
     * @throws TypeException
     * @throws \Psr\SimpleCache\InvalidArgumentException
     * @throws Exception
     */
    public function assertAllowed(string $sql): void
    {
        $statements = $this->splitter->split($sql);

        if ($statements === []) {
            throw new InvalidArgumentException(t__('SQL query is empty.', $this->domain()));
        }

        if (! $this->policy->canRunWriteSql() && count($statements) > 1) {
            throw new InvalidArgumentException(
                t__('Subsite users may only run one read-only query at a time.', $this->domain())
            );
        }

        foreach ($statements as $statement) {
            $this->assertStatementAllowed($statement);
        }
    }

    /**
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     * @throws \Psr\SimpleCache\InvalidArgumentException
     * @throws Exception
     */
    private function assertStatementAllowed(string $sql): void
    {
        if ($this->policy->canRunWriteSql()) {
            return;
        }

        $normalized = strtolower(ltrim($sql));

        if (
                ! str_starts_with($normalized, 'select')
                && ! str_starts_with($normalized, 'pragma table_info')
                && ! str_starts_with($normalized, 'pragma index_list')
                && ! str_starts_with($normalized, 'pragma table_xinfo')
        ) {
            throw new InvalidArgumentException(
                t__('Only read-only SELECT and safe PRAGMA queries are allowed.', $this->domain())
            );
        }

        preg_match_all(
            '/\\b(?:from|join)\\s+[\"`]?([A-Za-z_][A-Za-z0-9_]*)[\"`]?/i',
            $sql,
            $matches
        );

        foreach ($matches[1] ?? [] as $table) {
            if (! str_starts_with($table, $this->scope->sitePrefix())) {
                throw new InvalidArgumentException(
                    t__('This query references a table outside the current site.', $this->domain())
                );
            }
        }
    }
}
