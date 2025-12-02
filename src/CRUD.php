<?php

namespace Eril\CRUD;

use PDO;
use PDOException;
use Exception;
use InvalidArgumentException;
use RuntimeException;

/**
 * CRUD Class
 * Provides simplified CRUD operations (Create, Read, Update, Delete, Select) using PDO.
 * Also provides a static Query method to help building complex queries with a fluent interface.
 * 
 * @author erilshk <erilandocarvalho@gmail.com>
 * @license MIT
 * @package Eril\CRUD
 * @version 1.0.0
 */
class CRUD
{
    use Traits\ParamBinders;

    /** @var string Database table name */
    protected string $table;

    /** @var string|null Primary key column name */
    protected ?string $pk = null;

    /** @var PDO|null Shared PDO connection instance */
    private static ?PDO $pdo = null;

    /** @var array|null Callback to create PDO connection */
    private static ?array $register = null;

    /** @var array Callbacks to execute during transaction */
    private static array $transactionsCallbacks = [];

    /** @var bool Whether currently in a transaction */
    private static bool $onTransaction = false;

    /**
     * Registers how the PDO connection should be created or used by the class.
     *
     * This method supports two usage modes:
     *
     * 1. **Direct PDO instance**: Pass an already initialized `PDO` object.
     *    Example: `CRUD::registerConnection(new PDO($dsn, $user, $pass));`
     *
     * 2. **Callback array**: Register a factory/callback that will be invoked
     *    later to create the PDO connection when needed.
     *    Example: `CRUD::registerConnection([DbFactory::class, 'createConnection']);`
     *
     * It is recommended to call this method during application bootstrap
     * to ensure consistent database connection across operations.
     *
     * @param PDO|array $connection Either a PDO instance or callback array
     * @return void
     * @throws Exception If connection cannot be established
     */
    public static function registerConnection(PDO|array $connection): void
    {
        if ($connection instanceof PDO) {
            self::useConnection($connection);
        } else {
            self::$register = $connection;
        }
    }

    /**
     * Sets or creates the PDO connection instance.
     *
     * @param PDO|null $pdo Optional PDO instance to use directly
     * @return void
     * @throws Exception If connection cannot be established
     * @internal Used internally to manage connection lifecycle
     */
    protected static function useConnection(?PDO $pdo = null): void
    {
        try {
            if ($pdo !== null) {
                self::$pdo = $pdo;
            } elseif (self::$register !== null) {
                self::$pdo = call_user_func(self::$register);
                self::$register = null;
            }
        } catch (Exception $e) {
            error_log($e->getMessage());
            throw new Exception("Unknown connection error: " . $e->getMessage());
        }
    }

    /**
     * Constructor for CRUD operations on a specific table.
     *
     * @param string $table Database table name
     * @param string $primaryKey Primary key column name (default: 'id')
     * @param PDO|null $pdo Optional PDO instance (uses registered connection if null)
     * @throws Exception If no database connection is available
     */
    public function __construct(string $table, string $primaryKey = 'id', ?PDO $pdo = null)
    {
        try {
            $this->table = $table;
            $this->pk = $primaryKey;
            self::useConnection($pdo);

            if (self::$pdo === null) {
                throw new Exception("CRUD: No database connection available");
            }
        } catch (PDOException $exception) {
            error_log($exception->getMessage());
            throw $exception;
        }
    }

    /**
     * Returns the PDO connection instance.
     *
     * @return PDO|null The PDO connection or null if not established
     */
    protected function pdo(): ?PDO
    {
        return self::$pdo;
    }

    /**
     * Factory method for quick instantiation of CRUD with fluent interface.
     *
     * @param string $table Database table name
     * @param string $primaryKey Primary key column (default: 'id')
     * @return self New CRUD instance for the specified table
     * 
     * @example
     * CRUD::table('users')->create(['name' => 'John']);
     * CRUD::table('products', 'product_id')->read(123);
     */
    public static function table(string $table, string $primaryKey = "id"): self
    {
        return new self($table, $primaryKey, null);
    }

    

