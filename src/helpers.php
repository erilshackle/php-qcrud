<?php
// src/helpers.php

use Eril\CRUD\CRUD as BaseCRUD;
use Eril\CRUD\QueryCrud;
use Eril\CRUD\Exceptions\CrudException;
use Eril\CRUD\Logging\Logger;

// Create global alias if not exists
if (!class_exists('CRUD', false)) {
    class_alias(BaseCRUD::class, 'CRUD');
}

if (!function_exists('crud')) {
    /**
     * Quick CRUD instance creator
     * 
     * @param string $table Table name
     * @param string $primaryKey Primary key column (default: 'id')
     * @return BaseCRUD CRUD instance
     * @throws CrudException
     */
    function crud(string $table, string $primaryKey = 'id'): BaseCRUD
    {
        return BaseCRUD::table($table, $primaryKey);
    }
}

if (!function_exists('query')) {
    /**
     * Quick QueryCrud instance creator
     * 
     * @param string $table Table name (can include alias)
     * @param string|null $as Table alias
     * @return QueryCrud Query builder instance
     * @throws CrudException
     */
    function query(string $table, ?string $as = null): QueryCrud
    {
        return BaseCRUD::query($table, $as);
    }
}

if (!function_exists('crud_register_connection')) {
    /**
     * Register database connection
     * 
     * @param PDO|array $connection PDO instance or callback array
     * @return void
     * @throws CrudException
     */
    function crud_register_connection(PDO|array $connection): void
    {
        BaseCRUD::registerConnection($connection);
    }
}

if (!function_exists('crud_init_logging')) {
    /**
     * Initialize CRUD logging system
     * 
     * @param array $config Logger configuration
     * @return void
     */
    function crud_init_logging(array $config = []): void
    {
        BaseCRUD::initLogging($config);
    }
}

if (!function_exists('crud_prepare_transactions')) {
    /**
     * Prepare for transaction
     * 
     * @return void
     */
    function crud_prepare_transactions(): void
    {
        BaseCRUD::prepareTransactions();
    }
}

if (!function_exists('crud_execute_transactions')) {
    /**
     * Execute prepared transactions
     * 
     * @param Exception|null $exception Reference to catch exception
     * @return bool True if success
     */
    function crud_execute_transactions(?Exception &$exception = null): bool
    {
        return BaseCRUD::executeTransactions($exception);
    }
}

if (!function_exists('crud_logs_tail')) {
    /**
     * Get last error logs
     * 
     * @param int $lines Number of lines (default: 50)
     * @return array Array of log entries
     */
    function crud_logs_tail(int $lines = 50): array
    {
        return Logger::tail($lines);
    }
}

if (!function_exists('crud_logs_clear')) {
    /**
     * Clear all logs
     * 
     * @return bool True if cleared
     */
    function crud_logs_clear(): bool
    {
        return Logger::clear();
    }
}

if (!function_exists('crud_logs_stats')) {
    /**
     * Get log statistics
     * 
     * @return array Statistics array
     */
    function crud_logs_stats(): array
    {
        return Logger::stats();
    }
}