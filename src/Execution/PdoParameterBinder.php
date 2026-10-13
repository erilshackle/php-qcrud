<?php

namespace Eril\QCrud\Execution;

use PDO;
use PDOStatement;

final class PdoParameterBinder
{
    public function bind(PDOStatement $statement, array $params): void
    {
        foreach (array_values($params) as $index => $param) {
            $position = $index + 1;
            if (is_array($param) && array_key_exists('type', $param)) {
                $value = array_key_exists('value', $param)
                    ? $param['value']
                    : ($param['type'] === PDO::PARAM_NULL ? null : ($param[0] ?? null));
                $statement->bindValue($position, $value, $param['type']);
                continue;
            }

            $statement->bindValue($position, $param, $this->typeOf($param));
        }
    }

    public function typeOf(mixed $value): int
    {
        return match (true) {
            is_int($value) => PDO::PARAM_INT,
            is_bool($value) => PDO::PARAM_BOOL,
            $value === null => PDO::PARAM_NULL,
            default => PDO::PARAM_STR,
        };
    }
}
