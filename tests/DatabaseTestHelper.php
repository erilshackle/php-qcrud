<?php

namespace Eril\QCrud\Tests;

use PDO;
use Exception;

/**
 * Database helper for tests
 */
class DatabaseTestHelper
{
    private static ?PDO $pdo = null;
    
    /**
     * Get PDO connection to SQLite in-memory database
     *
     * @return PDO
     */
    public static function getConnection(): PDO
    {
        if (self::$pdo === null) {
            try {
                self::$pdo = new PDO('sqlite:db.sqlite');
                self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                self::$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                
                // Enable foreign keys
                // self::$pdo->exec('PRAGMA foreign_keys = ON');
            } catch (Exception $e) {
                throw new Exception("Failed to create SQLite connection: " . $e->getMessage());
            }
        }
        
        return self::$pdo;
    }
    
    /**
     * Create test tables
     *
     * @return void
     */
    public static function createTestTables(): void
    {
        $pdo = self::getConnection();
        
        // Users table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(100) NOT NULL,
                email VARCHAR(100) NOT NULL UNIQUE,
                age INTEGER DEFAULT 0,
                status VARCHAR(20) DEFAULT 'active',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // Profiles table (for JOIN tests)
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS profiles (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                bio TEXT,
                website VARCHAR(200),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )
        ");
        
        // Products table (for different primary key tests)
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS products (
                product_id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(100) NOT NULL,
                price DECIMAL(10,2) NOT NULL,
                category VARCHAR(50),
                in_stock BOOLEAN DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // Orders table (for complex queries)
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS orders (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                product_id INTEGER NOT NULL,
                quantity INTEGER NOT NULL,
                total DECIMAL(10,2) NOT NULL,
                status VARCHAR(20) DEFAULT 'pending',
                order_date DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id),
                FOREIGN KEY (product_id) REFERENCES products(product_id)
            )
        ");
        
        // Insert test data
        self::insertTestData();
    }
    
