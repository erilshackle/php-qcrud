<?php

namespace Eril\CRUD;

use Eril\CRUD\Exceptions\CrudException;
use Eril\CRUD\Logging\Logger;
use PDO;
use PDOException;
use RuntimeException;
use InvalidArgumentException;

/**
 * Advanced Query Builder with support for subqueries, joins, and complex conditions.
 * Provides a fluent interface for building SQL queries programmatically.
 * 
 * @method self where(string $field, mixed $evaluator, mixed $value = null) Adds intelligent WHERE condition
 * @method self orWhere(string $field, mixed $evaluator, mixed $value = null) Adds WHERE condition with OR operator
 * @method self whereSubquery(string $column, string $operator, callable|QueryCrud $callback, string|null $table = null) Explicit subquery
 * @method self orWhereSubquery(string $column, string $operator, callable|QueryCrud $callback, string|null $table = null) Explicit subquery with OR operator
 * @method self whereSelect(string $column, string $operator, QueryCrud $subQuery) Pre-built subquery
 * @method self orWhereSelect(string $column, string $operator, QueryCrud $subQuery) Pre-built subquery with OR operator
 * @method self whereExists(callable $callback, bool $not = false) EXISTS/NOT EXISTS condition
 * @method self whereNot(string $field, mixed $evaluator = null, mixed $value = null) WHERE NOT condition
 * @method self orWhereNot(string $field, mixed $evaluator = null, mixed $value = null) OR WHERE NOT condition
 * @method self whereBetween(string $field, mixed $start, mixed $end, bool $not = false) WHERE BETWEEN condition
 * @method self whereNotBetween(string $field, mixed $start, mixed $end) WHERE NOT BETWEEN condition
 * @method self orWhereBetween(string $field, mixed $start, mixed $end, bool $not = false) OR WHERE BETWEEN condition
 * @method self orWhereNotBetween(string $field, mixed $start, mixed $end) OR WHERE NOT BETWEEN condition
 * @method self whereInSelect(string $column, QueryCrud $subQuery) IN with pre-built subquery
 * @method self whereNotInSelect(string $column, QueryCrud $subQuery) NOT IN with pre-built subquery
 * 
 * @author Eril TS Carvalho <erilandocarvalho@gmail.com>
 * @license MIT
 * @package Eril\CRUD
 * @version 1.0.0
 */
class QueryCrud
{
    use Traits\WhereAliases, Traits\ParamBinders;
    use Builders\QueryBuilder;
    use Builders\WhereBuilder;
    use Builders\JoinBuilder;
    use Operations\SelectOperations;
    use Operations\AggregateOperations;
    use Operations\HavingOperations;
    use Operations\WhereOperations;
    use Operations\UtilityOperations;

    /** @var string Database table name */
    protected string $table;

    /** @var string SELECT fields clause */
    protected string $fields;

    /** @var PDO|null PDO connection instance */
    protected ?PDO $pdo;

    /** @var array Query parameters for prepared statements */
    protected array $params = [];

    /** @var array WHERE conditions */
    protected array $wheres = [];

    /** @var array JOIN clauses */
    protected array $joins = [];

    /** @var array OR WHERE conditions */
    protected array $orWheres = [];

    /** @var array HAVING conditions */
    protected array $havings = [];

    /** @var array UNION queries */
    protected array $unions = [];

    /** @var string|null ORDER BY clause */
    protected ?string $order = null;

    /** @var string|null LIMIT clause */
    protected ?string $limit = null;

    /** @var string|null GROUP BY clause */
    protected ?string $group = null;

    /** @var bool Whether this QueryCrud is for a subquery or not */
    protected bool $isSubquery = false;

    /**
     * Query Builder constructor.
     *
     * @param string $table Main table name (can include alias)
     * @param PDO|null $pdo PDO instance (optional for query construction only)
     * @throws InvalidArgumentException If table name is invalid
     */
    public function __construct(string $table, ?PDO $pdo)
    {
        $table = trim($table, '`');
        $pattern = '/^\s*([a-zA-Z_][a-zA-Z0-9_]*(\s+[a-zA-Z_][a-zA-Z0-9_]*)?)\s*$/i';

        if (!preg_match($pattern, $table)) {
            Logger::error("Invalid table name in QueryCrud constructor: {$table}");
            throw new InvalidArgumentException("QueryCrud: Invalid table name in constructor: `$table`");
        }
        $this->table = $table;
        $this->pdo = $pdo;
        $this->fields = '*';
    }