    /**
     * Creates a new record in the database.
     *
     * @param array $data Associative array of column => value pairs
     * @return int|bool The ID of the created record, or false on failure
     * 
     * @example
     * $id = CRUD::table('users')->create([
     *     'name' => 'John Doe',
     *     'email' => 'john@example.com'
     * ]);
     */
    public function create(array $data): int|bool
    {
        try {
            $columns = implode(',', array_keys($data));
            $placeholders = implode(',', array_fill(0, count($data), '?'));
            $sql = "INSERT INTO {$this->table} ($columns) VALUES ($placeholders)";
            $result = $this->executeSQL($sql, array_values($data));
            return $result > 0 ? (int) self::$pdo->lastInsertId() : false;
        } catch (Exception $e) {
            error_log($e->getMessage());
            return false;
        }
    }

    /**
     * Reads one or multiple records from the database.
     *
     * @param string|int|array|null $id Primary key value(s) or null for all records
     * @param string|null $select Comma-separated list of columns to select
     * @return array The record(s) as associative array(s)
     * 
     * @example
     * // Read single record
     * $user = CRUD::table('users')->read(123);
     * 
     * // Read all records with specific columns
     * $users = CRUD::table('users')->read(null, 'id, name, email');
     */
    public function read(string|int|array|null $id = null, ?string $select = null): array
    {
        $cols = $select ? array_map('trim', explode(',', $select)) : ['*'];
        $valid = array_filter($cols, fn($c) => $c === '*' || preg_match('/^[\w]+(\s+as\s+[\w]+|\s+[\w]+)?$/i', $c));
        $select = $valid ? implode(', ', $valid) : '*';

        try {
            if ($id === null) {
                return $this->querySQL("SELECT $select FROM {$this->table}");
            }
            $id = is_array($id) ? $id[$this->pk] ?? 0 : $id;
            return $this->querySQL("SELECT $select FROM {$this->table} WHERE {$this->pk}=? LIMIT 1", [$id]) ?: [];
        } catch (Exception $e) {
            error_log($e->getMessage());
            return [];
        }
    }

    /**
     * Updates an existing record in the database.
     *
     * @param string|int|array $id Primary key value or array containing primary key
     * @param array $data Associative array of column => value pairs to update
     * @return int|bool Number of affected rows, or false on failure
     * 
     * @example
     * CRUD::table('users')->update(123, ['name' => 'Jane Doe']);
     */
    public function update(string|int|array $id, array $data): int|bool
    {
        try {
            $set = [];
            $params = [];
            foreach ($data as $column => $value) {
                $set[] = "$column = ?";
                $params[] = $value;
            }
            $setClause = implode(', ', $set);
            $id = is_array($id) ? $id[$this->pk] ?? 0 : $id;
            $sql = "UPDATE {$this->table} SET $setClause WHERE {$this->pk} = ?";
            $params[] = $id;
            return $this->executeSQL($sql, $params);
        } catch (Exception $e) {
            error_log($e->getMessage());
            return false;
        }
    }

    /**
     * Deletes a record from the database.
     *
     * @param string|int|array $id Primary key value or array containing primary key
     * @return int|bool Number of affected rows, or false on failure
     * 
     * @example
     * CRUD::table('users')->delete(123);
     */
    public function delete(string|int|array $id): int|false
    {
        try {
            $id = is_array($id) ? ($id[$this->pk] ?? 0) : $id;
            $sql = "DELETE FROM {$this->table} WHERE {$this->pk} = ?";
            return $this->executeSQL($sql, [$id]);
        } catch (Exception $e) {
            error_log($e->getMessage());
            return false;
        }
    }

