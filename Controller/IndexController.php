<?php

declare(strict_types=1);

namespace Plugin\SqliteAdmin\Controller;

use App\Application\Devflow;
use Exception;
use Plugin\SqliteAdmin\Database\SqliteDatabase;
use Plugin\SqliteAdmin\Database\SqliteSchemaReader;
use Plugin\SqliteAdmin\DomainLocaleAware;
use Plugin\SqliteAdmin\Security\DatabaseAccessPolicy;
use Plugin\SqliteAdmin\Security\Identifier;
use Plugin\SqliteAdmin\Security\SqliteTableScope;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Qubus\Exception\Data\TypeException;
use ReflectionException;
use RuntimeException;

use function App\Shared\Helpers\admin_url;
use function Codefy\Framework\Helpers\view;
use function Qubus\Routing\Helpers\redirect;
use function Qubus\Security\Helpers\t__;
use function rawurlencode;

final readonly class IndexController
{
    use DomainLocaleAware;

    public function __construct(
        private SqliteSchemaReader $schema,
        private SqliteDatabase $database,
        private SqliteTableScope $scope,
        private DatabaseAccessPolicy $policy,
    ) {
    }

    /**
     * @param ServerRequestInterface $request
     * @param string $table
     * @return ResponseInterface
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws InvalidArgumentException
     * @throws TypeException
     * @throws \Qubus\Exception\Exception
     * @throws ReflectionException
     * @throws Exception
     */
    public function index(ServerRequestInterface $request, string $table): ResponseInterface
    {
        if (! $this->canManageIndexes($table)) {
            return view('plugin::SqliteAdmin/view/error/forbidden');
        }

        return view(
            'plugin::SqliteAdmin/view/table/indexes',
            [
                'title' => t__('Indexes', $this->domain()),
                'table' => $table,
                'tables' => $this->schema->tablesAndViews(),
                'columns' => $this->schema->columns($table),
                'indexes' => $this->schema->indexes($table),
            ]
        );
    }

    /**
     * @param ServerRequestInterface $request
     * @param string $table
     * @return ResponseInterface
     * @throws ContainerExceptionInterface
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     * @throws \Qubus\Exception\Exception
     * @throws Exception
     */
    public function create(ServerRequestInterface $request, string $table): ResponseInterface
    {
        if (! $this->canManageIndexes($table)) {
            return view('plugin::SqliteAdmin/view/error/forbidden');
        }

        $body = $request->getParsedBody();

        $indexName = trim((string) ($body['index_name'] ?? ''));
        $columns = $body['columns'] ?? [];
        $unique = isset($body['unique']);

        if ($indexName === '') {
            throw new RuntimeException(t__('Index name is required.', $this->domain()));
        }

        if (! is_array($columns) || $columns === []) {
            throw new RuntimeException(t__('At least one column must be selected.', $this->domain()));
        }

        $tableId = Identifier::from($table);
        $indexId = Identifier::from($indexName);

        $columnSql = implode(', ', array_map(
            static fn (mixed $column): string => Identifier::from((string) $column)->quoted(),
            $columns
        ));

        $sql = sprintf(
            'CREATE %s INDEX %s ON %s (%s)',
            $unique ? 'UNIQUE' : '',
            $indexId->quoted(),
            $tableId->quoted(),
            $columnSql
        );

        $this->database->query($sql);

        Devflow::$PHP->flash->success(Devflow::$PHP->flash->notice(200));

        return redirect(admin_url('plugin/sqlite-admin/table/' . rawurlencode($table) . '/indexes'));
    }

    /**
     * @param ServerRequestInterface $request
     * @param string $table
     * @return ResponseInterface
     * @throws ContainerExceptionInterface
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     * @throws \Qubus\Exception\Exception
     * @throws Exception
     */
    public function drop(ServerRequestInterface $request, string $table): ResponseInterface
    {
        if (! $this->canManageIndexes($table)) {
            return view('plugin::SqliteAdmin/view/error/forbidden');
        }

        $body = $request->getParsedBody();

        $indexName = trim((string) ($body['index_name'] ?? ''));

        if ($indexName === '') {
            throw new RuntimeException(t__('Index name is required.', $this->domain()));
        }

        $indexId = Identifier::from($indexName);

        $this->database->query('DROP INDEX ' . $indexId->quoted());

        Devflow::$PHP->flash->success(Devflow::$PHP->flash->notice(200));

        return redirect(admin_url('plugin/sqlite-admin/table/' . rawurlencode($table) . '/indexes'));
    }

    /**
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     * @throws InvalidArgumentException
     * @throws \Qubus\Exception\Exception
     * @throws ReflectionException
     * @throws TypeException
     */
    private function canManageIndexes(string $table): bool
    {
        return $this->policy->canAccess()
            && $this->scope->canSeeTable($table, $this->policy);
    }
}
