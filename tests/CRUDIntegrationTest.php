<?php

namespace Eril\CRUD\Tests;

use PDO;
use Exception;
use PHPUnit\Framework\TestCase;
use Eril\CRUD\CRUD;
use Eril\CRUD\QueryCrud;

/**
 * Integration tests for CRUD class with real SQLite database
 * 
 * @package Eril\CRUD\Tests
 */
class CRUDIntegrationTest extends TestCase
{
    private static $pdo;

    /**
     * Set up database once for all tests
     */
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        // Create SQLite in-memory database
        self::$pdo = DatabaseTestHelper::getConnection();
        DatabaseTestHelper::createTestTables();

        // Register connection for CRUD class
        CRUD::registerConnection(self::$pdo);
    }

    /**
     * Clean up after all tests
     */
    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        DatabaseTestHelper::dropTestTables();
    }

    /**
     * Reset database before each test
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Limpa COMPLETAMENTE o banco
        $pdo = DatabaseTestHelper::getConnection();

        // Drop todas as tabelas
        $tables = ['orders', 'profiles', 'products', 'users'];
        foreach ($tables as $table) {
            try {
                $pdo->exec("DROP TABLE IF EXISTS $table");
            } catch (\Exception $e) {
                // Ignore
            }
        }

        // Recria tudo
        DatabaseTestHelper::createTestTables();
    }

    // ====================================
    // Integration Tests
    // ====================================

    public function testCreateNewUser(): void
    {
        $crud = new CRUD('users', 'id');

        $data = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'age' => 30,
            'status' => 'active'
        ];

        $id = $crud->create($data);

        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);

        // Verify the record was created
        $createdUser = $crud->read($id);

        $this->assertNotEmpty($createdUser);
        $this->assertEquals('Test User', $createdUser['name']);
        $this->assertEquals('test@example.com', $createdUser['email']);
        $this->assertEquals(30, $createdUser['age']);
        $this->assertEquals('active', $createdUser['status']);
    }

    public function testReadAllUsers(): void
    {
        $crud = new CRUD('users', 'id');

        $users = $crud->read();

        $this->assertIsArray($users);
        $this->assertCount(5, $users); // 5 test users inserted

        // Check structure of first user
        $firstUser = $users[0];
        $this->assertArrayHasKey('id', $firstUser);
        $this->assertArrayHasKey('name', $firstUser);
        $this->assertArrayHasKey('email', $firstUser);
        $this->assertArrayHasKey('age', $firstUser);
        $this->assertArrayHasKey('status', $firstUser);
    }

    public function testReadSingleUser(): void
    {
        $crud = new CRUD('users', 'id');

        $user = $crud->read(1);

        $this->assertIsArray($user);
        $this->assertNotEmpty($user);
        $this->assertEquals(1, $user['id']);
        $this->assertEquals('John Doe', $user['name']);
        $this->assertEquals('john@example.com', $user['email']);
    }

    public function testReadWithSpecificColumns(): void
    {
        $crud = new CRUD('users', 'id');

        $users = $crud->read(null, 'id, name, email');

        $this->assertCount(5, $users);

        $firstUser = $users[0];
        $this->assertArrayHasKey('id', $firstUser);
        $this->assertArrayHasKey('name', $firstUser);
        $this->assertArrayHasKey('email', $firstUser);
        $this->assertArrayNotHasKey('age', $firstUser);
        $this->assertArrayNotHasKey('status', $firstUser);
    }

    public function testUpdateUser(): void
    {
        $crud = new CRUD('users', 'id');

        $updateData = [
            'name' => 'John Updated',
            'email' => 'john.updated@example.com',
            'age' => 31
        ];

        $affectedRows = $crud->update(1, $updateData);

        $this->assertEquals(1, $affectedRows);

        // Verify the update
        $updatedUser = $crud->read(1);

        $this->assertEquals('John Updated', $updatedUser['name']);
        $this->assertEquals('john.updated@example.com', $updatedUser['email']);
        $this->assertEquals(31, $updatedUser['age']);
        // Status should remain unchanged
        $this->assertEquals('active', $updatedUser['status']);
    }

    public function testDeleteUser(): void
    {
        $crud = new CRUD('users', 'id');

        // First verify user exists
        $user = $crud->read(1);
        $this->assertNotEmpty($user);

        // Delete the user
        $affectedRows = $crud->delete(1);

        $this->assertEquals(1, $affectedRows);

        // Verify user is deleted
        $deletedUser = $crud->read(1);
        $this->assertEmpty($deletedUser);
    }

    public function testSelectWithWhereClause(): void
    {
        $crud = new CRUD('users', 'id');

        $activeUsers = $crud->select('status = ?', ['active']);

        $this->assertIsArray($activeUsers);
        // Should have 3 active users (John, Jane, Alice)
        $this->assertCount(3, $activeUsers);

        foreach ($activeUsers as $user) {
            $this->assertEquals('active', $user['status']);
        }
    }

    public function testSelectWithMultipleConditions(): void
    {
        $crud = new CRUD('users', 'id');

        $users = $crud->select('status = ? AND age > ?', ['active', 25], 'id, name, age');

        $this->assertCount(2, $users); // John (30) and Alice (28)

        foreach ($users as $user) {
            $this->assertGreaterThan(25, $user['age']);
            $this->assertArrayHasKey('id', $user);
            $this->assertArrayHasKey('name', $user);
            $this->assertArrayHasKey('age', $user);
            $this->assertArrayNotHasKey('email', $user);
        }
    }

    public function testTableFactoryMethod(): void
    {
        $crud = CRUD::table('users');

        $users = $crud->read();

        $this->assertCount(5, $users);
        $this->assertIsArray($users[0]);
    }

    public function testQueryMethod(): void
    {
        $query = CRUD::query('users');

        $this->assertInstanceOf(QueryCrud::class, $query);

        $users = $query->get();

        $this->assertCount(5, $users);
    }

    public function testQueryWithJoin(): void
    {
        $query = CRUD::query('users u')
            ->select('u.id, u.name, p.bio')
            ->joinInner(['profiles', 'p'], 'u.id = p.user_id')
            ->orderBy('u.id', 'ASC');

        $results = $query->get();

        $this->assertCount(3, $results); // Only 3 users have profiles

        $firstResult = $results[0];
        $this->assertEquals(1, $firstResult['id']);
        $this->assertEquals('John Doe', $firstResult['name']);
        $this->assertEquals('Software developer from New York', $firstResult['bio']);
    }

    public function testQueryWithWhereConditions(): void
    {
        $query = CRUD::query('users')
            ->where('status', 'active')
            ->where('age', '>', 25)
            ->orderBy('age', 'ASC');

        $results = $query->get();

        $this->assertCount(2, $results); // John (30) and Alice (28)

        // Should be sorted by age ascending
        $this->assertEquals(28, $results[0]['age']); // Alice
        $this->assertEquals(30, $results[1]['age']); // John
    }

    public function testProductsWithDifferentPrimaryKey(): void
    {
        $crud = new CRUD('products', 'product_id');

        // Test read with different PK
        $product = $crud->read(1);

        $this->assertNotEmpty($product);
        $this->assertEquals(1, $product['product_id']);
        $this->assertEquals('Laptop', $product['name']);
        $this->assertEquals(999.99, $product['price']);

        // Test update with different PK
        $updateData = ['price' => 899.99];
        $affected = $crud->update(1, $updateData);

        $this->assertEquals(1, $affected);

        $updated = $crud->read(1);
        $this->assertEquals(899.99, $updated['price']);
    }

    public function testTransactionSuccess(): void
    {
        $crud = new CRUD('users', 'id');

        // Get initial count
        $initialCount = count($crud->read());

        // Start transaction
        CRUD::prepareTransactions();

        // Perform operations
        $crud->create([
            'name' => 'Transaction User 1',
            'email' => 'trans1@example.com',
            'status' => 'active'
        ]);

        $crud->create([
            'name' => 'Transaction User 2',
            'email' => 'trans2@example.com',
            'status' => 'active'
        ]);

        // Execute transaction
        $exception = null;
        $result = CRUD::executeTransactions($exception);

        $this->assertTrue($result);
        $this->assertNull($exception);

        // Verify users were created
        $finalCount = count($crud->read());
        $this->assertEquals($initialCount + 2, $finalCount);
    }

    public function testTransactionRollbackOnError(): void
    {
        $crud = new CRUD('users', 'id');

        // Get initial count
        $initialCount = count($crud->read());

        // Start transaction
        CRUD::prepareTransactions();

        // Perform operations - first one should succeed
        $crud->create([
            'name' => 'Transaction User',
            'email' => 'trans@example.com',
            'status' => 'active'
        ]);

        // Second operation with duplicate email (should fail)
        $crud->create([
            'name' => 'Duplicate User',
            'email' => 'john@example.com', // Duplicate email
            'status' => 'active'
        ]);

        // Execute transaction - should fail and rollback
        $exception = null;
        $result = CRUD::executeTransactions($exception);

        $this->assertFalse($result);
        $this->assertNotNull($exception);

        // Verify no users were created (rollback happened)
        $finalCount = count($crud->read());
        $this->assertEquals($initialCount, $finalCount);
    }

    public function testAggregateFunctions(): void
    {
        // Test count
        $count = CRUD::query('users')->count();
        $this->assertEquals(5, $count);

        // Test count with condition (nova query)
        $activeCount = CRUD::query('users')->where('status', 'active')->count();
        $this->assertEquals(3, $activeCount);

        // Test sum (nova query)
        $ageSum = CRUD::query('users')->sum('age');
        $this->assertEquals(158, $ageSum);

        // Test average (nova query)
        $ageAvg = CRUD::query('users')->avg('age');
        $this->assertEquals(31.6, $ageAvg);

        // Test min (nova query)
        $minAge = CRUD::query('users')->min('age');
        $this->assertEquals(25, $minAge);

        // Test max (nova query)
        $maxAge = CRUD::query('users')->max('age');
        $this->assertEquals(40, $maxAge);
    }

    public function testGroupByAndHaving(): void
    {
        // Teste 1: GROUP BY básico (query nova)
        $query = CRUD::query('users')
            ->select('status, COUNT(*) as count')
            ->groupBy('status')
            ->orderBy('count', 'DESC');

        $results = $query->get();

        // Deve ter 3 grupos: active, inactive, pending
        $this->assertCount(3, $results);

        // Teste 2: HAVING (outra query nova)
        $query2 = CRUD::query('users')
            ->select('status, COUNT(*) as count')
            ->groupBy('status')
            ->having('COUNT(*)', '>', 1);

        $results2 = $query2->get();

        // Apenas 'active' tem count > 1 (tem 3 usuários)
        $this->assertCount(1, $results2);
        $this->assertEquals('active', $results2[0]['status']);
        $this->assertEquals(3, $results2[0]['count']);
    }

    public function testComplexQueryWithSubquery(): void
    {
        // Usar um status que REALMENTE existe: 'completed'
        $query = CRUD::query('users')
            ->whereSubquery('id', 'IN', function ($q) {
                $q->select('user_id')
                    ->from('orders')
                    ->where('status', 'completed'); // ✅ Status real
            })
            ->orderBy('name', 'ASC');

        $results = $query->get();

        // Baseado nos dados: 
        // - Pedidos 'completed': IDs 1 (user_id: 1) e 3 (user_id: 2)
        // - Usuários com pedidos completed: John Doe (ID: 1) e Jane Smith (ID: 2)
        $this->assertCount(2, $results);

        // Verificar nomes específicos
        $names = array_column($results, 'name');
        sort($names);
        $this->assertEquals(['Jane Smith', 'John Doe'], $names);
    }

    public function testOrWhereConditions(): void
    {
        $query = CRUD::query('users')
            ->where('status', 'active')
            ->orWhere('age', '>', 35);

        $results = $query->get();

        // Should get active users OR users older than 35
        // Active: John(30), Jane(25), Alice(28) = 3 users
        // Age > 35: Bob(35-not >35), Charlie(40) = 1 user
        // Total unique: 4 users (Charlie is not active but >35)
        $this->assertCount(4, $results);
    }

    public function testLimitAndOffset(): void
    {
        $query = CRUD::query('users')
            ->orderBy('id', 'ASC')
            ->limit(2, 1); // Skip first, get next 2

        $results = $query->get();

        $this->assertCount(2, $results);
        $this->assertEquals(2, $results[0]['id']); // Jane
        $this->assertEquals(3, $results[1]['id']); // Bob
    }

    public function testOrderByRandom(): void
    {
        $query = CRUD::query('users')
            ->orderByRandom()
            ->limit(3);

        $results = $query->get();

        $this->assertCount(3, $results);

        // Can't assert order since it's random, but verify we got users
        foreach ($results as $user) {
            $this->assertArrayHasKey('id', $user);
            $this->assertArrayHasKey('name', $user);
        }
    }
}
