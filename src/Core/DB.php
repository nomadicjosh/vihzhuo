<?php

namespace Vihzhuo\Core;

use PDO;

/**
 * Class DB
 *
 * A basic shell around PDO.
 *
 * @package Vihzhuo\Core
 */
class DB
{
    protected ?PDO $pdo = null;

    /**
     * DB constructor.
     *
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->pdo = new PDO(
            $config['dsn'],
            $config['username'],
            $config['password'],
            $config['options']
        );
    }

    /**
     * Return the id of the last inserted record.
     *
     * @return string
     */
    public function lastInsertId(): string
    {
        return $this->pdo->lastInsertId();
    }

    /**
     * Return all records of the given table and return instances of the given class.
     *
     * @param string $table
     * @param array|string $columns
     * @return array
     */
    public function all(string $table, array|string $columns = '*'): array
    {
        if (is_array($columns)) {
            foreach ($columns as &$column) {
                $column = preg_replace('/[^a-zA-Z_]*/', '', $column);
            }
            $columns = implode(',', $columns);
            $stmt = $this->pdo->prepare("SELECT {$columns} FROM {$table}");
        } else {
            $stmt = $this->pdo->prepare("SELECT * FROM {$table}");
        }
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Return the first record of the given table as an instance of the given class.
     *
     * @param string $table
     * @param $id
     * @return array
     */
    public function findWithId(string $table, $id): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM {$table} WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetchAll();
    }

    /**
     * Perform a custom select query with user input data passed as $parameters.
     *
     * @param string $query
     * @param array $parameters
     * @return array
     */
    public function select(string $query, array $parameters): array
    {
        $stmt = $this->pdo->prepare($query);
        $stmt->execute($parameters);
        return $stmt->fetchAll();
    }

    /**
     * Perform a custom query with user input data passed as $parameters.
     *
     * @param string $query
     * @param array $parameters
     * @return bool
     */
    public function query(string $query, array $parameters = []): bool
    {
        $stmt = $this->pdo->prepare($query);
        return $stmt->execute($parameters);
    }

    public function rawQuery(string $query): array
    {
        $stmt = $this->pdo->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
