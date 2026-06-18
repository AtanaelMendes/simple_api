# Simple API - PHP Micro Framework

A lightweight PHP micro-framework with **Controller / Service / Repository / Model** pattern. An extremely simplified alternative to Laravel, perfect for small to medium REST APIs.

## Features

-  **Controller / Service / Repository / Model** layered architecture
-  **Routing** system with URL parameters (`{id}` syntax)
-  **Request / Response** objects with built-in helpers
-  **Database** singleton (MySQL & PostgreSQL) with PDO
-  **Migration** system (migrate, rollback, status, make)
-  **Seeder** system for test data
-  **Session-based Auth** with CSRF protection
-  **Validation** via [Respect/Validation](https://respect-validation.readthedocs.io/)
-  **Structured logging** utility
-  **Soft delete** support
-  **CORS** handling

## Requirements

- PHP 7.1.3+
- Composer
- MySQL 5.7+ or PostgreSQL 12+
- Apache with `mod_rewrite` enabled

## Quick Start

### 1. Clone the repository

```bash
git clone https://github.com/your-username/simple-api.git
cd simple-api
```

### 2. Install dependencies

```bash
composer install
```

### 3. Configure environment

```bash
cp .env.example .env
```

Edit `.env` with your database credentials:

```env
APP_DEBUG=true

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=simple_api
DB_USERNAME=root
DB_PASSWORD=
```

### 4. Create the database

```sql
CREATE DATABASE simple_api CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 5. Run migrations

```bash
php migrate.php migrate
```

### 6. (Optional) Run seeders

```bash
php seed.php seed
```

### 7. Configure Apache

Point your Apache virtual host or document root to the project folder. Make sure `mod_rewrite` is enabled. The included `.htaccess` handles URL rewriting automatically.

If your project lives in a subfolder (e.g. `http://localhost/simple_api/`), it works out of the box.

## Project Structure

```
simple_api/
├── index.php              # Entry point: CORS, autoloader, route dispatch
├── bootstrap.php          # Loads Composer, parses .env, defines DEBUG
├── app.php                # App class: route registration & dispatch
├── routes.php             # Route definitions
├── migrate.php            # CLI: migration commands
├── seed.php               # CLI: seeder commands
├── .env.example           # Environment template
├── .htaccess              # Apache rewrite rules
│
├── app/
│   ├── Controllers/       # Request handlers (validation, HTTP concerns)
│   │   ├── Controller.php         # Base controller
│   │   ├── HelloWorldController.php
│   │   └── UserController.php     # Example CRUD
│   │
│   ├── Services/          # Business logic layer
│   │   └── UserService.php
│   │
│   ├── Repository/        # Data access layer (SQL queries)
│   │   └── UserRepository.php
│   │
│   ├── Models/            # Table mirrors (structure definition)
│   │   └── UserModel.php
│   │
│   ├── Core/              # Framework core
│   │   ├── Auth.php       # Session authentication
│   │   ├── Database.php   # PDO singleton (MySQL/PostgreSQL)
│   │   ├── Migration.php  # Migration engine
│   │   ├── Request.php    # HTTP request wrapper
│   │   ├── Response.php   # HTTP response builder
│   │   └── Seeder.php     # Seeder engine
│   │
│   ├── Config/
│   │   └── Environment.php  # .env parser
│   │
│   └── Utils/
│       └── Logger.php     # File-based logger
│
├── database/
│   ├── migrations/        # Migration files
│   └── seeds/             # Seeder files
│
├── logs/                  # Auto-generated log files
└── test/                  # HTTP test files
```

## Request Lifecycle

```
Request → index.php → bootstrap.php → app.php (routing) → Controller → Service → Repository → Model → Database
```

### Layer Responsibilities

| Layer          | Folder             | Responsibility                                                    |
|----------------|--------------------|-----------------------------------------------------------------|
| **Controller** | `app/Controllers/` | HTTP concerns: receive request, validate input, return response  |
| **Service**    | `app/Services/`    | Business logic: rules, transformations, password hashing         |
| **Repository** | `app/Repository/`  | Data access: SQL queries, reads and writes to the database       |
| **Model**      | `app/Models/`      | Table mirror: defines table name, primary key, fillable columns  |

## Example: User CRUD

The template includes a complete User CRUD as an example:

| Method | Endpoint        | Description       |
|--------|-----------------|-------------------|
| POST   | `/users`        | Create user       |
| GET    | `/users`        | List all users    |
| GET    | `/users/{id}`   | Get user by ID    |
| PUT    | `/users/{id}`   | Update user       |
| DELETE | `/users/{id}`   | Soft delete user  |

### Create a user

```bash
curl -X POST http://localhost/simple_api/users \
  -H "Content-Type: application/json" \
  -d '{"user_name": "John Doe", "user_email": "john@example.com", "user_password": "secret123"}'
```

### List users

```bash
curl http://localhost/simple_api/users
```

### Get a user

```bash
curl http://localhost/simple_api/users/1
```

### Update a user

```bash
curl -X PUT http://localhost/simple_api/users/1 \
  -H "Content-Type: application/json" \
  -d '{"user_name": "Jane Doe"}'
```

### Delete a user (soft delete)

```bash
curl -X DELETE http://localhost/simple_api/users/1
```

## How to Add a New Resource

### 1. Create a migration

```bash
php migrate.php make create_products_table
```

Edit the generated file in `database/migrations/` with your SQL.

### 2. Run the migration

```bash
php migrate.php migrate
```

### 3. Create a Model

The Model is the table mirror — it defines structure only, no SQL:

```php
// app/Models/ProductModel.php
<?php
namespace App\Models;

class ProductModel
{
    protected $table = 'products';
    protected $primaryKey = 'id';

    protected $fillable = [
        'name',
        'price',
        'description',
    ];

    public function getTable()       { return $this->table; }
    public function getPrimaryKey()  { return $this->primaryKey; }
    public function getFillable()    { return $this->fillable; }
}
```

### 4. Create a Repository

The Repository handles all SQL queries (data access):

```php
// app/Repository/ProductRepository.php
<?php
namespace App\Repository;

use App\Core\Database;
use App\Models\ProductModel;

class ProductRepository
{
    private $db;
    private $model;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->model = new ProductModel();
    }

    public function findAll()
    {
        $table = $this->model->getTable();
        return $this->db->select("SELECT * FROM {$table} WHERE deleted_at IS NULL");
    }

    public function findById($id)
    {
        $table = $this->model->getTable();
        $pk = $this->model->getPrimaryKey();
        $result = $this->db->select(
            "SELECT * FROM {$table} WHERE {$pk} = :id AND deleted_at IS NULL LIMIT 1",
            ['id' => $id]
        );
        return !empty($result) ? $result[0] : false;
    }

    // ... add create, update, softDelete methods
}
```

### 5. Create a Service

The Service holds all business logic:

```php
// app/Services/ProductService.php
<?php
namespace App\Services;

use App\Repository\ProductRepository;

class ProductService
{
    private $repository;

    public function __construct()
    {
        $this->repository = new ProductRepository();
    }

    public function getAll()
    {
        return $this->repository->findAll();
    }

    public function getById($id)
    {
        return $this->repository->findById($id);
    }

    // ... add create (with validations), update, delete methods
}
```

### 6. Create a Controller

The Controller handles HTTP concerns only:

```php
// app/Controllers/ProductController.php
<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\ProductService;

class ProductController extends Controller
{
    private $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = new ProductService();
    }

    public function index(Request $request, Response $response)
    {
        $products = $this->service->getAll();
        return $response->success($products, 'Products retrieved');
    }
}
```

### 7. Register routes

```php
// routes.php
$app->get("products", [ProductController::class, "index"]);
$app->get("products/{id}", [ProductController::class, "show"]);
$app->post("products", [ProductController::class, "store"]);
$app->put("products/{id}", [ProductController::class, "update"]);
$app->delete("products/{id}", [ProductController::class, "destroy"]);
```

> **Note:** Short class names in routes auto-resolve to `App\Controllers\` namespace.

## Response Helpers

All controller methods receive `Request` and `Response` objects. Use the response helpers:

```php
return $response->success($data, "Message");          // 200
return $response->created($data, "Created");           // 201
return $response->error("Message", 400);               // 400
return $response->validationError($errors);            // 422
return $response->notFound("Not found");               // 404
return $response->unauthorized("Unauthorized");        // 401
return $response->forbidden("Forbidden");              // 403
return $response->noContent();                         // 204
```

## Database Methods

```php
$db = Database::getInstance();

$db->select("SELECT * FROM users WHERE id = :id", ['id' => 1]);   // returns array of rows
$db->insert("INSERT INTO users (...) VALUES (...)", $params);      // returns last insert ID
$db->update("UPDATE users SET ... WHERE ...", $params);            // returns affected rows
$db->delete("DELETE FROM users WHERE ...", $params);               // returns affected rows
$db->execute("CREATE TABLE ...");                                  // DDL statements
```

## Validation

Uses [Respect/Validation](https://respect-validation.readthedocs.io/):

```php
use Respect\Validation\Validator as v;
use Respect\Validation\Exceptions\NestedValidationException;

$validator = v::key('user_email', v::email())
    ->key('user_password', v::stringType()->length(6, null));

try {
    $validator->assert($params);
} catch (NestedValidationException $e) {
    return $response->validationError($e->getMessages());
}
```

## Logger

Built-in file-based logger (`App\Utils\Logger`) with 3 severity levels. Log files are auto-created in the `logs/` folder with daily rotation.

### Signature

```php
Logger::error($message, $context, $filePrefix);
Logger::warning($message, $context, $filePrefix);
Logger::info($message, $context, $filePrefix);
```

| Parameter     | Type            | Description                                                                 |
|---------------|-----------------|-----------------------------------------------------------------------------|
| `$message`    | `string|array`  | The log message. Arrays are automatically converted to JSON.                |
| `$context`    | `string`        | Context info — typically file name and line number.                          |
| `$filePrefix` | `string`        | Log file prefix. Default: `"logErrors"`. File: `logs/{prefix}{dd-mm-YYYY}.log` |

### Log File Format

Files are saved in `logs/` with the naming pattern `{filePrefix}{dd-mm-YYYY}.log`:

```
ERROR [2026-03-05 14:30:00] UserController.php linha-> 34 Connection refused

WARNING [2026-03-05 14:30:01] UserController.php linha-> 35 Slow query detected

INFO [2026-03-05 14:30:02] UserController.php linha-> 36 User created successfully
```

### Usage in Controllers

```php
use App\Utils\Logger;

try {
    // ... your logic
} catch (\Exception $e) {
    // Log error with file name, line number, and a file prefix for grouping
    Logger::error($e->getMessage(), basename(__FILE__)." linha-> ".__LINE__, "UserController_");
    return $response->error('Something went wrong');
}
```

### Log Levels

```php
// ERROR — Exceptions, critical failures
Logger::error($e->getMessage(), basename(__FILE__)." linha-> ".__LINE__, "UserController_");

// WARNING — Non-critical issues, degraded performance
Logger::warning("Slow query: 3.2s", basename(__FILE__)." linha-> ".__LINE__, "UserController_");

// INFO — General events, auditing
Logger::info("User #5 updated profile", basename(__FILE__)." linha-> ".__LINE__, "UserController_");
```

### Logging Arrays

Arrays are automatically serialized to JSON:

```php
Logger::info(['action' => 'login', 'user_id' => 5], basename(__FILE__)." linha-> ".__LINE__, "AuthController_");
// Output: INFO [2026-03-05 14:30:00] AuthController.php linha-> 12 {"action":"login","user_id":5}
```

### Grouping Logs by File Prefix

The third parameter lets you group logs into separate files per controller or feature:

```php
// logs/UserController_05-03-2026.log
Logger::error($msg, $ctx, "UserController_");

// logs/AuthController_05-03-2026.log
Logger::error($msg, $ctx, "AuthController_");

// logs/logErrors05-03-2026.log  (default)
Logger::error($msg, $ctx);
```

## CLI Commands

### Migrations

```bash
php migrate.php migrate          # Run pending migrations
php migrate.php rollback         # Rollback last migration
php migrate.php rollback 3       # Rollback last 3 migrations
php migrate.php status           # Show migration status
php migrate.php make <name>      # Create new migration file
```

### Seeders

```bash
php seed.php seed                # Run all pending seeders
php seed.php run <name>          # Run a specific seeder
php seed.php status              # Show seeder status
php seed.php reset               # Clear seeder history (allows re-run)
```

## Authentication

Session-based authentication with 4-hour timeout:

```php
use App\Core\Auth;

Auth::login($user);   // Store user in session
Auth::check();         // Returns bool
Auth::user();          // Returns current user or null
Auth::logout();        // End session
```

## License

MIT License - feel free to use this in your projects.
