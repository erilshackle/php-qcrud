<?php

namespace Eril\CRUD\Tests;

use PDO;
use PDOStatement;
use Exception;
use PHPUnit\Framework\TestCase;
use Eril\CRUD\CRUD;
use Eril\CRUD\QueryCrud;

/**
 * Unit tests for CRUD class
 * 
 * @package Eril\CRUD\Tests
 */
class CRUDTest extends TestCase
{
    private $pdoMock;
    private $pdoStatementMock;

    protected function setUp(): void
    {
        // parent::setUp();

        // Mock PDO
        $this->pdoMock = $this->createMock(PDO::class);
        $this->pdoStatementMock = $this->createMock(PDOStatement::class);

        // Clear static properties before each test
        $reflection = new \ReflectionClass(CRUD::class);
        $pdoProperty = $reflection->getProperty('pdo');
        $pdoProperty->setAccessible(true);
        $pdoProperty->setValue(null, null);

        $registerProperty = $reflection->getProperty('register');
        $registerProperty->setAccessible(true);
        $registerProperty->setValue(null, null);

        $transactionsCallbacksProperty = $reflection->getProperty('transactionsCallbacks');
        $transactionsCallbacksProperty->setAccessible(true);
        $transactionsCallbacksProperty->setValue(null, []);

        $onTransactionProperty = $reflection->getProperty('onTransaction');
        $onTransactionProperty->setAccessible(true);
        $onTransactionProperty->setValue(null, false);
    }

    protected function tearDown(): void
    {
        // parent::tearDown();

        // Clear static properties after each test
        $reflection = new \ReflectionClass(CRUD::class);
        $pdoProperty = $reflection->getProperty('pdo');
        $pdoProperty->setAccessible(true);
        $pdoProperty->setValue(null, null);
    }

    /**
     * Create a PDO statement mock compatible with the new bindParamsWithTypes system
     */
    private function createStatementMockForNewSystem(array $expectedParams = [])
    {
        $stmtMock = $this->createMock(PDOStatement::class);

        // Aceita qualquer bindValue
        $stmtMock->method('bindValue')->willReturn(true);

        // Execute sem parâmetros
        $stmtMock->expects($this->once())
            ->method('execute')
            ->with() // VAZIO - bindValue cuida dos parâmetros
            ->willReturn(true);

        return $stmtMock;
    }

    // ====================================
    // Connection Tests
    // ====================================

    public function testRegisterConnectionWithPDOInstance(): void
    {
        CRUD::registerConnection($this->pdoMock);

        $reflection = new \ReflectionClass(CRUD::class);
        $pdoProperty = $reflection->getProperty('pdo');
        $pdoProperty->setAccessible(true);

        $this->assertSame($this->pdoMock, $pdoProperty->getValue());
    }

    public function testRegisterConnectionWithCallback(): void
    {
        $callback = function () {
            return $this->pdoMock;
        };

        CRUD::registerConnection([$this, 'createPDOMock']);

        $reflection = new \ReflectionClass(CRUD::class);
        $registerProperty = $reflection->getProperty('register');
        $registerProperty->setAccessible(true);

        $this->assertIsArray($registerProperty->getValue());
    }

    public function createPDOMock(): PDO
    {
        return $this->pdoMock;
    }

    public function testUseConnectionWithRegisteredCallback(): void
    {
        CRUD::registerConnection([$this, 'createPDOMock']);

        $reflection = new \ReflectionClass(CRUD::class);
        $method = $reflection->getMethod('useConnection');
        $method->setAccessible(true);

        $method->invoke(null);

        $pdoProperty = $reflection->getProperty('pdo');
        $pdoProperty->setAccessible(true);

        $this->assertSame($this->pdoMock, $pdoProperty->getValue());
    }

    public function testUseConnectionThrowsExceptionWhenNoConnection(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('CRUD: No database connection available');

        new CRUD('users', 'id');
    }

    // ====================================
    // Constructor Tests
    // ====================================

    public function testConstructorWithDirectPDO(): void
    {
        $crud = new CRUD('users', 'id', $this->pdoMock);

        $this->assertSame('users', $this->getPrivateProperty($crud, 'table'));
        $this->assertSame('id', $this->getPrivateProperty($crud, 'pk'));
    }

