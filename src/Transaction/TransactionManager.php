<?php

namespace Eril\QCrud\Transaction;

use Eril\QCrud\Cache\CacheManager;
use PDO;
use Throwable;

final class TransactionManager
{
    private static array $callbacks = [];
    private static bool $collecting = false;
    private static bool $executing = false;

    public static function prepare(): void
    {
        self::$callbacks = [];
        self::$collecting = true;
    }

    public static function queue(callable $callback): bool
    {
        if (!self::$collecting) {
            return false;
        }
        self::$callbacks[] = $callback;
        return true;
    }

    public static function isExecuting(): bool
    {
        return self::$executing;
    }

    public static function isCollecting(): bool
    {
        return self::$collecting;
    }

    public static function reset(): void
    {
        self::$callbacks = [];
        self::$collecting = false;
        self::$executing = false;
    }

    public static function pendingCount(): int
    {
        return count(self::$callbacks);
    }

    public static function execute(PDO $pdo, ?\Exception &$exception = null): bool
    {
        self::$collecting = false;
        self::$executing = true;
        $began = false;
        try {
            $pdo->beginTransaction();
            $began = true;
            foreach (self::$callbacks as $callback) {
                $callback();
            }
            $pdo->commit();
            CacheManager::clear();
            return true;
        } catch (Throwable $failure) {
            if ($began || $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $exception = $failure instanceof \Exception ? $failure : new \RuntimeException($failure->getMessage(), 0, $failure);
            return false;
        } finally {
            self::$callbacks = [];
            self::$executing = false;
        }
    }

    public static function transaction(PDO $pdo, callable $callback): mixed
    {
        $pdo->beginTransaction();
        $began = true;
        self::$executing = true;
        try {
            $result = $callback($pdo);
            $pdo->commit();
            CacheManager::clear();
            return $result;
        } catch (Throwable $failure) {
            if ($began || $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $failure;
        } finally {
            self::$executing = false;
        }
    }
}
