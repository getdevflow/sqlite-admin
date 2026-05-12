<?php

declare(strict_types=1);

namespace Plugin\SqliteAdmin\Security;

use Plugin\SqliteAdmin\DomainLocaleAware;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use ReflectionException;
use RuntimeException;

use function Qubus\Security\Helpers\t__;

final readonly class SqliteTriggerGuard
{
    use DomainLocaleAware;

    /**
     * @throws ReflectionException
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function assertSafe(string $statement): void
    {
        $normalized = strtoupper(trim($statement));

        if ($normalized === '') {
            throw new RuntimeException(t__('Trigger statement cannot be empty.', $this->domain()));
        }

        $blocked = [
            'ATTACH DATABASE',
            'DETACH DATABASE',
            'VACUUM',
            'PRAGMA',
            'DROP TABLE',
            'DROP TRIGGER',
            'ALTER TABLE',
            'CREATE TRIGGER',
            'CREATE TABLE',
        ];

        foreach ($blocked as $phrase) {
            if (str_contains($normalized, $phrase)) {
                throw new RuntimeException(
                    sprintf(
                        t__('Trigger statement contains forbidden operation: %s', $this->domain()),
                        $phrase
                    )
                );
            }
        }
    }
}
