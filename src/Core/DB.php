<?php

declare(strict_types=1);

namespace Vihzhuo\Core;

use PDO;
use PDOStatement;
use RuntimeException;

final class DB
{
    protected ?PDO $pdo = null;

    /** @param array<string, mixed> $config */
    public function __construct(array $config)
    {
        $dsn = $config['dsn'] ?? null;
        $username = $config['username'] ?? null;
        $password = $config['password'] ?? null;
        $options = $config['options'] ?? [];
        if (
            !is_string($dsn) || (!is_string($username) && $username !== null)
            || (!is_string($password) && $password !== null) || !is_array($options)
        ) {
            throw new RuntimeException('Invalid database configuration.');
        }
        $this->pdo = new PDO($dsn, $username, $password, $options);
        if (($config['driver'] ?? null) === 'sqlite') {
            $this->pdo->exec('PRAGMA foreign_keys = ON');
        }
    }

    public function lastInsertId(): string
    {
        $id = $this->pdo->lastInsertId();
        return $id === false ? '' : $id;
    }

    /**
     * @param list<string>|string $columns
     * @return list<array<string, mixed>>
     */
    public function all(string $table, array|string $columns = '*'): array
    {
        $selection = '*';
        if (is_array($columns)) {
            $selection = implode(',', array_map($this->identifier(...), $columns));
        }
        return $this->executeSelect("SELECT {$selection} FROM {$this->identifier($table)}");
    }

    /** @return list<array<string, mixed>> */
    public function findWithId(string $table, int|string $id): array
    {
        return $this->executeSelect(
            "SELECT * FROM {$this->identifier($table)} WHERE id = ?",
            [$id]
        );
    }

    /**
     * @param list<scalar|null> $parameters
     * @return list<array<string, mixed>>
     */
    public function select(string $query, array $parameters = []): array
    {
        return $this->executeSelect($query, $parameters);
    }

    /** @param list<scalar|null> $parameters */
    public function query(string $query, array $parameters = []): bool
    {
        return $this->statement($query)->execute($parameters);
    }

    /** @return list<array<string, mixed>> */
    public function rawQuery(string $query): array
    {
        return $this->executeSelect($query);
    }

    /**
     * @param list<scalar|null> $parameters
     * @return list<array<string, mixed>>
     */
    private function executeSelect(string $query, array $parameters = []): array
    {
        $statement = $this->statement($query);
        $statement->execute($parameters);
        $records = $statement->fetchAll(PDO::FETCH_ASSOC);
        $result = [];
        foreach ($records as $record) {
            if (is_array($record)) {
                $result[] = array_filter($record, 'is_string', ARRAY_FILTER_USE_KEY);
            }
        }
        return $result;
    }

    private function statement(string $query): PDOStatement
    {
        $statement = $this->pdo->prepare($query);
        return $statement instanceof PDOStatement
        ? $statement
        : throw new RuntimeException('Unable to prepare database query.');
    }

    private function identifier(string $identifier): string
    {
        $clean = preg_replace('/[^a-zA-Z0-9_]/', '', $identifier);
        if (!is_string($clean) || $clean === '') {
            throw new RuntimeException('Invalid SQL identifier.');
        }
        return $clean;
    }
}
