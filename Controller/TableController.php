<?php

declare(strict_types=1);

namespace Plugin\SqliteAdmin\Controller;

use App\Application\Devflow;
use Exception;
use JsonException;
use Plugin\SqliteAdmin\Database\SqliteDatabase;
use Plugin\SqliteAdmin\Database\SqliteRowRepository;
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
use Throwable;

use function App\Shared\Helpers\admin_url;
use function Codefy\Framework\Helpers\view;
use function Qubus\Routing\Helpers\redirect;
use function Qubus\Security\Helpers\t__;
use function rawurlencode;

final readonly class TableController
{
    use DomainLocaleAware;

    public function __construct(
        private DatabaseAccessPolicy $policy,
        private SqliteTableScope $scope,
        private SqliteSchemaReader $schema,
        private SqliteRowRepository $rows,
        private SqliteDatabase $database,
    ) {
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
    public function browse(ServerRequestInterface $request, string $table): ResponseInterface
    {
        if (! $this->allowed($table)) {
            return view('plugin::SqliteAdmin/view/error/forbidden');
        }

        $query = $request->getQueryParams();
        $page = max(1, (int) ($query['page'] ?? 1));
        $perPage = min(200, max(10, (int) ($query['perPage'] ?? 50)));
        $sort = isset($query['sort']) ? (string) $query['sort'] : null;
        $direction = isset($query['direction']) ? (string) $query['direction'] : 'ASC';
        $result = $this->rows->browse($table, $page, $perPage, $sort, $direction);

        return view('plugin::SqliteAdmin/view/table/browse', [
            'title' => t__('Browse ', $this->domain()) . $table,
            'tables' => $this->schema->tablesAndViews(),
            'table' => $table,
            'columns' => $this->schema->columns($table),
            'rows' => $result['rows'],
            'total' => $result['total'],
            'page' => $page,
            'perPage' => $perPage,
        ]);
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
    public function structure(ServerRequestInterface $request, string $table): ResponseInterface
    {
        if (! $this->allowed($table)) {
            return view('plugin::SqliteAdmin/view/error/forbidden');
        }

        return view('plugin::SqliteAdmin/view/table/structure', [
            'title' => t__('Structure ', $this->domain()) . $table,
            'tables' => $this->schema->tablesAndViews(),
            'table' => $table,
            'columns' => $this->schema->columns($table),
            'indexes' => $this->schema->indexes($table),
            'triggers' => $this->schema->triggers($table),
            'createSql' => $this->schema->createSql($table),
            'policy' => $this->policy,
        ]);
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
    public function searchForm(ServerRequestInterface $request, string $table): ResponseInterface
    {
        if (! $this->allowed($table)) {
            return view('plugin::SqliteAdmin/view/error/forbidden');
        }

        return view('plugin::SqliteAdmin/view/table/search', [
            'title' => t__('Search ', $this->domain()) . $table,
            'tables' => $this->schema->tablesAndViews(),
            'table' => $table,
            'columns' => $this->schema->columns($table),
            'rows' => [],
            'total' => 0,
        ]);
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
    public function search(ServerRequestInterface $request, string $table): ResponseInterface
    {
        if (! $this->allowed($table)) {
            return view('plugin::SqliteAdmin/view/error/forbidden');
        }

        $body = (array) $request->getParsedBody();

        $filters = isset($body['filters']) && is_array($body['filters'])
                ? $body['filters']
                : [];

        $result = $this->rows->search($table, $filters);

        return view('plugin::SqliteAdmin/view/table/search', [
            'title' => t__('Search ', $this->domain()) . $table,
            'tables' => $this->schema->tablesAndViews(),
            'table' => $table,
            'columns' => $this->schema->columns($table),
            'rows' => $result['rows'],
            'total' => $result['total'],
            'filters' => $filters,
            'showActions' => true,
        ]);
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
    public function insertForm(ServerRequestInterface $request, string $table): ResponseInterface
    {
        if (! $this->allowed($table)) {
            return view('plugin::SqliteAdmin/view/error/forbidden');
        }

        return view('plugin::SqliteAdmin/view/table/insert', [
            'title' => t__('Insert Row', $this->domain()),
            'tables' => $this->schema->tablesAndViews(),
            'table' => $table,
            'columns' => $this->schema->columns($table),
        ]);
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
    public function editForm(ServerRequestInterface $request, string $table): ResponseInterface
    {
        if (! $this->allowed($table)) {
            return view('plugin::SqliteAdmin/view/error/forbidden');
        }

        $query = $request->getQueryParams();
        $rowKey = (string) ($query['row'] ?? '');

        $where = $this->rows->decodeRowKey($rowKey);
        $row = $this->rows->findOne($table, $where);

        if ($row === null) {
            return view('plugin::SqliteAdmin/view/action-result', [
                'title' => t__('Row Not Found', $this->domain()),
                'message' => t__('The selected row could not be found.', $this->domain()),
                'backUrl' => admin_url('plugin/sqlite-admin/table/' . rawurlencode($table)),
                'backLabel' => t__('Back to Table', $this->domain()),
            ]);
        }

        return view('plugin::SqliteAdmin/view/table/edit', [
            'title' => t__('Edit Row', $this->domain()),
            'table' => $table,
            'tables' => $this->schema->tablesAndViews(),
            'columns' => $this->schema->columns($table),
            'row' => $row,
            'where' => $where,
        ]);
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
    public function insert(ServerRequestInterface $request, string $table): ResponseInterface
    {
        if (! $this->allowed($table)) {
            return view('plugin::SqliteAdmin/view/error/forbidden');
        }

        $body = (array) $request->getParsedBody();

        $data = isset($body['data']) && is_array($body['data'])
                ? $body['data']
                : [];

        $nulls = isset($body['nulls']) && is_array($body['nulls'])
                ? array_map('strval', $body['nulls'])
                : [];

        $data = $this->rows->normalizeFormData($data, $nulls);

        $this->rows->insert($table, $data);

        Devflow::$PHP->flash->success(Devflow::$PHP->flash->notice(200));

        return redirect(admin_url('plugin/sqlite-admin/table/' . rawurlencode($table)));
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
    public function update(ServerRequestInterface $request, string $table): ResponseInterface
    {
        if (! $this->allowed($table)) {
            return view('plugin::SqliteAdmin/view/error/forbidden');
        }

        $body = (array) $request->getParsedBody();

        $data = isset($body['data']) && is_array($body['data'])
                ? $body['data']
                : [];

        $where = isset($body['where']) && is_array($body['where'])
                ? $body['where']
                : [];

        $nulls = isset($body['nulls']) && is_array($body['nulls'])
                ? array_map('strval', $body['nulls'])
                : [];

        $data = $this->rows->normalizeFormData($data, $nulls);

        $this->rows->update($table, $data, $where);

        Devflow::$PHP->flash->success(Devflow::$PHP->flash->notice(200));

        return redirect(admin_url('plugin/sqlite-admin/table/' . rawurlencode($table)));
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
     * @throws JsonException
     * @throws Exception
     */
    public function deleteRow(ServerRequestInterface $request, string $table): ResponseInterface
    {
        if (! $this->allowed($table)) {
            return view('plugin::SqliteAdmin/view/error/forbidden');
        }

        $body = (array) $request->getParsedBody();
        $rowKey = (string) ($body['row'] ?? '');

        $where = $this->rows->decodeRowKey($rowKey);

        $this->rows->delete($table, $where);

        Devflow::$PHP->flash->success(Devflow::$PHP->flash->notice(200));

        return redirect(admin_url('plugin/sqlite-admin/table/' . rawurlencode($table)));
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
     * @throws Throwable
     */
    public function empty(ServerRequestInterface $request, string $table): ResponseInterface
    {
        if (! $this->policy->canDropTable() || ! $this->allowed($table)) {
            return view('plugin::SqliteAdmin/view/error/forbidden');
        }

        $this->assertTableExists($table);

        $pdo = $this->database->pdo();

        $pdo->beginTransaction();

        try {
            $pdo->exec('DELETE FROM ' . Identifier::from($table)->quoted());
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }

        Devflow::$PHP->flash->success(Devflow::$PHP->flash->notice(200));

        return redirect(admin_url('plugin/sqlite-admin/table/' . rawurlencode($table)));
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
        if (! $this->policy->canDropTable() || ! $this->allowed($table)) {
            return view('plugin::SqliteAdmin/view/error/forbidden');
        }

        $this->assertTableExists($table);

        $this->database->pdo()->exec(
            'DROP TABLE ' . Identifier::from($table)->quoted()
        );

        Devflow::$PHP->flash->success(Devflow::$PHP->flash->notice(200));

        return redirect(admin_url('plugin/sqlite-admin/structure'));
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
    public function rename(ServerRequestInterface $request, string $table): ResponseInterface
    {
        if (! $this->policy->canDropTable() || ! $this->allowed($table)) {
            return view('plugin::SqliteAdmin/view/error/forbidden');
        }

        $this->assertTableExists($table);

        $body = (array) $request->getParsedBody();
        $newName = trim((string) ($body['new_name'] ?? ''));

        if ($newName === '') {
            throw new RuntimeException(t__('The new table name cannot be empty.', $this->domain()));
        }

        Identifier::from($newName);

        if ($this->tableExists($newName)) {
            throw new RuntimeException(
                sprintf(t__('Table "%s" already exists.', $this->domain()), $newName)
            );
        }

        $this->database->pdo()->exec(
            'ALTER TABLE '
            . Identifier::from($table)->quoted()
            . ' RENAME TO '
            . Identifier::from($newName)->quoted()
        );

        Devflow::$PHP->flash->success(Devflow::$PHP->flash->notice(200));

        return redirect(admin_url('plugin/sqlite-admin/table/' . rawurlencode($newName)));
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws InvalidArgumentException
     * @throws \Qubus\Exception\Exception
     * @throws ReflectionException
     * @throws TypeException
     * @throws Exception
     */
    public function addColumn(ServerRequestInterface $request, string $table): ResponseInterface
    {
        if (! $this->policy->canDropTable() || ! $this->allowed($table)) {
            return view('plugin::SqliteAdmin/view/error/forbidden');
        }

        $body = (array) $request->getParsedBody();

        $name = trim((string) ($body['column_name'] ?? ''));
        $type = strtoupper(trim((string) ($body['column_type'] ?? 'TEXT')));
        $nullable = isset($body['nullable']);
        $default = trim((string) ($body['default_value'] ?? ''));

        if ($name === '') {
            throw new RuntimeException(t__('Column name is required.', $this->domain()));
        }

        Identifier::from($name);

        $allowedTypes = [
            'TEXT',
            'INTEGER',
            'REAL',
            'NUMERIC',
            'BLOB',
            'VARCHAR(191)',
            'VARCHAR(255)',
            'LONGTEXT',
            'DATETIME',
            'DATE',
            'BOOLEAN',
        ];

        if (! in_array($type, $allowedTypes, true)) {
            throw new RuntimeException(t__('Invalid column type.', $this->domain()));
        }

        $sql = 'ALTER TABLE '
            . Identifier::from($table)->quoted()
            . ' ADD COLUMN '
            . Identifier::from($name)->quoted()
            . ' '
            . $type;

        if (! $nullable) {
            $sql .= ' NOT NULL';
        }

        if ($default !== '') {
            $sql .= ' DEFAULT ' . $this->database->pdo()->quote($default);
        }

        $this->database->pdo()->exec($sql);

        Devflow::$PHP->flash->success(Devflow::$PHP->flash->notice(200));

        return redirect(admin_url('plugin/sqlite-admin/table/' . rawurlencode($table) . '/structure'));
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws InvalidArgumentException
     * @throws \Qubus\Exception\Exception
     * @throws TypeException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws Exception
     */
    public function renameColumn(ServerRequestInterface $request, string $table): ResponseInterface
    {
        if (! $this->policy->canDropTable() || ! $this->allowed($table)) {
            return view('plugin::SqliteAdmin/view/error/forbidden');
        }

        $body = (array) $request->getParsedBody();

        $oldName = trim((string) ($body['old_column_name'] ?? ''));
        $newName = trim((string) ($body['new_column_name'] ?? ''));

        if ($oldName === '' || $newName === '') {
            throw new RuntimeException(t__('Old and new column names are required.', $this->domain()));
        }

        Identifier::from($oldName);
        Identifier::from($newName);

        $this->assertColumnExists($table, $oldName);

        if ($this->columnExists($table, $newName)) {
            throw new RuntimeException(sprintf(t__('Column "%s" already exists.', $this->domain()), $newName));
        }

        $this->database->pdo()->exec(
            'ALTER TABLE '
            . Identifier::from($table)->quoted()
            . ' RENAME COLUMN '
            . Identifier::from($oldName)->quoted()
            . ' TO '
            . Identifier::from($newName)->quoted()
        );

        Devflow::$PHP->flash->success(Devflow::$PHP->flash->notice(200));

        return redirect(admin_url('plugin/sqlite-admin/table/' . rawurlencode($table) . '/structure'));
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws InvalidArgumentException
     * @throws \Qubus\Exception\Exception
     * @throws ReflectionException
     * @throws TypeException
     * @throws Exception
     */
    public function dropColumn(ServerRequestInterface $request, string $table): ResponseInterface
    {
        if (! $this->policy->canDropTable() || ! $this->allowed($table)) {
            return view('plugin::SqliteAdmin/view/error/forbidden');
        }

        if (! $this->sqliteSupportsDropColumn()) {
            throw new RuntimeException(t__('This SQLite version does not support DROP COLUMN.', $this->domain()));
        }

        $body = (array) $request->getParsedBody();

        $column = trim((string) ($body['column_name'] ?? ''));

        if ($column === '') {
            throw new RuntimeException(t__('Column name is required.', $this->domain()));
        }

        Identifier::from($column);

        $this->assertColumnExists($table, $column);

        $this->database->pdo()->exec(
            'ALTER TABLE '
            . Identifier::from($table)->quoted()
            . ' DROP COLUMN '
            . Identifier::from($column)->quoted()
        );

        Devflow::$PHP->flash->success(Devflow::$PHP->flash->notice(200));

        return redirect(admin_url('plugin/sqlite-admin/table/' . rawurlencode($table) . '/structure'));
    }

    /**
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     * @throws InvalidArgumentException
     * @throws \Qubus\Exception\Exception
     * @throws ReflectionException
     * @throws TypeException
     */
    private function allowed(string $table): bool
    {
        return $this->policy->canAccess() && $this->scope->canSeeTable($table, $this->policy);
    }

    /**
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws ContainerExceptionInterface
     * @throws TypeException
     * @throws InvalidArgumentException
     * @throws \Qubus\Exception\Exception
     */
    private function tableExists(string $table): bool
    {
        return array_any($this->schema->tablesAndViews(), fn($item) => ($item['name'] ?? null)===$table);
    }

    /**
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws ContainerExceptionInterface
     * @throws TypeException
     * @throws InvalidArgumentException
     * @throws \Qubus\Exception\Exception
     */
    private function assertTableExists(string $table): void
    {
        if ($this->tableExists($table)) {
            return;
        }

        throw new RuntimeException(
            sprintf(t__('Table "%s" does not exist.', $this->domain()), $table)
        );
    }

    private function columnExists(string $table, string $column): bool
    {
        return array_any($this->schema->columns($table), fn($item) => ($item['name'] ?? null)===$column);
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws ReflectionException
     * @throws NotFoundExceptionInterface
     */
    private function assertColumnExists(string $table, string $column): void
    {
        if (! $this->columnExists($table, $column)) {
            throw new RuntimeException(sprintf(t__('Column "%s" does not exist.', $this->domain()), $column));
        }
    }

    private function sqliteSupportsDropColumn(): bool
    {
        $version = (string) $this->database
            ->pdo()
            ->query('SELECT sqlite_version()')
            ->fetchColumn();

        return version_compare($version, '3.35.0', '>=');
    }
}