    /**
     * Insert test data
     *
     * @return void
     */
    private static function insertTestData(): void
    {
        $pdo = self::getConnection();
        
        // Check if users table is empty
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
        $result = $stmt->fetch();
        
        if ($result['count'] > 0) {
            return; // Data already inserted
        }
        
        // Insert users
        $users = [
            ['name' => 'John Doe', 'email' => 'john@example.com', 'age' => 30, 'status' => 'active'],
            ['name' => 'Jane Smith', 'email' => 'jane@example.com', 'age' => 25, 'status' => 'active'],
            ['name' => 'Bob Johnson', 'email' => 'bob@example.com', 'age' => 35, 'status' => 'inactive'],
            ['name' => 'Alice Brown', 'email' => 'alice@example.com', 'age' => 28, 'status' => 'active'],
            ['name' => 'Charlie Wilson', 'email' => 'charlie@example.com', 'age' => 40, 'status' => 'pending'],
        ];
        
        foreach ($users as $user) {
            $stmt = $pdo->prepare("
                INSERT INTO users (name, email, age, status) 
                VALUES (:name, :email, :age, :status)
            ");
            $stmt->execute($user);
        }
        
        // Insert profiles
        $profiles = [
            ['user_id' => 1, 'bio' => 'Software developer from New York', 'website' => 'https://john.dev'],
            ['user_id' => 2, 'bio' => 'Designer and artist', 'website' => 'https://jane.design'],
            ['user_id' => 3, 'bio' => 'Business consultant', 'website' => 'https://bob.consulting'],
        ];
        
        foreach ($profiles as $profile) {
            $stmt = $pdo->prepare("
                INSERT INTO profiles (user_id, bio, website) 
                VALUES (:user_id, :bio, :website)
            ");
            $stmt->execute($profile);
        }
        
        // Insert products
        $products = [
            ['name' => 'Laptop', 'price' => 999.99, 'category' => 'Electronics', 'in_stock' => 1],
            ['name' => 'Mouse', 'price' => 29.99, 'category' => 'Electronics', 'in_stock' => 1],
            ['name' => 'Keyboard', 'price' => 79.99, 'category' => 'Electronics', 'in_stock' => 0],
            ['name' => 'Desk', 'price' => 199.99, 'category' => 'Furniture', 'in_stock' => 1],
            ['name' => 'Chair', 'price' => 149.99, 'category' => 'Furniture', 'in_stock' => 1],
        ];
        
        foreach ($products as $product) {
            $stmt = $pdo->prepare("
                INSERT INTO products (name, price, category, in_stock) 
                VALUES (:name, :price, :category, :in_stock)
            ");
            $stmt->execute($product);
        }
        
        // Insert orders
        $orders = [
            ['user_id' => 1, 'product_id' => 1, 'quantity' => 1, 'total' => 999.99, 'status' => 'completed'],
            ['user_id' => 1, 'product_id' => 2, 'quantity' => 2, 'total' => 59.98, 'status' => 'pending'],
            ['user_id' => 2, 'product_id' => 3, 'quantity' => 1, 'total' => 79.99, 'status' => 'completed'],
            ['user_id' => 3, 'product_id' => 4, 'quantity' => 1, 'total' => 199.99, 'status' => 'shipped'],
            ['user_id' => 4, 'product_id' => 5, 'quantity' => 2, 'total' => 299.98, 'status' => 'pending'],
        ];
        
        foreach ($orders as $order) {
            $stmt = $pdo->prepare("
                INSERT INTO orders (user_id, product_id, quantity, total, status) 
                VALUES (:user_id, :product_id, :quantity, :total, :status)
            ");
            $stmt->execute($order);
        }
    }
    
    /**
     * Drop all test tables
     *
     * @return void
     */
    public static function dropTestTables(): void
    {
        $pdo = self::getConnection();
        
        $tables = ['orders', 'profiles', 'products', 'users'];
        
        foreach ($tables as $table) {
            try {
                $pdo->exec("DROP TABLE IF EXISTS $table");
            } catch (Exception $e) {
                // Ignore errors if table doesn't exist
            }
        }
    }
    
    /**
     * Reset database (drop and recreate)
     *
     * @return void
     */
    public static function resetDatabase(): void
    {
        self::dropTestTables();
        self::createTestTables();
    }
    
    /**
     * Get test data for assertions
     *
     * @return array
     */
    public static function getTestData(): array
    {
        return [
            'users' => [
                ['id' => 1, 'name' => 'John Doe', 'email' => 'john@example.com', 'age' => 30, 'status' => 'active'],
                ['id' => 2, 'name' => 'Jane Smith', 'email' => 'jane@example.com', 'age' => 25, 'status' => 'active'],
                ['id' => 3, 'name' => 'Bob Johnson', 'email' => 'bob@example.com', 'age' => 35, 'status' => 'inactive'],
                ['id' => 4, 'name' => 'Alice Brown', 'email' => 'alice@example.com', 'age' => 28, 'status' => 'active'],
                ['id' => 5, 'name' => 'Charlie Wilson', 'email' => 'charlie@example.com', 'age' => 40, 'status' => 'pending'],
            ],
            'products' => [
                ['product_id' => 1, 'name' => 'Laptop', 'price' => 999.99, 'category' => 'Electronics', 'in_stock' => 1],
                ['product_id' => 2, 'name' => 'Mouse', 'price' => 29.99, 'category' => 'Electronics', 'in_stock' => 1],
                ['product_id' => 3, 'name' => 'Keyboard', 'price' => 79.99, 'category' => 'Electronics', 'in_stock' => 0],
                ['product_id' => 4, 'name' => 'Desk', 'price' => 199.99, 'category' => 'Furniture', 'in_stock' => 1],
                ['product_id' => 5, 'name' => 'Chair', 'price' => 149.99, 'category' => 'Furniture', 'in_stock' => 1],
            ]
        ];
    }
}