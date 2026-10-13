<?php

namespace Eril\QCrud\Execution;

use Eril\QCrud\Cache\CacheManager;
use Eril\QCrud\Transaction\TransactionManager;
use PDO;

final class QueryExecutor
{
    private PdoParameterBinder $binder;

    public function __construct(?PdoParameterBinder $binder = null)
    {
        $this->binder = $binder ?? new PdoParameterBinder();
    }

    public function run(PDO $pdo, string $sql, array $params, string $resultMode, bool $cacheEnabled, callable $callback): mixed
    {
        if ($cacheEnabled && !TransactionManager::isExecuting()) {
            return CacheManager::remember($pdo, $sql, $params, $resultMode, $callback);
        }
        return $callback();
    }

    public function fetchAll(PDO $pdo, string $sql, array $params = [], bool $cacheEnabled = false): array
    {
        return $this->run($pdo, $sql, $params, 'rows:assoc', $cacheEnabled, function () use ($pdo, $sql, $params): array {
            $statement = $pdo->prepare($sql);
            $this->binder->bind($statement, $params);
            $statement->execute();
            return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
        });
    }

    public function fetchOne(PDO $pdo, string $sql, array $params = [], bool $cacheEnabled = false): array
    {
        return $this->run($pdo, $sql, $params, 'row:assoc', $cacheEnabled, function () use ($pdo, $sql, $params): array {
            $statement = $pdo->prepare($sql);
            $this->binder->bind($statement, $params);
            $statement->execute();
            return $statement->fetch(PDO::FETCH_ASSOC) ?: [];
        });
    }

    public function fetchColumn(PDO $pdo, string $sql, array $params = [], bool $cacheEnabled = false): mixed
    {
        return $this->run($pdo, $sql, $params, 'scalar', $cacheEnabled, function () use ($pdo, $sql, $params): mixed {
            $statement = $pdo->prepare($sql);
            $this->binder->bind($statement, $params);
            $statement->execute();
            $value = $statement->fetchColumn();
            return $value === false ? null : $value;
        });
    }

    public function exists(PDO $pdo, string $sql, array $params = [], bool $cacheEnabled = false): bool
    {
        return $this->run($pdo, $sql, $params, 'exists:bool', $cacheEnabled, function () use ($pdo, $sql, $params): bool {
            $statement = $pdo->prepare($sql);
            $this->binder->bind($statement, $params);
            $statement->execute();
            return (bool) $statement->fetchColumn();
        });
    }
}
