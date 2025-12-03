<?php

namespace Eril\CRUD\Builders;

/**
 * Trait for building the complete SQL query
 */
trait QueryBuilder
{
    /**
     * Builds the complete SQL query
     *
     * @return string SQL query
     * @internal
     */
    protected function buildQuery(): string
    {
        $sql = "SELECT {$this->fields} FROM {$this->table}"
            . $this->buildJoin()
            . $this->buildWhere()
            . $this->buildGroup()
            . $this->buildHaving();

        if ($this->order) {
            $sql .= " {$this->order}";
        }

        if ($this->limit) {
            $sql .= " {$this->limit}";
        }

        if (!empty($this->unions)) {
            $sql .= $this->buildUnion();
        }

        return $sql;
    }


    /**
     * @internal Method created for debugging purposes
     *
     * @return string SQL query
     */
    public function mountSQL(): string
    {
        // Para debugging, mostra SQL com parâmetros
        return $this->buildQuery();
    }

    /**
     * Builds the JOIN clause
     *
     * @return string JOIN clause
     * @internal
     */
    protected function buildJoin(): string
    {
        return !empty($this->joins) ? ' ' . implode(' ', $this->joins) : '';
    }

    /**
     * Builds the GROUP BY clause
     *
     * @return string GROUP BY clause
     * @internal
     */
    protected function buildGroup(): string
    {
        return $this->group ? " {$this->group}" : '';
    }

    /**
     * Builds the HAVING clause
     *
     * @return string HAVING clause
     * @internal
     */
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

    /**
     * Builds the UNION clause
     *
     * @return string UNION clause
     * @internal
     */
    protected function buildUnion(): string
    {
        if (empty($this->unions)) {
            return '';
        }

        $unionSql = '';
        foreach ($this->unions as [$query, $all]) {
            $unionSql .= ' UNION ' . ($all ? 'ALL ' : '') . '(' . $query->mountSQL() . ')';
            $this->params = array_merge($this->params, $query->getParams());
        }

        return $unionSql;
    }
}