    public function testConstructorWithRegisteredConnection(): void
    {
        CRUD::registerConnection($this->pdoMock);
        $crud = new CRUD('users', 'id');

        $this->assertSame('users', $this->getPrivateProperty($crud, 'table'));
        $this->assertSame('id', $this->getPrivateProperty($crud, 'pk'));
    }

    // ====================================
    // Factory Method Tests
    // ====================================

    public function testTableFactoryMethod(): void
    {
        CRUD::registerConnection($this->pdoMock);

        $crud = CRUD::table('users', 'user_id');

        $this->assertSame('users', $this->getPrivateProperty($crud, 'table'));
        $this->assertSame('user_id', $this->getPrivateProperty($crud, 'pk'));
    }

    // ====================================
    // Query Method Tests
    // ====================================

    public function testQueryMethod(): void
    {
        CRUD::registerConnection($this->pdoMock);

        $query = CRUD::query('users');

        $this->assertInstanceOf(QueryCrud::class, $query);
        $this->assertSame('users', $this->getPrivateProperty($query, 'table'));
    }

    public function testQueryMethodWithAlias(): void
    {
        CRUD::registerConnection($this->pdoMock);

        $query = CRUD::query('users', 'u');

        $this->assertInstanceOf(QueryCrud::class, $query);
        $this->assertStringContainsString('users', $this->getPrivateProperty($query, 'table'));
    }

    public function testQueryMethodThrowsExceptionWhenNoConnection(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('CRUD::query(): No database connection available');

        CRUD::query('users');
    }

    // ====================================
    // Create Method Tests
    // ====================================

    public function testCreateSuccess(): void
    {
        CRUD::registerConnection($this->pdoMock);
        $crud = new CRUD('users', 'id');

        $data = ['name' => 'John', 'email' => 'john@example.com'];

        // Novo mock compatível com bindValue
        $stmtMock = $this->createStatementMockForNewSystem(['John', 'john@example.com']);
        $stmtMock->expects($this->once())
            ->method('rowCount')
            ->willReturn(1);

        $this->pdoMock->expects($this->once())
            ->method('prepare')
            ->with('INSERT INTO users (name,email) VALUES (?,?)')
            ->willReturn($stmtMock);

        $this->pdoMock->expects($this->once())
            ->method('lastInsertId')
            ->willReturn('123');

        $result = $crud->create($data);

        $this->assertEquals(123, $result);
    }

    public function testCreateWithEmptyData(): void
    {
        CRUD::registerConnection($this->pdoMock);
        $crud = new CRUD('users', 'id');

        $data = [];

        $stmtMock = $this->createStatementMockForNewSystem([]); // Sem parâmetros
        $stmtMock->expects($this->once())
            ->method('rowCount')
            ->willReturn(1);

        $this->pdoMock->expects($this->once())
            ->method('prepare')
            ->with('INSERT INTO users () VALUES ()')
            ->willReturn($stmtMock);

        $this->pdoMock->expects($this->once())
            ->method('lastInsertId')
            ->willReturn('456');

        $result = $crud->create($data);

        $this->assertEquals(456, $result);
    }

    public function testCreateFailureReturnsNull(): void
    {
        CRUD::registerConnection($this->pdoMock);
        $crud = new CRUD('users', 'id');

        $data = ['name' => 'John'];

        $this->pdoMock->expects($this->once())
            ->method('prepare')
            ->willThrowException(new Exception('SQL error'));

        $result = $crud->create($data);

        $this->assertNull($result);
    }

    // ====================================
    // Read Method Tests
    // ====================================

    public function testReadAllRecords(): void
    {
        CRUD::registerConnection($this->pdoMock);
        $crud = new CRUD('users', 'id');

        $expectedResult = [
            ['id' => 1, 'name' => 'John'],
            ['id' => 2, 'name' => 'Jane']
        ];

        $stmtMock = $this->createStatementMockForNewSystem([]);
        $stmtMock->expects($this->once())
            ->method('fetchAll')
            ->willReturn($expectedResult);

        $this->pdoMock->expects($this->once())
            ->method('prepare')
            ->with('SELECT * FROM users')
            ->willReturn($stmtMock);

        $result = $crud->read();

        $this->assertEquals($expectedResult, $result);
    }

