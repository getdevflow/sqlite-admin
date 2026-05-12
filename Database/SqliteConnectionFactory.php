<?php

declare(strict_types=1);

namespace Plugin\SqliteAdmin\Database;

use PDO;
use Plugin\SqliteAdmin\DomainLocaleAware;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Qubus\Config\ConfigContainer;
use Qubus\Exception\Data\TypeException;
use Qubus\Expressive\ParsePdoDsn;
use ReflectionException;
use RuntimeException;

use function Qubus\Security\Helpers\t__;

final readonly class SqliteConnectionFactory
{
    use DomainLocaleAware;

    public function __construct(private ConfigContainer $config)
    {
    }

    /**
     * @return PDO
     * @throws TypeException
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     */
    public function make(): PDO
    {
        $path = $this->path();

        if ($path === '') {
            throw new RuntimeException(t__('SQLite database path is not configured.', $this->domain()));
        }

        $realPath = realpath($path);

        if ($realPath === false || ! is_file($realPath)) {
            throw new RuntimeException(t__('SQLite database file could not be found.', $this->domain()));
        }

        if (! is_readable($realPath)) {
            throw new RuntimeException(t__('SQLite database file is not readable.', $this->domain()));
        }

        $pdo = new PDO('sqlite:' . $realPath, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA busy_timeout = 5000');

        return $pdo;
    }

    /**
     * @throws TypeException
     */
    public function path(): string
    {
        $dsn = ParsePdoDsn::fromString($this->config->string(key: 'database.connections.sqlite.dsn'));
        return $dsn->path();
    }
}
