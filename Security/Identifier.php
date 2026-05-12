<?php

declare(strict_types=1);

namespace Plugin\SqliteAdmin\Security;

use InvalidArgumentException;
use Plugin\SqliteAdmin\DomainLocaleAware;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use ReflectionException;

use function Qubus\Security\Helpers\t__;

final readonly class Identifier
{
    use DomainLocaleAware;

    /**
     * @throws ReflectionException
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function __construct(
        private string $value,
    ) {
        if ($value === '' || ! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value)) {
            throw new InvalidArgumentException(t__('Invalid SQLite identifier.', $this->domain()));
        }
    }

    /**
     * @throws ReflectionException
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public static function from(string $value): self
    {
        return new self($value);
    }

    public function raw(): string
    {
        return $this->value;
    }

    public function quoted(): string
    {
        return '"' . str_replace('"', '""', $this->value) . '"';
    }
}
