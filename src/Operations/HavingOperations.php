<?php

namespace Eril\CRUD\Operations;

/**
 * Trait for HAVING operations with SQLite compatibility
 */
trait HavingOperations
{
    /**
     * HAVING condition for aggregated fields
     *
     * @param string $field Aggregated field or alias
     * @param mixed $evaluator Value or operator
     * @param mixed|null $value Value when $evaluator is an operator
     * @return self
     */
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
    
    /**
     * HAVING condition with OR operator
     *
     * @param string $field Aggregated field
     * @param mixed $evaluator Value or operator
     * @param mixed|null $value Value when $evaluator is an operator
     * @return self
     */
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
    
    /**
     * Raw HAVING condition (use with caution)
     *
     * @param string $condition Raw HAVING condition
     * @param array $params Parameters for the condition
     * @return self
     */
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
    
    /**
     * HAVING condition for integer comparisons (optimized for SQLite)
     *
     * @param string $field Field or alias
     * @param string $operator Comparison operator
     * @param int $value Integer value
     * @return self
     */
    public function havingInt(string $field, string $operator, int $value): self
    {
        $fieldExpression = $this->buildHavingFieldExpression($field);
        $this->havings[] = "$fieldExpression $operator ?";
        $this->params[] = ['value' => $value, 'type' => \PDO::PARAM_INT];
        return $this;
    }
    
    /**
     * Constrói a expressão do campo para HAVING
     */
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
    
    /**
     * Detect PDO parameter type based on PHP type
     *
     * @param mixed $value Parameter value
     * @return int PDO parameter type constant
     */
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
    
    /**
     * Determina se um campo precisa de CAST no HAVING para SQLite
     */
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
}