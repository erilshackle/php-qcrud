<?php

namespace Eril\CRUD\Operations;

use Eril\CRUD\Exceptions\CrudException;
use InvalidArgumentException;
use Eril\CRUD\QueryCrud;

/**
 * Trait for WHERE operations
 */
trait WhereOperations
{


    /**
     * Internal method to add WHERE condition (supports both AND and OR)
     *
     * @param string $field Field name
     * @param mixed $evaluator Value or operator
     * @param mixed|null $value Value when $evaluator is an operator
     * @param bool $isOr Whether it's an OR condition
     * @return self
     * @throws InvalidArgumentException If parameters are invalid
     */
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

    /**
     * Adds an intelligent WHERE condition
     *
     * @param string $field Field name
     * @param mixed $evaluator Value or operator
     * @param mixed|null $value Value when $evaluator is an operator
     * @return self
     * @throws InvalidArgumentException If parameters are invalid
     */
    public function where(string $field, mixed $evaluator, mixed $value = null): self
    {
        return $this->addWhere($field, $evaluator, $value, false);
    }

    /**
     * Adds a WHERE condition with OR operator
     *
     * @param string $field Field name
     * @param mixed $evaluator Value or operator
     * @param mixed|null $value Value when $evaluator is an operator
     * @return self
     */
    public function orWhere(string $field, mixed $evaluator, mixed $value = null): self
    {
        return $this->addWhere($field, $evaluator, $value, true);
    }

    /**
     * Explicit subquery with operator
     *
     * @param string $column Column for comparison
     * @param string $operator SQL operator
     * @param callable|QueryCrud $callback Function or QueryCrud instance
     * @param string|null $table Table for subquery
     * @return self
     */
    public function whereSubquery(string $column, string $operator, callable|QueryCrud $callback, ?string $table = null): self
    {
        $sub = $this->prepareSubquery($callback, $table);
        $this->wheres[] = [$column, $operator, $sub];
        return $this;
    }

    /**
     * Explicit subquery with OR operator
     *
     * @param string $column Column for comparison
     * @param string $operator SQL operator
     * @param callable|QueryCrud $callback Function or QueryCrud instance
     * @param string|null $table Table for subquery
     * @return self
     */
    public function orWhereSubquery(string $column, string $operator, callable|QueryCrud $callback, ?string $table = null): self
    {
        $sub = $this->prepareSubquery($callback, $table);
        $this->orWheres[] = [$column, $operator, $sub];
        return $this;
    }

    /**
     * Prepare a subquery from callback or QueryCrud instance
     */
    private function prepareSubquery(callable|QueryCrud $callback, ?string $table = null): QueryCrud
    {
        if ($callback instanceof QueryCrud) {
            return $callback;
        }

        $sub = QueryCrud::subquery($table ?? $this->table, $this->pdo);
        $callback($sub);
        return $sub;
    }

    /**
     * WHERE with pre-built subquery
     *
     * @param string $column Column for comparison
     * @param string $operator SQL operator
     * @param QueryCrud $subQuery Pre-built QueryCrud instance
     * @return self
     */
    public function whereSelect(string $column, string $operator, QueryCrud $subQuery): self
    {
        $this->wheres[] = [$column, $operator, $subQuery];
        return $this;
    }

    /**
     * WHERE with pre-built subquery and OR operator
     *
     * @param string $column Column for comparison
     * @param string $operator SQL operator
     * @param QueryCrud $subQuery Pre-built QueryCrud instance
     * @return self
     */
    public function orWhereSelect(string $column, string $operator, QueryCrud $subQuery): self
    {
        $this->orWheres[] = [$column, $operator, $subQuery];
        return $this;
    }

    /**
     * Add EXISTS or NOT EXISTS condition with subquery
     *
     * @param callable $callback Function receiving QueryCrud to build subquery
     * @param bool $isNot Whether it's a NOT EXISTS condition
     * @param bool $isOr Whether it's an OR condition
     * @param string|array|null $table Table for subquery
     * @return self
     * @internal Centralized method for EXISTS conditions
     */
    private function addWhereExists(
        callable $callback,
        bool $isNot = false,
        bool $isOr = false,
        string|array|null $table = null
    ): self {
        
        $subQuery = QueryCrud::subquery($table ?? $this->table, $this->pdo);

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

    /**
     * EXISTS condition with subquery.
     *
     * @param callable $callback Function receiving QueryCrud to build subquery
     * @param string|array|null $table Table for subquery
     * @return self
     * 
     * @example
     * ->whereExists(function($q) {
     *     $q->select('1')
     *       ->from('invoices')
     *       ->where('invoices.user_id = users.id');
     * })
     */
    public function whereExists(callable $callback, string|array|null $table = null): self
    {
        return $this->addWhereExists($callback, false, false, $table);
    }

    /**
     * OR EXISTS condition with subquery.
     *
     * @param callable $callback Function receiving QueryCrud to build subquery
     * @param string|array|null $table Table for subquery
     * @return self
     * 
     * @example
     * ->where('status', 'pending')
     * ->orWhereExists(function($q) {
     *     $q->select('1')
     *       ->from('payments')
     *       ->where('payments.order_id = orders.id');
     * })
     */
    public function orWhereExists(callable $callback, string|array|null $table = null): self
    {
        return $this->addWhereExists($callback, false, true, $table);
    }

    /**
     * NOT EXISTS condition with subquery.
     *
     * @param callable $callback Function receiving QueryCrud to build subquery
     * @param string|array|null $table Table for subquery
     * @return self
     * 
     * @example
     * ->whereNotExists(function($q) {
     *     $q->select('1')
     *       ->from('bans')
     *       ->where('bans.user_id = users.id');
     * })
     */
    public function whereNotExists(callable $callback, string|array|null $table = null): self
    {
        return $this->addWhereExists($callback, true, false, $table);
    }

    /**
     * OR NOT EXISTS condition with subquery.
     *
     * @param callable $callback Function receiving QueryCrud to build subquery
     * @param string|array|null $table Table for subquery
     * @return self
     * 
     * @example
     * ->where('active', 1)
     * ->orWhereNotExists(function($q) {
     *     $q->select('1')
     *       ->from('suspensions')
     *       ->where('suspensions.user_id = users.id');
     * })
     */
    public function orWhereNotExists(callable $callback, string|array|null $table = null): self
    {
        return $this->addWhereExists($callback, true, true, $table);
    }

    /**
     * Raw WHERE condition
     *
     * @param string $condition Raw SQL condition
     * @param array $params Parameters for the condition
     * @return self
     */
    public function whereRaw(string $condition, array $params = []): self
    {
        $this->wheres[] = $condition;
        $this->params = array_merge($this->params, $params);
        return $this;
    }

    /**
     * Raw OR WHERE condition
     *
     * @param string $condition Raw SQL condition
     * @param array $params Parameters for the condition
     * @return self
     */
    public function orWhereRaw(string $condition, array $params = []): self
    {
        $this->orWheres[] = $condition;
        $this->params = array_merge($this->params, $params);
        return $this;
    }
}
