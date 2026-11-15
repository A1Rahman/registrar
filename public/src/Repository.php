<?php
declare(strict_types=1);

/**
 * Base class for the four table classes. It holds the PDO connection and a few
 * helpers. All SQL uses prepared statements with ? placeholders and standard SQL.
 * Table names that appear in SQL strings are constants written by us, never user input.
 */
abstract class Repository
{
    public function __construct(protected PDO $pdo)
    {
    }

    /** All rows, with readable names for related records. */
    abstract public function all(): array;

    /** One row or null. */
    abstract protected function row(int $id): ?array;

    /** Validate, insert and return the new row. */
    abstract public function create(array $data): array;

    /** Validate, update and return the row. */
    abstract public function update(int $id, array $data): array;

    /** Apply the delete rules, then delete. */
    abstract public function delete(int $id): void;

    /** The row, or a 404 if it does not exist. */
    public function find(int $id): array
    {
        return $this->row($id) ?? throw new ApiException('Record not found.', 404);
    }

    /** Run a query and return the first column of the first row. */
    protected function scalar(string $sql, array $params = []): mixed
    {
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        return $st->fetchColumn();
    }

    /** Run a query and return all rows. */
    protected function rows(string $sql, array $params = []): array
    {
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    }

    /** Run an INSERT, UPDATE or DELETE. */
    protected function run(string $sql, array $params): void
    {
        $this->pdo->prepare($sql)->execute($params);
    }

    /** A referenced record (e.g. the course of an enrollment) must exist, otherwise 404. */
    protected function requireExists(string $table, int $id, string $label): void
    {
        if ((int)$this->scalar("SELECT COUNT(*) FROM $table WHERE id = ?", [$id]) === 0) {
            throw new ApiException("The selected $label does not exist.", 404);
        }
    }
}