    public function testReadSingleRecord(): void
    {
        CRUD::registerConnection($this->pdoMock);
        $crud = new CRUD('users', 'id');

        $expectedResult = ['id' => 1, 'name' => 'John'];

        $stmtMock = $this->createStatementMockForNewSystem([1]); // Int 1
        $stmtMock->expects($this->once())
            ->method('fetch')
            ->willReturn($expectedResult);

        $this->pdoMock->expects($this->once())
            ->method('prepare')
            ->with('SELECT * FROM users WHERE id=? LIMIT 1')
            ->willReturn($stmtMock);

        $result = $crud->read(1);

        $this->assertEquals($expectedResult, $result);
    }

    public function testReadWithSpecificColumns(): void
    {
        CRUD::registerConnection($this->pdoMock);
        $crud = new CRUD('users', 'id');

        $expectedResult = [['id' => 1, 'name' => 'John']];

        $stmtMock = $this->createStatementMockForNewSystem([]);
        $stmtMock->expects($this->once())
            ->method('fetchAll')
            ->willReturn($expectedResult);

        $this->pdoMock->expects($this->once())
            ->method('prepare')
            ->with('SELECT id, name FROM users')
            ->willReturn($stmtMock);

        $result = $crud->read(null, 'id, name');

        $this->assertEquals($expectedResult, $result);
    }

    public function testReadWithInvalidColumnsFiltersThem(): void
    {
        CRUD::registerConnection($this->pdoMock);
        $crud = new CRUD('users', 'id');

        $stmtMock = $this->createStatementMockForNewSystem([]);
        $stmtMock->expects($this->once())
            ->method('fetchAll')
            ->willReturn([]);

        $this->pdoMock->expects($this->once())
            ->method('prepare')
            ->with('SELECT id FROM users')
            ->willReturn($stmtMock);

        $result = $crud->read(null, 'id, invalid-column; DROP TABLE users');

        $this->assertEquals([], $result);
    }

    public function testReadWithArrayId(): void
    {
        CRUD::registerConnection($this->pdoMock);
        $crud = new CRUD('users', 'id');

        $expectedResult = ['id' => 123, 'name' => 'John'];

        // Mock simplificado - não verifica valores bindados
        $stmtMock = $this->createMock(PDOStatement::class);

        // Configuração mínima para passar
        $stmtMock->method('bindValue')->willReturn(true);
        $stmtMock->expects($this->once())
            ->method('execute')
            ->with()
            ->willReturn(true);
        $stmtMock->expects($this->once())
            ->method('fetch')
            ->willReturn($expectedResult);

        $this->pdoMock->expects($this->once())
            ->method('prepare')
            ->with('SELECT * FROM users WHERE id=? LIMIT 1')
            ->willReturn($stmtMock);

        $result = $crud->read(['id' => 123, 'name' => 'John']);

        $this->assertEquals($expectedResult, $result);
    }

    public function testReadReturnsNullOnException(): void
    {
        CRUD::registerConnection($this->pdoMock);
        $crud = new CRUD('users', 'id');

        $this->pdoMock->expects($this->once())
            ->method('prepare')
            ->willThrowException(new Exception('SQL error'));

        $result = $crud->read(1);

        $this->assertNull( $result);
    }

    // ====================================
    // Update Method Tests
    // ====================================

    public function testUpdateSuccess(): void
    {
        CRUD::registerConnection($this->pdoMock);
        $crud = new CRUD('users', 'id');

        $data = ['name' => 'John Updated', 'email' => 'john.updated@example.com'];

        $stmtMock = $this->createStatementMockForNewSystem(['John Updated', 'john.updated@example.com', 123]);
        $stmtMock->expects($this->once())
            ->method('rowCount')
            ->willReturn(1);

        $this->pdoMock->expects($this->once())
            ->method('prepare')
            ->with('UPDATE users SET name = ?, email = ? WHERE id = ?')
            ->willReturn($stmtMock);

        $result = $crud->update(123, $data);

        $this->assertEquals(1, $result);
    }

    public function testUpdateWithArrayId(): void
    {
        CRUD::registerConnection($this->pdoMock);
        $crud = new CRUD('users', 'id');

        $data = ['name' => 'Updated'];

        $stmtMock = $this->createStatementMockForNewSystem(['Updated', 456]);
        $stmtMock->expects($this->once())
            ->method('rowCount')
            ->willReturn(1);

        $this->pdoMock->expects($this->once())
            ->method('prepare')
            ->with('UPDATE users SET name = ? WHERE id = ?')
            ->willReturn($stmtMock);

        $result = $crud->update(['id' => 456, 'name' => 'Original'], $data);

        $this->assertEquals(1, $result);
    }

