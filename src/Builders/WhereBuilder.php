<?php

namespace Eril\CRUD\Builders;

use Eril\CRUD\QueryCrud;

/**
 * Trait for building WHERE conditions
 */
trait WhereBuilder
{
    /**
     * Builds the WHERE clause
     */
    protected function buildWhere(): string
    {
        $whereParts = [];
        $orParts = [];

        foreach ($this->wheres as $where) {
            if ($condition = $this->buildCondition($where)) {
                $whereParts[] = $condition;
            }
        }

        foreach ($this->orWheres as $where) {
            if ($condition = $this->buildCondition($where)) {
                $orParts[] = $condition;
            }
        }

        if (empty($whereParts) && empty($orParts)) {
            return '';
        }

        if (empty($whereParts) && !empty($orParts)) {
            return ' WHERE ' . implode(' OR ', $orParts);
        }

        if (!empty($whereParts) && empty($orParts)) {
            return ' WHERE ' . implode(' AND ', $whereParts);
        }

        return ' WHERE (' . implode(' AND ', $whereParts) . ') OR (' . implode(' OR ', $orParts) . ')';
    }

    /**
     * Builds a single condition
     */
    protected function buildCondition(mixed $condition): string
    {
        if (is_string($condition)) {
            return $condition;
        }

        if (is_array($condition)) {
            if (count($condition) === 3) {
                [$col, $op, $sub] = $condition;
                // ✅ SEM coletar parâmetros - usa valores inline
                return "$col $op (" . $this->buildSubquerySQL($sub) . ")";
                
            } elseif (count($condition) === 2) {
                [$op, $sub] = $condition;
                // ✅ SEM coletar parâmetros - usa valores inline
                return "$op (" . $this->buildSubquerySQL($sub) . ")";
            }
        }

        return '';
    }

    /**
     * Build SQL for subquery with values inlined (not as parameters)
     */
    private function buildSubquerySQL(QueryCrud $subQuery): string
    {
        $sql = $subQuery->buildQuery();
        $params = $subQuery->getParams();
        
        if (empty($params)) {
            return $sql;
        }
        
        // Substituir cada ? pelo valor correspondente
        $offset = 0;
        foreach ($params as $param) {
            // Extrair valor do parâmetro
            $value = is_array($param) ? ($param['value'] ?? $param[0] ?? null) : $param;
            
            // Encontrar próximo ?
            $pos = strpos($sql, '?', $offset);
            if ($pos === false) break;
            
            // Escapar valor para SQL
            $replacement = $this->escapeValueForSQL($value);
            
            // Substituir
            $sql = substr_replace($sql, $replacement, $pos, 1);
            $offset = $pos + strlen($replacement);
        }
        
        return $sql;
    }
    
    /**
     * Escape value for inline SQL
     */
    private function escapeValueForSQL(mixed $value): string
    {
        if (is_null($value)) {
            return 'NULL';
        }
        
        if (is_int($value) || is_float($value)) {
            return (string)$value;
        }
        
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        
        // String - escapar aspas
        $escaped = str_replace("'", "''", (string)$value);
        return "'" . $escaped . "'";
    }
}