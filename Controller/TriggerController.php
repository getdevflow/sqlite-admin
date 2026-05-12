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
use Plugin\SqliteAdmin\Security\SqliteTriggerGuard;
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

final readonly class TriggerController
{
    use DomainLocaleAware;

    public function __construct(
        private SqliteSchemaReader $schema,
        private SqliteDatabase $database,
        private SqliteTableScope $scope,
        private DatabaseAccessPolicy $policy,
        private SqliteTriggerGuard $guard,
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
        if (! $this->canManageTriggers($table)) {
            return view('plugin::SqliteAdmin/view/error/forbidden');
        }

        return view('plugin::SqliteAdmin/view/table/triggers', [
            'title' => t__('Triggers', $this->domain()),
            'table' => $table,
            'tables' => $this->schema->tablesAndViews(),
            'columns' => $this->schema->columns($table),
            'triggers' => $this->schema->triggers($table),
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
    public function create(ServerRequestInterface $request, string $table): ResponseInterface
    {
        if (! $this->canManageTriggers($table)) {
            return view('plugin::SqliteAdmin/view/error/forbidden');
        }

        $body = (array) $request->getParsedBody();

        $triggerName = trim((string) ($body['trigger_name'] ?? ''));
        $timing = strtoupper(trim((string) ($body['timing'] ?? 'AFTER')));
        $event = strtoupper(trim((string) ($body['event'] ?? 'INSERT')));
        $forEach = strtoupper(trim((string) ($body['for_each'] ?? 'ROW')));
        $when = trim((string) ($body['when_expression'] ?? ''));
        $statement = trim((string) ($body['statement'] ?? ''));

        if ($triggerName === '') {
            throw new RuntimeException(t__('Trigger name is required.', $this->domain()));
        }

        if (! in_array($timing, ['BEFORE', 'AFTER', 'INSTEAD OF'], true)) {
            throw new RuntimeException(t__('Invalid trigger timing.', $this->domain()));
        }

        if (! in_array($event, ['INSERT', 'UPDATE', 'DELETE'], true)) {
            throw new RuntimeException(t__('Invalid trigger event.', $this->domain()));
        }

        if ($forEach !== 'ROW') {
            throw new RuntimeException(t__('SQLite only supports FOR EACH ROW triggers.', $this->domain()));
        }

        if ($statement === '') {
            $this->guard->assertSafe($statement);
            throw new RuntimeException(t__('Trigger statement is required.', $this->domain()));
        }

        $triggerId = Identifier::from($triggerName);
        $tableId = Identifier::from($table);

        $sql = sprintf(
            'CREATE TRIGGER %s %s %s ON %s FOR EACH ROW %s BEGIN %s; END',
            $triggerId->quoted(),
            $timing,
            $event,
            $tableId->quoted(),
            $when !== '' ? 'WHEN ' . $when : '',
            rtrim($statement, ';')
        );

        $this->database->pdo()->exec($sql);

        Devflow::$PHP->flash->success(Devflow::$PHP->flash->notice(200));

        return redirect(admin_url('sqlite-manager/table/' . rawurlencode($table) . '/triggers'));
    }

    /**
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     * @throws InvalidArgumentException
     * @throws \Qubus\Exception\Exception
     * @throws Throwable
     * @throws ReflectionException
     * @throws TypeException
     */
    public function edit(
        ServerRequestInterface $request,
        string $table,
        string $trigger
    ): ResponseInterface {
        if (! $this->canManageTriggers($table)) {
            return view('plugin::SqliteAdmin/view/error/forbidden');
        }

        $existing = $this->schema->trigger($trigger);

        if ($existing === null) {
            throw new RuntimeException(t__('Trigger not found.', $this->domain()));
        }

        if ($request->getMethod() === 'GET') {
            return view(
                'plugin::SqliteAdmin/view/table/trigger-edit',
                [
                    'table' => $table,
                    'trigger' => $existing,
                    'tables' => $this->schema->tablesAndViews(),
                ]
            );
        }

        $body = (array) $request->getParsedBody();

        $sql = trim((string) ($body['sql'] ?? ''));

        if ($sql === '') {
            throw new RuntimeException(t__('Trigger SQL is required.', $this->domain()));
        }

        $this->guard->assertSafe($sql);

        $triggerId = Identifier::from($trigger);

        $this->database->pdo()->beginTransaction();

        try {
            $this->database->pdo()->exec(
                'DROP TRIGGER ' . $triggerId->quoted()
            );

            $this->database->pdo()->exec($sql);

            $this->database->pdo()->commit();

            Devflow::$PHP->flash->success(Devflow::$PHP->flash->notice(200));
        } catch (\Throwable $e) {
            $this->database->pdo()->rollBack();

            Devflow::$PHP->flash->error(Devflow::$PHP->flash->notice(204));

            throw $e;
        }

        return redirect(
            admin_url(
                'plugin/sqlite-admin/table/'
                . rawurlencode($table)
                . '/triggers'
            )
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
    public function drop(ServerRequestInterface $request, string $table): ResponseInterface
    {
        if (! $this->canManageTriggers($table)) {
            return view('plugin::SqliteAdmin/view/error/forbidden');
        }

        $body = (array) $request->getParsedBody();

        $triggerName = trim((string) ($body['trigger_name'] ?? ''));

        if ($triggerName === '') {
            throw new RuntimeException(t__('Trigger name is required.', $this->domain()));
        }

        $triggerId = Identifier::from($triggerName);

        $this->database->pdo()->exec(
            'DROP TRIGGER ' . $triggerId->quoted()
        );

        Devflow::$PHP->flash->success(Devflow::$PHP->flash->notice(200));

        return redirect(admin_url('sqlite-manager/table/' . rawurlencode($table) . '/triggers'));
    }

    /**
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     * @throws InvalidArgumentException
     * @throws \Qubus\Exception\Exception
     * @throws ReflectionException
     * @throws TypeException
     */
    private function canManageTriggers(string $table): bool
    {
        return $this->policy->canAccess()
                && $this->scope->canSeeTable($table, $this->policy);
    }
}
