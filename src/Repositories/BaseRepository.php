<?php

declare(strict_types=1);

namespace Vihzhuo\Repositories;

use LogicException;
use ReflectionClass;
use ReflectionException;
use Vihzhuo\Contracts\PageContract;
use Vihzhuo\Contracts\DataRecordContract;
use Vihzhuo\Core\DB;

/** @template T of object */
abstract class BaseRepository
{
    protected DB $db;

    protected string $table = '';

    /** @var class-string<T> */
    protected string $class;

    public function __construct()
    {
        global $phpb_db;
        if (!$phpb_db instanceof DB) {
            throw new LogicException('Database connection has not been configured.');
        }
        $this->db = $phpb_db;
        $prefix = phpb_config('storage.database.prefix');
        $this->table = (is_string($prefix) ? $prefix : '') . $this->identifier($this->table);
    }

    /**
     * @param array<string, scalar|null> $data
     * @throws ReflectionException
     */
    protected function createRecord(array $data): ?object
    {
        $columns = array_map($this->identifier(...), array_keys($data));
        $questionMarks = implode(', ', array_fill(0, count($data), '?'));
        $this->db->query(
            "INSERT INTO {$this->table} (" . implode(', ', $columns) . ") VALUES ({$questionMarks})",
            array_values($data)
        );
        $id = $this->db->lastInsertId();
        return $id !== '' ? $this->findWithId($id) : null;
    }

    /** @param array<string, scalar|null> $data */
    protected function updateRecord(object $instance, array $data): bool
    {
        $set = implode(', ', array_map(fn (string $column): string => $this->identifier($column) . '=?', array_keys($data)));
        $values = array_values($data);
        $values[] = $this->recordId($instance);
        return $this->db->query("UPDATE {$this->table} SET {$set} WHERE id=?", $values);
    }

    public function destroy(int|string $id): bool
    {
        return $this->db->query("DELETE FROM {$this->table} WHERE id=?", [$id]);
    }

    public function destroyWhere(string $column, bool|float|int|string|null $value): bool
    {
        return $this->db->query("DELETE FROM {$this->table} WHERE {$this->identifier($column)}=?", [$value]);
    }

    public function destroyAll(): bool
    {
        return $this->db->query("DELETE FROM {$this->table}");
    }

    /**
     * @param list<string>|string $columns
     * @return list<T>
     * @throws ReflectionException
     */
    public function getAll(array|string $columns = '*'): array
    {
        return $this->hydrateAll($this->db->all($this->table, $columns));
    }

    /**
     * @return T|null
     * @throws ReflectionException
     */
    public function findWithId(int|string $id): ?object
    {
        return $this->hydrateOne($this->db->findWithId($this->table, $id));
    }

    /**
     * @return list<T>
     * @throws ReflectionException
     */
    public function findWhere(string $column, bool|float|int|string|null $value): array
    {
        return $this->hydrateAll($this->db->select(
            "SELECT * FROM {$this->table} WHERE {$this->identifier($column)} = ?",
            [$value]
        ));
    }

    private function identifier(string $identifier): string
    {
        $clean = preg_replace('/\W/', '', $identifier);
        return is_string($clean) ? $clean : '';
    }

    /**
     * @param list<array<string, mixed>> $records
     * @return T|null
     * @throws ReflectionException
     */
    private function hydrateOne(array $records): ?object
    {
        $instances = $this->hydrateAll($records);
        return $instances[0] ?? null;
    }

    /**
     * @param list<array<string, mixed>> $records
     * @return list<T>
     * @throws ReflectionException
     */
    private function hydrateAll(array $records): array
    {
        $result = [];
        foreach ($records as $record) {
            $reflection = new ReflectionClass($this->class);
            $instance = $reflection->newInstanceWithoutConstructor();
            if ($instance instanceof PageContract || $instance instanceof DataRecordContract) {
                $instance->setData($record);
            } else {
                throw new LogicException($this->class . ' must implement PageContract or DataRecordContract.');
            }
            $result[] = $instance;
        }
        return $result;
    }

    private function recordId(object $instance): int|string
    {
        if ($instance instanceof PageContract) {
            return $instance->getId();
        }
        if ($instance instanceof DataRecordContract) {
            return $instance->getId();
        }
        throw new LogicException('Record has no identifier.');
    }
}
