<?php
// src/helpers.php

use Eril\QCrud\CRUD as BaseCRUD;
use Eril\QCrud\QueryCrud;
use Eril\QCrud\Exceptions\CrudException;

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
