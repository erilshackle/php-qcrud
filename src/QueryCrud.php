<?php

namespace Eril\QCrud;

use Eril\QCrud\Exceptions\CrudException;
use PDO;
use Eril\QCrud\Cache\CacheManager;
use Eril\QCrud\Execution\QueryExecutor;
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
 * @package Eril\QCrud
 * @version 1.0.0
 */
class QueryCrud
{
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

    private bool $cacheEnabled = false;
    private QueryExecutor $executor;

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

        if ($table !== '' && !preg_match($pattern, $table)) {
            throw new InvalidArgumentException("QueryCrud: Invalid table name in constructor: `$table`");
        }
        $this->table = $table;
        $this->pdo = $pdo;
        $this->executor = new QueryExecutor();
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
            throw new RuntimeException("PDO connection not set. Provide a PDO instance to the query builder.");
        }
        $sql = $this->buildQuery();
        try {
            return $this->executor->fetchAll($this->pdo, $sql, $this->compiledParams(), $this->cacheEnabled);
        } catch (\Throwable $exception) {
            throw new RuntimeException("Error executing query: " . $exception->getMessage(), 0, $exception);
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
        if (!$this->pdo) return false;
        $sql = "SELECT 1 FROM {$this->table}" . $this->buildJoin() . $this->buildWhere() . " LIMIT 1";
        try {
            return $this->executor->exists($this->pdo, $sql, $this->params, $this->cacheEnabled);
        } catch (\Throwable) {
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
            if ($value instanceof self) {
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
            if ($value instanceof self) {
                $result[$key] = clone $value;
            } elseif (is_array($value)) {
                $result[$key] = $this->deepCloneArray($value);
            } else {
                $result[$key] = $value;
            }
        }
        return $result;
    }


    public function join(string|array $table, string $on, string $type = 'INNER'): self
    {
        if (is_array($table)) {
            $table = "{$table[0]} AS {$table[1]}";
        } else {
            $table = preg_replace('/(.+)AS\s+(.+)/', '$1 AS $2', $table);
        }
        $this->joins[] = strtoupper($type) . " JOIN $table ON $on";
        return $this;
    }

    public function joinLeft(string|array $table, string $on): self
    {
        return $this->join($table, $on, 'LEFT');
    }

    public function joinRight(string|array $table, string $on): self
    {
        return $this->join($table, $on, 'RIGHT');
    }

    public function joinInner(string|array $table, string $on): self
    {
        return $this->join($table, $on, 'INNER');
    }

    protected function buildJoin(): string
    {
        return !empty($this->joins) ? ' ' . implode(' ', $this->joins) : '';
    }

    protected function buildGroup(): string
    {
        return $this->group ? " {$this->group}" : '';
    }

    protected function buildHaving(): string
    {
        if (empty($this->havings)) {
            return '';
        }

        $sql = ' HAVING ';
        $first = true;

        foreach ($this->havings as $having) {
            if (!$first) {
                $sql .= ' ';
            }

            // Extrai a condição completa
            $condition = $having;

            if ($first && str_starts_with(trim($having), 'OR')) {
                // Remove OR do primeiro HAVING
                $condition = preg_replace('/^\s*OR\s+/', '', $having);
                $sql .= $condition;
            } else {
                $sql .= $condition;
            }

            $first = false;
        }

        return $sql;
    }

    public function count(string $field = '*'): int
    {
        return $this->executeAggregate('COUNT', $field, 'int');
    }

    public function sum(string $field): float
    {
        return $this->executeAggregate('SUM', $field, 'float');
    }

    public function avg(string $field): float
    {
        return $this->executeAggregate('AVG', $field, 'float');
    }

    public function min(string $field): mixed
    {
        return $this->executeAggregate('MIN', $field, 'mixed');
    }

    public function max(string $field): mixed
    {
        return $this->executeAggregate('MAX', $field, 'mixed');
    }

    public function having(string $field, mixed $evaluator, mixed $value = null): self
    {
        // Normaliza o campo
        $field = trim($field);
        
        // Determina se é uma condição de igualdade simples
        $isSimpleEquality = ($value === null);
        
        if ($isSimpleEquality) {
            // Formato: having('count', 5)
            $comparisonValue = $evaluator;
            $operator = '=';
        } else {
            // Formato: having('count', '>', 5)
            $comparisonValue = $value;
            $operator = trim($evaluator);
        }
        
        // Determina como tratar o campo baseado em seu tipo
        $fieldExpression = $this->buildHavingFieldExpression($field);
        
        // Constrói a condição HAVING
        if ($isSimpleEquality) {
            $this->havings[] = "$fieldExpression = ?";
        } else {
            $this->havings[] = "$fieldExpression $operator ?";
        }
        
        // Armazena o parâmetro com tipo detectado
        $type = $this->detectParamTypeForHaving($comparisonValue);
        $this->params[] = ['value' => $comparisonValue, 'type' => $type];
        
        return $this;
    }

    public function orHaving(string $field, mixed $evaluator, mixed $value = null): self
    {
        // Normaliza o campo
        $field = trim($field);
        
        // Determina se é uma condição de igualdade simples
        $isSimpleEquality = ($value === null);
        
        if ($isSimpleEquality) {
            // Formato: orHaving('count', 5)
            $comparisonValue = $evaluator;
            $operator = '=';
        } else {
            // Formato: orHaving('count', '>', 5)
            $comparisonValue = $value;
            $operator = trim($evaluator);
        }
        
        // Determina como tratar o campo baseado em seu tipo
        $fieldExpression = $this->buildHavingFieldExpression($field);
        
        // Constrói a condição HAVING com OR
        if ($isSimpleEquality) {
            $this->havings[] = "OR $fieldExpression = ?";
        } else {
            $this->havings[] = "OR $fieldExpression $operator ?";
        }
        
        // Armazena o parâmetro com tipo detectado
        $type = $this->detectParamTypeForHaving($comparisonValue);
        $this->params[] = ['value' => $comparisonValue, 'type' => $type];
        
        return $this;
    }

    public function havingRaw(string $condition, array $params = []): self
    {
        $this->havings[] = $condition;
        
        // Adiciona parâmetros com tipos detectados
        foreach ($params as $param) {
            $type = $this->detectParamTypeForHaving($param);
            $this->params[] = ['value' => $param, 'type' => $type];
        }
        
        return $this;
    }

    public function havingInt(string $field, string $operator, int $value): self
    {
        $fieldExpression = $this->buildHavingFieldExpression($field);
        $this->havings[] = "$fieldExpression $operator ?";
        $this->params[] = ['value' => $value, 'type' => \PDO::PARAM_INT];
        return $this;
    }

    private function buildHavingFieldExpression(string $field): string
    {
        $field = trim($field);
        
        // Caso 1: É uma função agregada completa (COUNT(*), SUM(column), etc.)
        if (preg_match('/^(COUNT|SUM|AVG|MIN|MAX|GROUP_CONCAT)\(/i', $field)) {
            return $field;
        }
        
        // Caso 2: É apenas "COUNT" ou o nome de uma função
        if (preg_match('/^(COUNT|SUM|AVG|MIN|MAX|GROUP_CONCAT)$/i', $field)) {
            return "$field(*)";
        }
        
        // Caso 3: É '*'
        if ($field === '*') {
            return 'COUNT(*)';
        }
        
        // Caso 4: É um alias simples (apenas letras, números, underscore)
        if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $field)) {
            // Para SQLite, precisamos de CAST para inteiro
            return "CAST($field AS INTEGER)";
        }
        
        // Caso 5: É uma expressão complexa (deixa como está)
        return $field;
    }

    private function detectParamTypeForHaving(mixed $value): int
    {
        if (is_int($value)) {
            return \PDO::PARAM_INT;
        } elseif (is_bool($value)) {
            return \PDO::PARAM_BOOL;
        } elseif (is_null($value)) {
            return \PDO::PARAM_NULL;
        } elseif (is_float($value)) {
            // SQLite não tem PARAM_FLOAT, usa string
            return \PDO::PARAM_STR;
        } else {
            return \PDO::PARAM_STR;
        }
    }

    private function fieldNeedsCast(string $field): bool
    {
        // Remove espaços extras
        $field = trim($field);
        
        // Se for função agregada com parênteses, não precisa de CAST
        if (preg_match('/^(COUNT|SUM|AVG|MIN|MAX|GROUP_CONCAT)\(/i', $field)) {
            return false;
        }
        
        // Se for apenas o nome da função (COUNT), não precisa de CAST
        if (preg_match('/^(COUNT|SUM|AVG|MIN|MAX|GROUP_CONCAT)$/i', $field)) {
            return false;
        }
        
        // '*' ou 'COUNT(*)' não precisa de CAST
        if ($field === '*' || strtoupper(str_replace(' ', '', $field)) === 'COUNT(*)') {
            return false;
        }
        
        // Se contém operadores matemáticos, provavelmente já é uma expressão
        if (preg_match('/[\+\-\*\/]/', $field)) {
            return false;
        }
        
        // Se começa ou termina com parênteses, é uma expressão
        if (str_starts_with($field, '(') || str_ends_with($field, ')')) {
            return false;
        }
        
        // Alias simples (apenas letras, números e underscore) precisa de CAST
        return preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $field) === 1;
    }

    public function select(string $fields): self
    {
        $fields = trim($fields);

        if (empty($fields)) {
            throw new InvalidArgumentException("QueryCrud::select(): Fields cannot be empty");
        }

        // Segurança básica: impede múltiplas queries
        if (strpos($fields, ';') !== false) {
            throw new InvalidArgumentException("QueryCrud::select(): Multiple queries not allowed");
        }
        $this->fields =  $fields;
        return $this;
    }

    public function addSelect(string $fields): self
    {
        $fields = trim($fields);
        if ($fields === '' || str_contains($fields, ';')) {
            throw new InvalidArgumentException('QueryCrud::addSelect(): provide one or more select expressions.');
        }
        $this->fields = $this->fields === '*' ? $fields : $this->fields . ', ' . $fields;
        return $this;
    }

    public function groupBy(string $field): self
    {
        $this->group = "GROUP BY $field";
        return $this;
    }

    public function orderBy(string $field, string $sort = 'ASC'): self
    {
        $fieldUpper = strtoupper(trim($field));

        if ($fieldUpper === 'RAND()' || $fieldUpper === 'RANDOM()') {
            $this->order = "ORDER BY $fieldUpper";
            return $this;
        }

        $sort = strtoupper(trim($sort));
        if (!in_array($sort, ['ASC', 'DESC'], true)) {
            throw new InvalidArgumentException('Sort direction must be ASC or DESC.');
        }
        $this->order = "ORDER BY $field $sort";
        return $this;
    }

    public function orderByDesc(string $field): self
    {
        return $this->orderBy($field, 'DESC');
    }

    public function orderByRandom(): self
    {
        $driver = $this->pdo?->getAttribute(PDO::ATTR_DRIVER_NAME) ?? 'mysql';
        $random = match ($driver) {
            'sqlite', 'pgsql' => 'RANDOM()',
            default => 'RAND()'
        };
        return $this->orderBy($random);
    }

    public function limit(int $limit, int $offset = 0): self
    {
        if ($limit < 0 || $offset < 0) {
            throw new InvalidArgumentException('Limit and offset must be non-negative integers.');
        }
        $driver = $this->pdo?->getAttribute(PDO::ATTR_DRIVER_NAME);
        $this->limit = $driver === 'pgsql'
            ? "LIMIT $limit OFFSET $offset"
            : "LIMIT $offset, $limit";
        return $this;
    }

    public function paginate(int $perPage, int $page = 1): array
    {
        if ($perPage < 1) {
            throw new InvalidArgumentException("Per page must be at least 1");
        }
        
        if ($page < 1) {
            throw new InvalidArgumentException("Page number must be at least 1");
        }
        
        $offset = ($page - 1) * $perPage;
        return $this->limit($perPage, $offset)->get();
    }

    public function union(QueryCrud|callable $query, bool $all = false): self
    {
        if ($query instanceof QueryCrud) {
            $this->unions[] = [$query, $all];
        } else {
            $unionQuery = new QueryCrud($this->table, $this->pdo);
            $query($unionQuery);
            $this->unions[] = [$unionQuery, $all];
        }
        return $this;
    }

    public function unionAll(QueryCrud|callable $query): self
    {
        return $this->union($query, true);
    }

    private function addWhere(string $field, mixed $evaluator, mixed $value = null, bool $isOr = false): self
    {
        $not = false;

        if (str_starts_with($field, '!')) {
            $not = true;
            $field = substr($field, 1);
        }

        // CASE 1: Subquery via Closure (without operator)
        if ($value === null && is_callable($evaluator) && !is_string($evaluator)) {
            return $isOr
                ? $this->orWhereSubquery($field, $not ? 'NOT IN' : 'IN', $evaluator)
                : $this->whereSubquery($field, $not ? 'NOT IN' : 'IN', $evaluator);
        }

        // CASE 2: Operator with Subquery via Closure
        if (is_callable($value) && !is_string($value)) {
            if (!is_string($evaluator)) {
                throw new InvalidArgumentException(
                    "Operator must be string when value is callable. " .
                        "For subquery without explicit operator, use: where('field', function(\$q) { ... })"
                );
            }
            return $isOr
                ? $this->orWhereSubquery($field, $not ? "NOT $evaluator" : $evaluator, $value)
                : $this->whereSubquery($field, $not ? "NOT $evaluator" : $evaluator, $value);
        }

        // CASE 3: IN/NOT IN operator with Subquery via Closure
        if (($evaluator === 'IN' || $evaluator === 'NOT IN') && is_callable($value) && !is_string($value)) {
            return $isOr
                ? $this->orWhereSubquery($field, $evaluator, $value)
                : $this->whereSubquery($field, $evaluator, $value);
        }

        // Original processing (for non-subquery cases)
        if ($value === null) {
            if (is_null($evaluator)) {
                $condition = "$field IS " . ($not ? 'NOT NULL' : 'NULL');
            } elseif (is_array($evaluator) && count($evaluator) === 3 && $evaluator[1] === '><') {
                if ($not) {
                    $condition = "($field < ? OR $field > ?)";
                } else {
                    $condition = "$field BETWEEN ? AND ?";
                }
                $this->params[] = $evaluator[0];
                $this->params[] = $evaluator[2];
            } elseif (is_array($evaluator)) {
                $placeholders = implode(',', array_fill(0, count($evaluator), '?'));
                $condition = $not
                    ? "$field NOT IN ($placeholders)"
                    : "$field IN ($placeholders)";
                $this->params = array_merge($this->params, $evaluator);
            } else {
                $condition = $not ? "$field <> ?" : "$field = ?";
                $this->params[] = $evaluator;
            }
        } else {
            $condition = $not ? "$field NOT $evaluator ?" : "$field $evaluator ?";
            $this->params[] = $value;
        }

        // Store in appropriate array
        if ($isOr) {
            $this->orWheres[] = $condition;
        } else {
            $this->wheres[] = $condition;
        }

        return $this;
    }

    public function where(string $field, mixed $evaluator, mixed $value = null): self
    {
        return $this->addWhere($field, $evaluator, $value, false);
    }

    public function orWhere(string $field, mixed $evaluator, mixed $value = null): self
    {
        return $this->addWhere($field, $evaluator, $value, true);
    }

    public function whereSubquery(string $column, string $operator, callable|QueryCrud $callback, ?string $table = null): self
    {
        $sub = $this->prepareSubquery($callback, $table);
        $this->wheres[] = [$column, $operator, $sub];
        return $this;
    }

    public function orWhereSubquery(string $column, string $operator, callable|QueryCrud $callback, ?string $table = null): self
    {
        $sub = $this->prepareSubquery($callback, $table);
        $this->orWheres[] = [$column, $operator, $sub];
        return $this;
    }

    public function whereSelect(string $column, string $operator, QueryCrud $subQuery): self
    {
        $this->wheres[] = [$column, $operator, $subQuery];
        return $this;
    }

    public function orWhereSelect(string $column, string $operator, QueryCrud $subQuery): self
    {
        $this->orWheres[] = [$column, $operator, $subQuery];
        return $this;
    }

    private function addWhereExists(
        callable $callback,
        bool $isNot = false,
        bool $isOr = false,
        string|array|null $table = null
    ): self {
        $subqueryTable = is_array($table) ? implode(' ', $table) : ($table ?? $this->table);
        $subQuery = QueryCrud::subquery($subqueryTable, $this->pdo);

        // Executar o callback para construir a subquery
        $callback($subQuery);

        // Determinar o tipo de condição
        $conditionType = $isNot ? 'NOT EXISTS' : 'EXISTS';

        // Adicionar ao array apropriado
        if ($isOr) {
            $this->orWheres[] = [$conditionType, $subQuery];
        } else {
            $this->wheres[] = [$conditionType, $subQuery];
        }

        return $this;
    }

    public function whereExists(callable $callback, string|array|null $table = null): self
    {
        return $this->addWhereExists($callback, false, false, $table);
    }

    public function orWhereExists(callable $callback, string|array|null $table = null): self
    {
        return $this->addWhereExists($callback, false, true, $table);
    }

    public function whereNotExists(callable $callback, string|array|null $table = null): self
    {
        return $this->addWhereExists($callback, true, false, $table);
    }

    public function orWhereNotExists(callable $callback, string|array|null $table = null): self
    {
        return $this->addWhereExists($callback, true, true, $table);
    }

    public function whereRaw(string $condition, array $params = []): self
    {
        $this->wheres[] = $condition;
        $this->params = array_merge($this->params, $params);
        return $this;
    }

    public function orWhereRaw(string $condition, array $params = []): self
    {
        $this->orWheres[] = $condition;
        $this->params = array_merge($this->params, $params);
        return $this;
    }

    public function whereNot(string $field, mixed $evaluator = null, mixed $value = null): self
    {
        if (str_starts_with($field, '!')) {
            $field = substr($field, 1);
        }
        return $this->where("!$field", $evaluator, $value);
    }

    public function orWhereNot(string $field, mixed $evaluator = null, mixed $value = null): self
    {
        if (str_starts_with($field, '!')) {
            $field = substr($field, 1);
        }
        return $this->orWhere("!$field", $evaluator, $value);
    }

    public function whereBetween(string $field, mixed $start, mixed $end, bool $not = false): self
    {
        if ($not) {
            $this->wheres[] = "($field < ? OR $field > ?)";
        } else {
            $this->wheres[] = "$field BETWEEN ? AND ?";
        }
        $this->params[] = $start;
        $this->params[] = $end;
        return $this;
    }

    public function whereNotBetween(string $field, mixed $start, mixed $end): self
    {
        return $this->whereBetween($field, $start, $end, true);
    }

    public function orWhereBetween(string $field, mixed $start, mixed $end, bool $not = false): self
    {
        if ($not) {
            $this->orWheres[] = "($field < ? OR $field > ?)";
        } else {
            $this->orWheres[] = "$field BETWEEN ? AND ?";
        }
        $this->params[] = $start;
        $this->params[] = $end;
        return $this;
    }

    public function orWhereNotBetween(string $field, mixed $start, mixed $end): self
    {
        return $this->orWhereBetween($field, $start, $end, true);
    }

    public function whereInSelect(string $column, QueryCrud $subQuery): self
    {
        return $this->whereSelect($column, 'IN', $subQuery);
    }

    public function whereNotInSelect(string $column, QueryCrud $subQuery): self
    {
        return $this->whereSelect($column, 'NOT IN', $subQuery);
    }

    public function useCache(bool $enabled = true): self
    {
        if ($enabled) CacheManager::assertConfigured();
        $this->cacheEnabled = $enabled;
        return $this;
    }

    protected function buildWhere(): string
    {
        $andParts = [];
        $orParts = [];
        foreach ($this->wheres as $condition) if ($built = $this->buildCondition($condition)) $andParts[] = $built;
        foreach ($this->orWheres as $condition) if ($built = $this->buildCondition($condition)) $orParts[] = $built;
        if (!$andParts && !$orParts) return '';
        if (!$andParts) return ' WHERE ' . implode(' OR ', $orParts);
        if (!$orParts) return ' WHERE ' . implode(' AND ', $andParts);
        return ' WHERE (' . implode(' AND ', $andParts) . ') OR (' . implode(' OR ', $orParts) . ')';
    }

    protected function buildCondition(mixed $condition): string
    {
        if (is_string($condition)) return $condition;
        if (is_array($condition) && count($condition) === 3) {
            [$column, $operator, $subquery] = $condition;
            return "$column $operator (" . $this->buildSubquerySQL($subquery) . ")";
        }
        if (is_array($condition) && count($condition) === 2 && $condition[1] instanceof self) {
            return $condition[0] . ' (' . $this->buildSubquerySQL($condition[1]) . ')';
        }
        return '';
    }

    private function buildSubquerySQL(QueryCrud $subQuery): string
    {
        $sql = $subQuery->buildQuery();
        foreach ($subQuery->getParams() as $param) {
            $value = is_array($param) ? ($param['value'] ?? $param[0] ?? null) : $param;
            $type = is_array($param) ? ($param['type'] ?? null) : null;
            $replacement = $value === null ? 'NULL' : (is_int($value) || is_float($value) || is_bool($value) || $type === PDO::PARAM_INT
                ? (is_bool($value) ? ($value ? '1' : '0') : (string)$value)
                : "'" . str_replace("'", "''", (string)$value) . "'");
            $position = strpos($sql, '?');
            if ($position === false) break;
            $sql = substr_replace($sql, $replacement, $position, 1);
        }
        return $sql;
    }

    protected function buildQuery(): string
    {
        $sql = "SELECT {$this->fields} FROM {$this->table}" . $this->buildJoin() . $this->buildWhere() . $this->buildGroup() . $this->buildHaving();
        if ($this->order) $sql .= " {$this->order}";
        if ($this->limit) $sql .= " {$this->limit}";
        foreach ($this->unions as [$query, $all]) $sql .= ' UNION ' . ($all ? 'ALL ' : '') . '(' . $query->buildQuery() . ')';
        return $sql;
    }

    private function compiledParams(): array
    {
        $params = $this->params;
        foreach ($this->unions as [$query]) {
            $params = array_merge($params, $query->compiledParams());
        }
        return $params;
    }

    public function mountSQL(): string
    {
        return $this->buildQuery();
    }

    private function prepareSubquery(callable|QueryCrud $callback, ?string $table = null): QueryCrud
    {
        if ($callback instanceof QueryCrud) return $callback;
        $query = QueryCrud::subquery($table ?? $this->table, $this->pdo);
        $callback($query);
        return $query;
    }

    private function executeAggregate(string $function, string $field, string $type = 'mixed'): mixed
    {
        if (!$this->pdo) return match ($type) { 'int' => 0, 'float' => 0.0, default => null };
        $sql = "SELECT $function($field) FROM {$this->table}" . $this->buildJoin() . $this->buildWhere() . $this->buildGroup();
        try {
            $result = $this->executor->fetchColumn($this->pdo, $sql, $this->params, $this->cacheEnabled);
            if ($result === null) return match ($type) { 'int' => 0, 'float' => 0.0, default => null };
            return match ($type) { 'int' => (int)$result, 'float' => (float)$result, default => $result };
        } catch (\Throwable) {
            return match ($type) { 'int' => 0, 'float' => 0.0, default => null };
        }
    }
}
