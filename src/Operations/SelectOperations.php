<?php

namespace Eril\CRUD\Operations;

use Eril\CRUD\Exceptions\CrudException;
use InvalidArgumentException;
use PDO;

/**
 * Trait for SELECT operations
 */
trait SelectOperations
{

    /**
     * Sets the fields to be selected
     *
     * @param string $fields Comma-separated list of fields or SQL expressions
     * @return self
     * @throws InvalidArgumentException If fields are empty
     * 
     * @example
     * ->select('*')
     * ->select('id, name, email')
     * ->select('COUNT(*) AS total, AVG(score) AS average')
     * ->select('CONCAT(first_name, " ", last_name) AS full_name')
     * ->select('DATE_FORMAT(created_at, "%Y-%m-%d") AS created_date')
     * ->select('price * quantity AS subtotal, (price * quantity) * 0.1 AS tax')
     * ->select('(SELECT COUNT(*) FROM orders WHERE user_id = users.id) AS order_count')
     */
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

    /* 
    // VERSÃO ALTERNATIVA: Concatena campos com formatação inteligente
    // Para usar esta versão, comente a linha acima ($this->fields = $fields;)
    // e descomente todo este bloco

    public function select(string $fields): self
    {
        $fields = trim($fields);

        if (empty($fields)) {
            throw new InvalidArgumentException("QueryCrud::select(): Fields cannot be empty");
        }

        if (strpos($fields, ';') !== false) {
            throw new InvalidArgumentException("QueryCrud::select(): Multiple queries not allowed");
        }
        
        // Se já tem campos (e não é apenas '*'), concatena com formatação
        if (!empty($this->fields) && $this->fields !== '*') {
            // Formatação inteligente: se já tem mais de 10 chars, usa nova linha
            $sep = strlen($this->fields) > 10 ? "\n" : ' ';
            $this->fields .= ltrim($sep . $fields, $sep);
        } else {
            // Primeira chamada ou substitui o '*'
            $this->fields = $fields;
        }
        
        return $this;
    }
    */

    /**
     * Groups results by field
     *
     * @param string $field Field for grouping
     * @return self
     */
    public function groupBy(string $field): self
    {
        $this->group = "GROUP BY $field";
        return $this;
    }

    /**
     * Orders results
     *
     * @param string $field Field for ordering
     * @param string $sort Direction (ASC or DESC)
     * @return self
     */
    public function orderBy(string $field, string $sort = 'ASC'): self
    {
        $fieldUpper = strtoupper(trim($field));

        if ($fieldUpper === 'RAND()' || $fieldUpper === 'RANDOM()') {
            $this->order = "ORDER BY $fieldUpper";
            return $this;
        }

        $sort = str_word_count($sort, 1)[0];
        $this->order = "ORDER BY $field " . trim(strtoupper($sort));
        return $this;
    }

    public function orderByRandom(): self
    {
        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $random = match ($driver) {
            'sqlite', 'pgsql' => 'RANDOM()',
            default => 'RAND()'
        };
        return $this->orderBy($random);
    }

    /**
     * Limits the number of results
     *
     * @param int $limit Maximum number of records
     * @param int $offset Number of records to skip
     * @return self
     */
    public function limit(int $limit, int $offset = 0): self
    {
        $this->limit = "LIMIT $offset, $limit";
        return $this;
    }
}
