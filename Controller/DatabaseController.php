<?php

declare(strict_types=1);

namespace Plugin\SqliteAdmin\Controller;

use Exception;
use Plugin\SqliteAdmin\Database\SqliteConnectionFactory;
use Plugin\SqliteAdmin\Database\SqliteDatabase;
use Plugin\SqliteAdmin\Database\SqliteSchemaReader;
use Plugin\SqliteAdmin\DomainLocaleAware;
use Plugin\SqliteAdmin\Security\DatabaseAccessPolicy;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Qubus\Exception\Data\TypeException;
use ReflectionException;

use function App\Shared\Helpers\admin_url;
use function Codefy\Framework\Helpers\view;
use function Qubus\Security\Helpers\t__;

final readonly class DatabaseController
{
    use DomainLocaleAware;

    public function __construct(
        private DatabaseAccessPolicy $policy,
        private SqliteSchemaReader $schema,
        private SqliteDatabase $database,
        private SqliteConnectionFactory $factory,
    ) {
    }

    /**
     * @return ResponseInterface
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws InvalidArgumentException
     * @throws TypeException
     * @throws \Qubus\Exception\Exception
     * @throws ReflectionException
     * @throws Exception
     */
    public function dashboard(): ResponseInterface
    {
        if (! $this->policy->canAccess()) {
            return view('plugin::SqliteAdmin/view/error/forbidden');
        }

        $path = realpath($this->factory->path()) ?: $this->factory->path();

        return view('plugin::SqliteAdmin/view/database/dashboard', [
            'title' => t__('SQLite Manager', $this->domain()),
            'tables' => $this->schema->tablesAndViews(),
            'databasePath' => $path,
            'databaseSize' => is_file($path) ? filesize($path) : 0,
            'databaseModified' => is_file($path) ? filemtime($path) : null,
            'sqliteVersion' => $this->database->pdo()->query('SELECT sqlite_version()')->fetchColumn(),
        ]);
    }

    /**
     * @return ResponseInterface
     * @throws ContainerExceptionInterface
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     * @throws \Qubus\Exception\Exception
     * @throws Exception
     */
    public function structure(): ResponseInterface
    {
        if (! $this->policy->canAccess()) {
            return view('plugin::SqliteAdmin/view/error/forbidden');
        }

        return view('plugin::SqliteAdmin/view/database/structure', [
            'title' => t__('Database Structure', $this->domain()),
            'tables' => $this->schema->tablesAndViews(),
        ]);
    }

    /**
     * @return ResponseInterface
     * @throws ContainerExceptionInterface
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     * @throws \Qubus\Exception\Exception
     * @throws Exception
     */
    public function vacuum(): ResponseInterface
    {
        if (! $this->policy->canRunWriteSql()) {
            return view('plugin::SqliteAdmin/view/error/forbidden');
        }

        $this->database->pdo()->exec('VACUUM');

        return view('plugin::SqliteAdmin/view/database/action-result', [
            'title' => t__('VACUUM Complete', $this->domain()),
            'message' => t__('Database VACUUM completed.', $this->domain()),
            'tables' => $this->schema->tablesAndViews(),
            'backUrl' => admin_url('plugin/sqlite-admin/'),
            'backLabel' => t__('Back to Dashboard', $this->domain()),
        ]);
    }


    /**
     * @return ResponseInterface
     * @throws ContainerExceptionInterface
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     * @throws \Qubus\Exception\Exception
     * @throws Exception
     */
    public function integrityCheck(): ResponseInterface
    {
        if (! $this->policy->canAccess()) {
            return view('plugin::SqliteAdmin/view/error/forbidden');
        }

        $result = $this->database
            ->pdo()
            ->query('PRAGMA integrity_check')
            ->fetchAll(\PDO::FETCH_ASSOC);

        return view('plugin::SqliteAdmin/view/database/action-result', [
            'title' => t__('Integrity Check', $this->domain()),
            'message' => t__('Integrity check completed.', $this->domain()),
            'result' => $result,
            'tables' => $this->schema->tablesAndViews(),
            'backUrl' => admin_url('plugin/sqlite-admin/'),
            'backLabel' => t__('Back to Dashboard', $this->domain()),
        ]);
    }

    /**
     * @return ResponseInterface
     * @throws ContainerExceptionInterface
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     * @throws \Qubus\Exception\Exception
     * @throws Exception
     */
    public function optimize(): ResponseInterface
    {
        if (! $this->policy->canRunWriteSql()) {
            return view('plugin::SqliteAdmin/view/error/forbidden');
        }

        $this->database->pdo()->exec('PRAGMA optimize');

        return view('plugin::SqliteAdmin/view/database/action-result', [
            'title' => t__('Optimize Complete', $this->domain()),
            'message' => t__('PRAGMA optimize completed.', $this->domain()),
            'tables' => $this->schema->tablesAndViews(),
            'backUrl' => admin_url('plugin/sqlite-admin/'),
            'backLabel' => t__('Back to Dashboard', $this->domain()),
        ]);
    }
}