    /**
     * Factory method for subqueries
     */
    public static function subquery(?string $table = null, ?PDO $pdo = null): self
    {
        $instance = new self($table ?? '', $pdo);
        $instance->isSubquery = true;
        return $instance;
    }

    /**
     * Change table for subqueries only
     *
     * @param string $table Table name
     * @return self
     * @throws CrudException If called on non-subquery
     */
    public function from(string $table): self
    {
        if (!$this->isSubquery) {
            throw new CrudException("QueryCrud::from(): cannot be changed outside a subquery");
        }
        $this->table = $table;
        return $this;
    }

    /**
     * Returns query parameters for prepared statements.
     *
     * @return array Query parameters
     * @internal
     */
    public function getParams(): array
    {
        return $this->params;
    }

    /**
     * Reset only parameters (for subquery cloning)
     *
     * @return self
     * @internal
     */
    protected function resetParams(): self
    {
        $this->params = [];
        return $this;
    }

    /**
     * Set parameters directly (for subquery cloning)
     *
     * @param array $params Parameters to set
     * @return self
     * @internal
     */
    protected function setParams(array $params): self
    {
        $this->params = $params;
        return $this;
    }

    /**
     * Executes the query and returns all results
     *
     * @return array Associative array with results
     * @throws RuntimeException If execution fails
     */
    public function get(): array
    {
        if (!$this->pdo) {
            Logger::error("PDO connection not set in QueryCrud::get()");
            throw new RuntimeException("PDO connection not set. Use setConnection() or provide PDO in constructor.");
        }

        $sql = $this->buildQuery();

        try {
            $stmt = $this->pdo->prepare($sql);

            if (!$stmt) {
                $error = "Failed to prepare query: " . implode(', ', $this->pdo->errorInfo());
                Logger::error("QueryCrud prepare error: {$error}");
                throw new PDOException($error);
            }

            // Bind parameters
            $this->bindParamsWithTypes($stmt, $this->params);

            $stmt->execute();

            $errorCode = $stmt->errorCode();
            if ($errorCode !== '00000') {
                $errorInfo = $stmt->errorInfo();
                $error = "Query execution error [{$errorCode}]: " . $errorInfo[2];
                Logger::error("QueryCrud execution error: {$error}");
                throw new PDOException($error);
            }

            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return $result ?: [];
        } catch (PDOException $e) {
            Logger::error("QueryCrud execution failed", $e);
            throw new RuntimeException("Error executing query: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Executes the query and returns only the first result
     *
     * @return array|false Associative array or false if no results
     * @throws RuntimeException If execution fails
     */
    public function first(): array|false
    {
        try {
            $this->limit(1);
            $res = $this->get();
            return $res[0] ?? false;
        } catch (RuntimeException $e) {
            Logger::error("QueryCrud first() failed", $e);
            throw $e;
        }
    }

    /**
     * Checks if at least one record exists
     *
     * @return bool True if exists, false otherwise
     */
    public function exists(): bool
    {
        if (!$this->pdo) {
            Logger::error("PDO connection not set in QueryCrud::exists()");
            return false;
        }

        $sql = "SELECT 1 FROM {$this->table}"
            . $this->buildJoin()
            . $this->buildWhere()
            . " LIMIT 1";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($this->params);
            return (bool) $stmt->fetchColumn();
        } catch (\Exception $e) {
            Logger::error("QueryCrud exists() failed", $e);
            return false;
        }
    }


    /**
     * Resets the query builder to its initial state.
     *
     * @return self
     */
    public function reset(): self
    {
        $this->fields = '*';
        $this->wheres = [];
        $this->params = [];
        $this->joins = [];
        $this->orWheres = [];
        $this->havings = [];
        $this->unions = [];
        $this->order = null;
        $this->limit = null;
        $this->group = null;
        return $this;
    }

    /**
     * Clones the current query builder.
     *
     * @return void
     */
    public function __clone()
    {
        foreach ($this as $key => $value) {
            if (is_object($value)) {
                $this->$key = clone $value;
            } elseif (is_array($value)) {
                $this->$key = $this->deepCloneArray($value);
            }
        }
    }

    /**
     * Deep clone array.
     *
     * @param array $array Array to clone
     * @return array Cloned array
     */
    private function deepCloneArray(array $array): array
    {
        $result = [];
        foreach ($array as $key => $value) {
            if (is_object($value)) {
                $result[$key] = clone $value;
            } elseif (is_array($value)) {
                $result[$key] = $this->deepCloneArray($value);
            } else {
                $result[$key] = $value;
            }
        }
        return $result;
    }
}
