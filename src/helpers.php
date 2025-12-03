<?php
// src/helpers.php

use Eril\CRUD\CRUD as BaseCRUD;

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
     * @throws Exception
     */
    function crud(string $table, string $primaryKey = 'id'): BaseCRUD
    {
        return BaseCRUD::table($table, $primaryKey);
    }
}
