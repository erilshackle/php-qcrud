# QCrud - Testes

## Instalação

```bash
# Instalar dependências
composer install

# Executar testes
composer test
```

## Comandos disponíveis

- `composer test` - Executa todos os testes
- `composer test-unit` - Executa apenas testes unitários (com mocks)
- `composer test-integration` - Executa apenas testes de integração (com SQLite)

## Estrutura de testes

```
tests/
├── CRUDTest.php               # Testes unitários com mocks PDO
├── CRUDIntegrationTest.php    # Testes de integração com SQLite real
└── DatabaseTestHelper.php     # Helper para criar banco de teste
```

## Banco de dados de teste

Os testes de integração usam SQLite em memória com as seguintes tabelas:

### Tabela `users`
- `id` (INTEGER PRIMARY KEY AUTOINCREMENT)
- `name` (VARCHAR)
- `email` (VARCHAR UNIQUE)
- `age` (INTEGER)
- `status` (VARCHAR)
- `created_at` (DATETIME)
- `updated_at` (DATETIME)

### Tabela `profiles`
- `id` (INTEGER PRIMARY KEY)
- `user_id` (INTEGER FOREIGN KEY)
- `bio` (TEXT)
- `website` (VARCHAR)

### Tabela `products`
- `product_id` (INTEGER PRIMARY KEY)
- `name` (VARCHAR)
- `price` (DECIMAL)
- `category` (VARCHAR)
- `in_stock` (BOOLEAN)

### Tabela `orders`
- `id` (INTEGER PRIMARY KEY)
- `user_id` (INTEGER FOREIGN KEY)
- `product_id` (INTEGER FOREIGN KEY)
- `quantity` (INTEGER)
- `total` (DECIMAL)
- `status` (VARCHAR)
