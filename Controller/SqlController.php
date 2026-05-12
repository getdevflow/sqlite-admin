<?php

declare(strict_types=1);

namespace Plugin\SqliteAdmin\Controller;

use Exception;
use PDO;
use PDOStatement;
use Plugin\SqliteAdmin\DomainLocaleAware;
use Plugin\SqliteAdmin\Security\SqliteTableScope;
use Plugin\SqliteAdmin\Database\SqliteDatabase;
use Plugin\SqliteAdmin\Database\SqliteSchemaReader;
use Plugin\SqliteAdmin\Database\SqliteStatementSplitter;
use Plugin\SqliteAdmin\Security\DatabaseAccessPolicy;
use Plugin\SqliteAdmin\Security\SqliteQueryGuard;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Qubus\Exception\Data\TypeException;
use ReflectionException;

use function Codefy\Framework\Helpers\view;
use function Qubus\Security\Helpers\t__;

final readonly class SqlController
{
    use DomainLocaleAware;

    public function __construct(
        private DatabaseAccessPolicy $policy,
        private SqliteDatabase $database,
        private SqliteSchemaReader $schema,
        private SqliteQueryGuard $guard,
        private SqliteStatementSplitter $splitter,
        private SqliteTableScope $scope,
    ) {
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
    public function editor(): ResponseInterface
    {
        if (! $this->policy->canAccess()) {
            return view('plugin::SqliteAdmin/view/error/forbidden');
        }

        return view(
            'plugin::SqliteAdmin/view/database/sql',
            [
                'title' => t__('SQL Editor', $this->domain()),
                'tables' => $this->schema->tablesAndViews(),
                'sql' => '',
                'results' => [],
                'table' => null,
            ]
        );
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
    public function tableEditor(string $table): ResponseInterface
    {
        if (! $this->policy->canAccess()
                || ! $this->scope->canSeeTable($table, $this->policy)) {
            return view('plugin::SqliteAdmin/view/error/forbidden');
        }

        return view('plugin::SqliteAdmin/view/database/sql', [
            'title' => t__('SQL Editor', $this->domain()),
            'tables' => $this->schema->tablesAndViews(),
            'sql' => 'SELECT * FROM "' . str_replace('"', '""', $table) . '" LIMIT 50;',
            'results' => [],
            'table' => $table,
        ]);
    }

    /**
     * @param ServerRequestInterface $request
     * @param string|null $table
     * @return ResponseInterface
     * @throws ContainerExceptionInterface
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     * @throws \Qubus\Exception\Exception
     * @throws Exception
     */
    public function execute(ServerRequestInterface $request, ?string $table = null): ResponseInterface
    {
        if (! $this->policy->canAccess()) {
            return view('plugin::SqliteAdmin/view/error/forbidden');
        }

        if ($table !== null && ! $this->scope->canSeeTable($table, $this->policy)) {
            return view('plugin::SqliteAdmin/view/error/forbidden');
        }

        $body = (array) $request->getParsedBody();
        $sql = trim((string) ($body['sql'] ?? ''));

        $this->guard->assertAllowed($sql);

        $results = [];

        foreach ($this->splitter->split($sql) as $statement) {
            $start = microtime(true);
            $stmt = $this->database->query($statement);

            $rows = [];
            $rowCount = 0;

            if ($stmt instanceof PDOStatement) {
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $rowCount = $rows !== []
                    ? count($rows)
                    : $stmt->rowCount();
            }

            $results[] = [
                'sql' => $statement,
                'rows' => $rows,
                'rowCount' => $rowCount,
                'time' => microtime(true) - $start,
            ];
        }

        return view('plugin::SqliteAdmin/view/database/sql', [
            'title' => t__('SQL Editor', $this->domain()),
            'tables' => $this->schema->tablesAndViews(),
            'sql' => $sql,
            'results' => $results,
            'table' => $table,
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
    public function executeForTable(ServerRequestInterface $request, string $table): ResponseInterface
    {
        if (! $this->policy->canAccess()
                || ! $this->scope->canSeeTable($table, $this->policy)) {
            return view('plugin::SqliteAdmin/view/error/forbidden');
        }

        return $this->execute($request, $table);
    }
}
