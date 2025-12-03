<?php

namespace Eril\CRUD;

use Eril\CRUD\Exceptions\CrudException;
use Eril\CRUD\Logging\Logger;
use Exception;
use PDO;
use PDOException;

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
     * Initialize error logging system
     * Call this method during bootstrap if you want to customize logging
     * 
     * @param array{
     *  log_dir:string, log_file:string, max_size:int, max_days:int, enabled:bool
     * } $config Logger configuration
     * 
     * - `log_dir`  :   where to output logs (/logs)
     * - `log_file` :   the logs file name (crud-errors)
     * - `max_size` :   limit size (5MB)
     * - `max_days` :   limit days (7 days retention)
     * - `enabled`  :   enable or disable logs (bool)
     * @return void
     */
    public static function initLogging(array $config = []): void
    {
        Logger::init($config);
    }

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
     * @param bool $enableLogs Whether to enable logs or not.
     * @return void
     * @throws CrudException If connection cannot be established
     */
    public static function registerConnection(PDO|array $connection, $enableLogs = true): void
    {
        if ($connection instanceof PDO) {
            self::useConnection($connection);
        } else {
            self::$register = $connection;
        }

        self::initLogging(['enable' => true]);
    }

    /**
     * Sets or creates the PDO connection instance.
     *
     * @param PDO|null $pdo Optional PDO instance to use directly
     * @return void
     * @throws CrudException If connection cannot be established
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
            Logger::error("Connection error", $e);
            throw new CrudException("Unknown connection error: " . $e->getMessage());
        }
    }

    /**
     * Constructor for CRUD operations on a specific table.
     *
     * @param string $table Database table name
     * @param string $primaryKey Primary key column name (default: 'id')
     * @param PDO|null $pdo Optional PDO instance (uses registered connection if null)
     * @throws CrudException If no database connection is available
     */
    public function __construct(string $table, string $primaryKey = 'id', ?PDO $pdo = null)
    {
        try {
            $this->table = $table;
            $this->pk = $primaryKey;
            self::useConnection($pdo);

            if (self::$pdo === null) {
                throw new CrudException("CRUD: No database connection available");
            }
        } catch (PDOException $exception) {
            Logger::error("PDO Exception in constructor", $exception);
            throw $exception;
        } catch (Exception $e) {
            Logger::error("General Exception in constructor", $e);
            throw $e;
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
     * Creates a QueryCrud instance for advanced query building.
     *
     * @param string $table Table name (can include alias)
     * @param string|null $as Optional table alias
     * @return QueryCrud Query builder instance
     * @throws CrudException If no database connection is available
     * 
     * @example
     * $query = CRUD::query('users u')
     *     ->select('u.*, p.name')
     *     ->joinInner(['profiles', 'p'], 'u.profile_id = p.id');
     */
    public static function query(string $table, ?string $as = null): QueryCrud
    {
        self::useConnection();
        if (!self::$pdo) {
            Logger::error("No database connection available for query");
            throw new CrudException("CRUD::query(): No database connection available");
        }

        $table = $as === null ? $table : "$table AS $as";
        $table = preg_replace('/(.+)\s*AS\s+(.+)/', '$1 $2', $table);

        try {
            return new QueryCrud($table, self::$pdo);
        } catch (Exception $e) {
            Logger::error("Failed to create QueryCrud", $e);
            throw new CrudException("Failed to create QueryCrud: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Creates a new record in the database.
     *
     * @param array $data Associative array of column => value pairs
     * @return string|int|null The ID of the created record (string/int),
     *                         or 0 if inserted without auto-increment ID,
     *                         or null on error
     *                         Returns 0 if 0 rows affected or no auto-increment
     * 
     * @example
     * // With auto-increment ID
     * $id = CRUD::table('users')->create(['name' => 'John']); // "123" or 123
     * 
     * // Without auto-increment or 0 rows
     * $result = CRUD::table('logs')->create(['message' => 'test']); // 0
     * 
     * // Error
     * $result = CRUD::table('invalid')->create($data); // null
     */
    public function create(array $data): string|int|null
    {
        try {
            $columns = implode(',', array_keys($data));
            $placeholders = implode(',', array_fill(0, count($data), '?'));
            $sql = "INSERT INTO {$this->table} ($columns) VALUES ($placeholders)";

            $rowsAffected = $this->executeSQL($sql, array_values($data));

            if ($rowsAffected === 0) {
                return 0;
            }

            $lastInsertId = self::$pdo->lastInsertId();

            if (!$lastInsertId || $lastInsertId === '' || $lastInsertId === '0') {
                return 0;
            }

            if (ctype_digit($lastInsertId) && $lastInsertId <= PHP_INT_MAX) {
                return (int) $lastInsertId;
            }

            // Retorna como string (para bigints)
            return $lastInsertId;
        } catch (Exception $e) {
            Logger::error("CREATE failed on table: {$this->table}", $e);
            return null;
        }
    }

    /**
     * Reads one or multiple records from the database.
     *
     * @param string|int|array|null $id Primary key value(s) or null for all records
     * @param string|null $select Comma-separated list of columns to select
     * @return array|null The record(s) as associative array(s) or null on error
     *                    Returns empty array [] if no records found
     * 
     * @example
     * // Read single record
     * $user = CRUD::table('users')->read(123);
     * 
     * // Read all records with specific columns
     * $users = CRUD::table('users')->read(null, 'id, name, email');
     */
    public function read(string|int|array|null $id = null, ?string $select = null): ?array
    {
        try {
            $cols = $select ? array_map('trim', explode(',', $select)) : ['*'];
            $valid = array_filter($cols, fn($c) => $c === '*' || preg_match('/^[\w]+(\s+as\s+[\w]+|\s+[\w]+)?$/i', $c));
            $select = $valid ? implode(', ', $valid) : '*';

            if ($id === null) {
                return $this->querySQL("SELECT $select FROM {$this->table}");
            }

            $id = is_array($id) ? $id[$this->pk] ?? 0 : $id;
            $result = $this->querySQL("SELECT $select FROM {$this->table} WHERE {$this->pk}=? LIMIT 1", [$id]);

            return $result ?: []; // Retorna array (vazio se não encontrado)
        } catch (Exception $e) {
            Logger::error("READ failed on table: {$this->table}", $e);
            return null;
        }
    }

    /**
     * Updates an existing record in the database.
     *
     * @param string|int|array $id Primary key value or array containing primary key
     * @param array $data Associative array of column => value pairs to update
     * @return int|null Number of affected rows, or null on error
     *                  Returns 0 if no rows affected (record not found)
     * 
     * @example
     * CRUD::table('users')->update(123, ['name' => 'Jane Doe']);
     */
    public function update(string|int|array $id, array $data): ?int
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
            Logger::error("UPDATE failed on table: {$this->table}", $e);
            return null;
        }
    }

    /**
     * Deletes a record from the database.
     *
     * @param string|int|array $id Primary key value or array containing primary key
     * @return int|null Number of affected rows, or null on error
     *                  Returns 0 if no rows affected (record not found)
     * 
     * @example
     * CRUD::table('users')->delete(123);
     */
    public function delete(string|int|array $id): ?int
    {
        try {
            $id = is_array($id) ? ($id[$this->pk] ?? 0) : $id;
            $sql = "DELETE FROM {$this->table} WHERE {$this->pk} = ?";

            return $this->executeSQL($sql, [$id]);
        } catch (Exception $e) {
            Logger::error("DELETE failed on table: {$this->table}", $e);
            return null;
        }
    }

    /**
     * Selects records with custom WHERE conditions.
     *
     * @param string|null $where WHERE clause (without the 'WHERE' keyword)
     * @param array $params Parameters for prepared statement
     * @param string|null $fields Comma-separated list of columns to select
     * @return array|null Matching records as associative arrays, or null on error
     *                    Returns empty array [] if no matching records
     * 
     * @example
     * $activeUsers = CRUD::table('users')->select('status = ? AND age > ?', ['active', 18], 'id, name');
     */
    public function select(?string $where = null, array $params = [], ?string $fields = '*'): ?array
    {
        try {
            $fields ??= '*';

            if ($fields === '*') {
                $validatedFields = '*';
            } else {
                $cols = array_map('trim', explode(',', $fields));
                $valid = [];

                foreach ($cols as $col) {
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

            $result = $this->querySQL($sql, $params);
            return $result ?: []; // Retorna array (pode ser vazio)
        } catch (Exception $e) {
            Logger::error("SELECT failed on table: {$this->table}", $e);
            return null;
        }
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
    public static function prepareTransactions(): void
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
    public static function executeTransactions(?Exception &$exception = null): bool
    {
        self::$onTransaction = false;
        $db = self::$pdo;
        $result = false;

        try {
            $db->beginTransaction();
            foreach (self::$transactionsCallbacks as $callback) {
                $callback();
            }
            $db->commit();
            $result = true;
        } catch (PDOException $e) {
            $db->rollBack();
            $exception = $e;
            Logger::error("Transaction failed", $e);
        }
        self::$transactionsCallbacks = [];
        return $result;
    }

    /**
     * Executes a SELECT query and returns results.
     *
     * @param string $sql SQL query
     * @param array $params Query parameters (can be simple values or arrays with 'value' and 'type')
     * @return array Query results as associative array
     * @throws PDOException If query execution fails
     * @internal Used internally for query execution
     */
    private function querySQL(string $sql, array $params = []): array
    {
        $stmt = self::$pdo->prepare($sql);
        $this->bindParamsWithTypes($stmt, $params);
        $stmt->execute();

        return preg_match('/LIMIT\s+1/i', $sql)
            ? ($stmt->fetch() ?: [])
            : $stmt->fetchAll();
    }

    /**
     * Executes an INSERT, UPDATE, or DELETE query.
     *
     * @param string $sql SQL query
     * @param array $params Query parameters
     * @return int Number of affected rows
     * @throws PDOException If query execution fails
     * @internal Used internally for query execution
     */
    private function executeSQL(string $sql, array $params = []): int
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

        $stmt = self::$pdo->prepare($sql);
        $this->bindParamsWithTypes($stmt, $params);
        $stmt->execute();
        return $stmt->rowCount();
    }
}
