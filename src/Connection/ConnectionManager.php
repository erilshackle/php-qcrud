<?php

namespace Eril\QCrud\Connection;

use Eril\QCrud\Exceptions\CrudException;
use PDO;
use Throwable;

final class ConnectionManager
{
    private static ?PDO $pdo = null;
    private static mixed $factory = null;

    public static function register(PDO|array $connection): void
    {
        if ($connection instanceof PDO) {
            self::$pdo = $connection;
            self::$factory = null;
            return;
        }
        if (!is_callable($connection)) {
            throw new CrudException('Connection factory must be callable.');
        }
        self::$factory = $connection;
        self::$pdo = null;
    }

    public static function reset(): void
    {
        self::$pdo = null;
        self::$factory = null;
    }

    public static function get(?PDO $connection = null): PDO
    {
        if ($connection !== null) {
            self::$pdo = $connection;
        }
        if (self::$pdo === null && self::$factory !== null) {
            try {
                $pdo = call_user_func(self::$factory);
                if (!$pdo instanceof PDO) {
                    throw new CrudException('Connection factory must return a PDO instance.');
                }
                self::$pdo = $pdo;
                self::$factory = null;
            } catch (Throwable $exception) {
                throw new CrudException('Unable to create database connection: ' . $exception->getMessage(), 0, $exception);
            }
        }
        if (self::$pdo === null) {
            throw new CrudException('No database connection available.');
        }
        return self::$pdo;
    }
}
