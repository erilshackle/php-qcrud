<?php

namespace Eril\CRUD\Builders;

/**
 * Trait for JOIN operations
 */
trait JoinBuilder
{
    /**
     * Adds a JOIN clause to the query
     *
     * @param string|array $table Table name or array [table, alias]
     * @param string $on JOIN condition
     * @param string $type JOIN type (INNER, LEFT, RIGHT)
     * @return self
     */
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
    
    /**
     * LEFT JOIN table ON condition
     *
     * @param string|array $table Right table (can include alias)
     * @param string $on JOIN condition
     * @return self
     */
    public function joinLeft(string|array $table, string $on): self
    {
        return $this->join($table, $on, 'LEFT');
    }
    
    /**
     * RIGHT JOIN table ON condition
     *
     * @param string|array $table Right table (can include alias)
     * @param string $on JOIN condition
     * @return self
     */
    public function joinRight(string|array $table, string $on): self
    {
        return $this->join($table, $on, 'RIGHT');
    }
    
    /**
     * INNER JOIN table ON condition
     *
     * @param string|array $table Right table (can include alias)
     * @param string $on JOIN condition
     * @return self
     */
    public function joinInner(string|array $table, string $on): self
    {
        return $this->join($table, $on, 'INNER');
    }
}