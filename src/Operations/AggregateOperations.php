<?php

namespace Eril\CRUD\Operations;

/**
 * Trait for aggregate operations
 */
trait AggregateOperations
{

    /**
     * Builds SQL for aggregate functions
     *
     * @param string $function Aggregate function (COUNT, SUM, AVG, MIN, MAX)
     * @param string $field Field name
     * @return string SQL query
     */
    private function mountAgregateSql(string $function, string $field): string
    {
        return "SELECT $function($field) FROM {$this->table}"
            . $this->buildJoin()
            . $this->buildWhere()
            . $this->buildGroup();
    }

    /**
     * Executes an aggregate query
     *
     * @param string $function Aggregate function
     * @param string $field Field name
     * @param string $type Return type ('int', 'float', 'mixed')
     * @return mixed Query result
     */
    private function executeAggregate(string $function, string $field, string $type = 'mixed'): mixed
    {
        $sql = $this->mountAgregateSql($function, $field);

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($this->params);
            $result = $stmt->fetchColumn();

            if ($result === false || $result === null) {
                return match ($type) {
                    'int' => 0,
                    'float' => 0.0,
                    default => null
                };
            }

            return match ($type) {
                'int' => (int) $result,
                'float' => (float) $result,
                default => $result
            };
        } catch (\Exception $e) {
            error_log($e->getMessage());
            return match ($type) {
                'int' => 0,
                'float' => 0.0,
                default => null
            };
        }
    }

    /**
     * Counts the number of records
     *
     * @param string $field Field to count (default: '*')
     * @return int Number of records
     */
    public function count(string $field = '*'): int
    {
        return $this->executeAggregate('COUNT', $field, 'int');
    }

    /**
     * Sum of field values
     *
     * @param string $field Field to sum
     * @return float Total sum
     */
    public function sum(string $field): float
    {
        return $this->executeAggregate('SUM', $field, 'float');
    }

    /**
     * Average of field values
     *
     * @param string $field Field to average
     * @return float Average value
     */
    public function avg(string $field): float
    {
        return $this->executeAggregate('AVG', $field, 'float');
    }

    /**
     * Minimum value of a field
     *
     * @param string $field Field to get minimum value from
     * @return mixed Minimum value
     */
    public function min(string $field): mixed
    {
        return $this->executeAggregate('MIN', $field, 'mixed');
    }

    /**
     * Maximum value of a field
     *
     * @param string $field Field to get maximum value from
     * @return mixed Maximum value
     */
    public function max(string $field): mixed
    {
        return $this->executeAggregate('MAX', $field, 'mixed');
    }
}