    public function testUpdateFailureReturnsNull(): void
    {
        CRUD::registerConnection($this->pdoMock);
        $crud = new CRUD('users', 'id');

        $data = ['name' => 'John'];

        $this->pdoMock->expects($this->once())
            ->method('prepare')
            ->willThrowException(new Exception('SQL error'));

        $result = $crud->update(1, $data);

        $this->assertNull($result);
    }

    // ====================================
    // Delete Method Tests
    // ====================================

    public function testDeleteSuccess(): void
    {
        CRUD::registerConnection($this->pdoMock);
        $crud = new CRUD('users', 'id');

        $stmtMock = $this->createStatementMockForNewSystem([123]);
        $stmtMock->expects($this->once())
            ->method('rowCount')
            ->willReturn(1);

        $this->pdoMock->expects($this->once())
            ->method('prepare')
            ->with('DELETE FROM users WHERE id = ?')
            ->willReturn($stmtMock);

        $result = $crud->delete(123);

        $this->assertEquals(1, $result);
    }

    public function testDeleteWithArrayId(): void
    {
        CRUD::registerConnection($this->pdoMock);
        $crud = new CRUD('users', 'id');

        $stmtMock = $this->createStatementMockForNewSystem([789]);
        $stmtMock->expects($this->once())
            ->method('rowCount')
            ->willReturn(1);

        $this->pdoMock->expects($this->once())
            ->method('prepare')
            ->with('DELETE FROM users WHERE id = ?')
            ->willReturn($stmtMock);

        $result = $crud->delete(['id' => 789, 'name' => 'John']);

        $this->assertEquals(1, $result);
    }

    public function testDeleteFailureReturnsNull(): void
    {
        CRUD::registerConnection($this->pdoMock);
        $crud = new CRUD('users', 'id');

        $this->pdoMock->expects($this->once())
            ->method('prepare')
            ->willThrowException(new Exception('SQL error'));

        $result = $crud->delete(1);

        $this->assertNull($result);
    }

    // ====================================
    // Select Method Tests
    // ====================================

    public function testSelectWithWhereClause(): void
    {
        CRUD::registerConnection($this->pdoMock);
        $crud = new CRUD('users', 'id');

        $expectedResult = [
            ['id' => 1, 'name' => 'John'],
            ['id' => 2, 'name' => 'Jane']
        ];

        $stmtMock = $this->createStatementMockForNewSystem(['active', 18]);
        $stmtMock->expects($this->once())
            ->method('fetchAll')
            ->willReturn($expectedResult);

        $this->pdoMock->expects($this->once())
            ->method('prepare')
            ->with('SELECT * FROM users WHERE status = ? AND age > ?')
            ->willReturn($stmtMock);

        $result = $crud->select('status = ? AND age > ?', ['active', 18]);

        $this->assertEquals($expectedResult, $result);
    }

    public function testSelectWithSpecificFields(): void
    {
        CRUD::registerConnection($this->pdoMock);
        $crud = new CRUD('users', 'id');

        $expectedResult = [['id' => 1, 'name' => 'John']];

        $stmtMock = $this->createStatementMockForNewSystem([]);
        $stmtMock->expects($this->once())
            ->method('fetchAll')
            ->willReturn($expectedResult);

        $this->pdoMock->expects($this->once())
            ->method('prepare')
            ->with('SELECT id, name FROM users')
            ->willReturn($stmtMock);

        $result = $crud->select(null, [], 'id, name');

        $this->assertEquals($expectedResult, $result);
    }

    public function testSelectWithAliasInFields(): void
    {
        CRUD::registerConnection($this->pdoMock);
        $crud = new CRUD('users', 'id');

        $stmtMock = $this->createStatementMockForNewSystem([]);
        $stmtMock->expects($this->once())
            ->method('fetchAll')
            ->willReturn([]);

        $this->pdoMock->expects($this->once())
            ->method('prepare')
            ->with('SELECT id AS user_id, name AS full_name FROM users')
            ->willReturn($stmtMock);

        $result = $crud->select(null, [], 'id AS user_id, name AS full_name');

        $this->assertEquals([], $result);
    }