    /**
     * Selects records with custom WHERE conditions.
     *
     * @param string|null $where WHERE clause (without the 'WHERE' keyword)
     * @param array $params Parameters for prepared statement
     * @param string|null $fields Comma-separated list of columns to select
     * @return array Matching records as associative arrays
     * 
     * @example
     * $activeUsers = CRUD::table('users')->select('status = ? AND age > ?', ['active', 18], 'id, name');
     */
    public function select(?string $where = null, array $params = [], ?string $fields = '*'): array
    {
        $fields ??= '*';

        if ($fields === '*') {
            // Caso especial: selecionar todos os campos
            $validatedFields = '*';
        } else {
            // Dividir por vírgula e validar cada campo
            $cols = array_map('trim', explode(',', $fields));
            $valid = [];

            foreach ($cols as $col) {
                // Permite:
                // 1. *
                // 2. campo
                // 3. campo.qualificado
                // 4. campo AS alias
                // 5. campo.qualificado AS alias
                // 6. COUNT(*)
                // 7. SUM(campo)
                // 8. Funções SQL básicas
                if ($col === '*') {
                    $valid[] = '*';
                } elseif (preg_match('/^(\*|[a-zA-Z_][a-zA-Z0-9_\.]*|COUNT\(\*\)|(AVG|SUM|MIN|MAX|COUNT)\([a-zA-Z_][a-zA-Z0-9_\.]*\))(\s+(AS\s+)?[a-zA-Z_][a-zA-Z0-9_]+)?$/i', $col)) {
                    $valid[] = $col;
                }
            }

            $validatedFields = $valid ? implode(', ', $valid) : '*';
        }

        $sql = "SELECT $validatedFields FROM {$this->table}";
        if ($where) {
            $sql .= " WHERE $where";
        }

        return $this->querySQL($sql, $params);
    }

    /**
     * Prepares the system to collect database operations for a transaction.
     *
     * @return void
     * 
     * @example
     * CRUD::prepareTrasactions();
     * CRUD::table('users')->create([...]);
     * CRUD::table('profiles')->create([...]);
     * CRUD::executeTrasactions($error);
     */
    public static function prepareTrasactions(): void
    {
        self::$onTransaction = true;
    }

    /**
     * Executes all collected database operations as a single transaction.
     *
     * @param Exception|null $exception Reference to catch any exception that occurs
     * @return bool True if transaction succeeded, false otherwise
     * 
     * @example
     * $success = CRUD::executeTrasactions($error);
     * if (!$success) {
     *     echo "Transaction failed: " . $error->getMessage();
     * }
     */
    public static function executeTrasactions(?Exception &$exception = null): bool
    {
        self::$onTransaction = false;
        $db = self::$pdo;

        try {
            $db->beginTransaction();
            foreach (self::$transactionsCallbacks as $callback) {
                $callback();
            }
            $db->commit();
            return true;
        } catch (PDOException $e) {
            $db->rollBack();
            $exception = $e;
            error_log("[CRUD TRANSACTION ERROR] " . $e->getMessage());
        }

        return false;
    }

    /**
     * Executes a SELECT query and returns results.
     *
     * @param string $sql SQL query
     * @param array $params Query parameters (can be simple values or arrays with 'value' and 'type')
     * @return array Query results
     * @internal Used internally for query execution
     */
    private function querySQL(string $sql, array $params = []): array
    {
        try {
            $stmt = self::$pdo->prepare($sql);

            // Bind dos parâmetros com suporte a tipos
            $this->bindParamsWithTypes($stmt, $params);

            $stmt->execute();

            return preg_match('/LIMIT\s+1/i', $sql)
                ? ($stmt->fetch() ?: [])
                : $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("CRUD query error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Executes an INSERT, UPDATE, or DELETE query.
     *
     * @param string $sql SQL query
     * @param array $params Query parameters
     * @return int|false Number of affected rows of false in exceptions case
     * @internal Used internally for query execution
     */
    private function executeSQL(string $sql, array $params = []): int|false
    {
        if (self::$onTransaction) {
            self::$transactionsCallbacks[] = function () use ($sql, $params) {
                $stmt = self::$pdo->prepare($sql);
                $this->bindParamsWithTypes($stmt, $params);
                $stmt->execute();
                return $stmt->rowCount();
            };
            return 0;
        }

        try {
            $stmt = self::$pdo->prepare($sql);
            $this->bindParamsWithTypes($stmt, $params);
            $stmt->execute();
            return $stmt->rowCount();
        } catch (Exception $e) {
            error_log("CRUD execute error: " . $e->getMessage());
            return false;
        }
    }

}
