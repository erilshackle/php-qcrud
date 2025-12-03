<?php

namespace Eril\CRUD\Traits;

use Eril\CRUD\QueryCrud;

/**
 * Trait com aliases para métodos WHERE comuns.
 * 
 * @method self whereNot(string $field, mixed $evaluator = null, mixed $value = null) WHERE NOT
 * @method self orWhereNot(string $field, mixed $evaluator = null, mixed $value = null) OR WHERE NOT
 * @method self whereBetween(string $field, mixed $start, mixed $end, bool $not = false) WHERE BETWEEN
 * @method self whereNotBetween(string $field, mixed $start, mixed $end) WHERE NOT BETWEEN
 * @method self orWhereBetween(string $field, mixed $start, mixed $end, bool $not = false) OR WHERE BETWEEN
 * @method self orWhereNotBetween(string $field, mixed $start, mixed $end) OR WHERE NOT BETWEEN
 * @method self orWhereSelect(string $column, string $operator, QueryCrud $subQuery) OR com subquery pré-construída
 * @method self whereInSelect(string $column, QueryCrud $subQuery) IN com subquery pré-construída
 * @method self whereNotInSelect(string $column, QueryCrud $subQuery) NOT IN com subquery pré-construída
 */

trait WhereAliases
{
    // 🔹 MÉTODOS EXISTENTES - APENAS MOVENDO DA CLASSE PARA TRAIT

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
}