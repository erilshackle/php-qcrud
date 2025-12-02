<?php


namespace Eril\CRUD\Traits;

use PDO;
use PDOStatement;

trait ParamBinders
{

    // No final da classe QueryCrud, antes do fechamento }
    /**
     * Bind parameters with explicit types for PDO
     *
     * @param PDOStatement $stmt Prepared statement
     * @param array $params Parameters array
     */
    private function bindParamsWithTypes(PDOStatement $stmt, array $params): void
    {
        foreach ($params as $index => $param) {
            $paramIndex = $index + 1; // PDO usa 1-based index

            if (is_array($param) && isset($param['value']) && isset($param['type'])) {
                // Parâmetro com tipo definido
                $stmt->bindValue($paramIndex, $param['value'], $param['type']);
            } elseif (is_array($param) && isset($param['type'])) {
                // Parâmetro com tipo mas sem chave 'value' (valor pode ser direto)
                $value = $param['type'] === PDO::PARAM_NULL ? null : $param[0] ?? null;
                $stmt->bindValue($paramIndex, $value, $param['type']);
            } else {
                // Parâmetro simples - detecta tipo automaticamente
                $type = $this->detectParamType($param);
                $stmt->bindValue($paramIndex, $param, $type);
            }
        }
    }

    /**
     * Detect PDO parameter type based on PHP type
     *
     * @param mixed $value Parameter value
     * @return int PDO parameter type constant
     */
    private function detectParamType(mixed $value): int
    {
        if (is_int($value)) {
            return PDO::PARAM_INT;
        } elseif (is_bool($value)) {
            return PDO::PARAM_BOOL;
        } elseif (is_null($value)) {
            return PDO::PARAM_NULL;
        } elseif (is_float($value)) {
            // SQLite não tem PARAM_FLOAT, usa string
            return PDO::PARAM_STR;
        } else {
            return PDO::PARAM_STR;
        }
    }
}