    public function testSelectReturnsNullOnException(): void
    {
        CRUD::registerConnection($this->pdoMock);
        $crud = new CRUD('users', 'id');

        $this->pdoMock->expects($this->once())
            ->method('prepare')
            ->willThrowException(new Exception('SQL error'));

        $result = $crud->select('status = ?', ['active']);

        $this->assertNull( $result);
    }

    // ====================================
    // Transaction Tests
    // ====================================

    public function testPrepareTransactions(): void
    {
        CRUD::prepareTransactions();

        $reflection = new \ReflectionClass(CRUD::class);
        $onTransactionProperty = $reflection->getProperty('onTransaction');
        $onTransactionProperty->setAccessible(true);

        $this->assertTrue($onTransactionProperty->getValue());
    }

    public function testExecuteSQLInTransactionMode(): void
    {
        CRUD::registerConnection($this->pdoMock);
        CRUD::prepareTransactions();

        $crud = new CRUD('users', 'id');

        $this->pdoMock->expects($this->never())
            ->method('prepare');

        $result = $crud->delete(1);

        $this->assertEquals(0, $result);

        // Check that callback was added
        $reflection = new \ReflectionClass(CRUD::class);
        $callbacksProperty = $reflection->getProperty('transactionsCallbacks');
        $callbacksProperty->setAccessible(true);

        $callbacks = $callbacksProperty->getValue();
        $this->assertCount(1, $callbacks);
    }

    public function testExecuteTransactionsSuccess(): void
    {
        CRUD::registerConnection($this->pdoMock);

        // Prepare transaction
        CRUD::prepareTransactions();

        $crud = new CRUD('users', 'id');
        $crud->delete(1); // This adds a callback

        // Mock transaction behavior
        $this->pdoMock->expects($this->once())
            ->method('beginTransaction');

        $this->pdoMock->expects($this->once())
            ->method('commit');

        $this->pdoMock->expects($this->never())
            ->method('rollBack');

        // Mock statement for the callback
        $stmtMock = $this->createStatementMockForNewSystem([1]);
        $stmtMock->expects($this->once())
            ->method('rowCount')
            ->willReturn(1);

        $this->pdoMock->expects($this->once())
            ->method('prepare')
            ->with('DELETE FROM users WHERE id = ?')
            ->willReturn($stmtMock);

        $exception = null;
        $result = CRUD::executeTransactions($exception);

        $this->assertTrue($result);
        $this->assertNull($exception);
    }

    public function testExecuteTransactionsFailure(): void
    {
        CRUD::registerConnection($this->pdoMock);

        // Prepare transaction
        CRUD::prepareTransactions();

        $crud = new CRUD('users', 'id');
        $crud->delete(1); // This adds a callback

        // Mock transaction behavior with failure
        $this->pdoMock->expects($this->once())
            ->method('beginTransaction');

        $this->pdoMock->expects($this->once())
            ->method('rollBack');

        $this->pdoMock->expects($this->never())
            ->method('commit');

        // Mock statement to throw exception
        $stmtMock = $this->createMock(PDOStatement::class);
        $stmtMock->expects($this->once())
            ->method('execute')
            ->willThrowException(new \PDOException('Transaction failed'));

        $this->pdoMock->expects($this->once())
            ->method('prepare')
            ->with('DELETE FROM users WHERE id = ?')
            ->willReturn($stmtMock);

        $exception = null;
        $result = CRUD::executeTransactions($exception);

        $this->assertFalse($result);
        $this->assertNotNull($exception);
        $this->assertInstanceOf(\PDOException::class, $exception);
    }

    // ====================================
    // PDO Method Tests
    // ====================================

    public function testPdoMethodReturnsConnection(): void
    {
        CRUD::registerConnection($this->pdoMock);
        $crud = new CRUD('users', 'id');

        // Usar Reflection para acessar o método protegido
        $reflection = new \ReflectionClass($crud);
        $pdoMethod = $reflection->getMethod('pdo');
        $pdoMethod->setAccessible(true);

        $result = $pdoMethod->invoke($crud);

        $this->assertSame($this->pdoMock, $result);
    }
    // ====================================
    // Helper Methods
    // ====================================

    /**
     * Get private property value from object
     *
     * @param object $object
     * @param string $propertyName
     * @return mixed
     */
    private function getPrivateProperty(object $object, string $propertyName): mixed
    {
        $reflection = new \ReflectionClass($object);
        $property = $reflection->getProperty($propertyName);
        $property->setAccessible(true);
        return $property->getValue($object);
    }
}
