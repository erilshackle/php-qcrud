<?php

namespace Eril\CRUD\Operations;

use Eril\CRUD\QueryCrud;
use InvalidArgumentException;

/**
 * Trait for utility operations
 */
trait UtilityOperations
{
    /**
     * Paginates results
     *
     * @param int $perPage Records per page
     * @param int $page Page number (starts at 1)
     * @return array Records for the requested page
     * @throws InvalidArgumentException If perPage or page is invalid
     */
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
    
    /**
     * Adds UNION with another query
     *
     * @param QueryCrud|callable $query QueryCrud instance or function returning QueryCrud
     * @param bool $all If true, uses UNION ALL
     * @return self
     */
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
    
}