# Copilot Instructions — Simple API

## Architecture Overview

Custom PHP micro-framework (no Laravel/Symfony). The request lifecycle is:

```
index.php -> bootstrap.php -> app.php (App class) -> routes.php -> Controller -> Service -> Repository -> Model -> Database
```

- **index.php** — Entry point: CORS, raw JSON parsing into `$_POST`, custom autoloader, route dispatch
- **bootstrap.php** — Loads Composer, parses `.env`, defines `DEBUG` constant
- **app.php** — `App` class: stores routes, resolves URL params (`{id}` syntax), dispatches to controller
- **routes.php** — Register routes: `$app->get("users/{id}", [UserController::class, "show"])`

## Layered Architecture

| Layer          | Folder             | Responsibility                                                    |
|----------------|--------------------|-----------------------------------------------------------------|
| **Controller** | `app/Controllers/` | HTTP concerns: validate input, call service, return response     |
| **Service**    | `app/Services/`    | Business logic: rules, transformations, password hashing         |
| **Repository** | `app/Repository/`  | Data access: SQL queries via `Database` singleton                |
| **Model**      | `app/Models/`      | Table mirror: defines table name, primary key, fillable columns  |

## Adding a New Route / Controller

1. Register in routes.php: `$app->post("resource/action", [ResourceController::class, "methodName"])`
   - Short class names auto-resolve to `App\Controllers\` — no full namespace needed in routes
2. Create `app/Models/ResourceModel.php` — table mirror (table name, primary key, fillable columns)
3. Create `app/Repository/ResourceRepository.php` — SQL queries using Model + Database singleton
4. Create `app/Services/ResourceService.php` — business logic using Repository
5. Create `app/Controllers/ResourceController.php` extending `Controller` — uses Service
6. Controller method signature: `public function methodName(Request $request, Response $response)`

## Response Helpers (`App\Core\Response`)

Always return one of these from controller methods — never echo directly:

```php
return $response->success($data, "Message");          // 200 {success,message,data,timestamp}
return $response->created($data, "Created");           // 201
return $response->error("Message", 400);               // generic error
return $response->validationError($errors);            // 422
return $response->notFound("Not found");               // 404
return $response->unauthorized("Unauthorized");        // 401
```

## Service / Repository / Model Pattern

**Flow:** Controller -> Service -> Repository -> Model -> `Database` singleton

- Models (`app/Models/`) are table mirrors: define `$table`, `$primaryKey`, `$fillable` — no SQL
- Repositories (`app/Repository/`) own raw SQL via `Database::getInstance()->select()`, `insert()`, etc.
- Services (`app/Services/`) hold business logic (duplicate checks, password hashing, stripping sensitive fields)
- All queries must soft-delete aware: always include `AND deleted_at IS NULL`

## Database (`App\Core\Database`)

MySQL/PostgreSQL singleton via PDO. Key methods:

```php
$db = Database::getInstance();
$db->select("SELECT * FROM users WHERE id = :id", ['id' => $id]); // returns array of rows
$db->insert("INSERT INTO ...", $values);   // returns last insert ID
$db->update("UPDATE ...", $values);        // returns affected rows
$db->delete("DELETE FROM ...", $values);   // returns affected rows
$db->execute("CREATE TABLE ...");          // DDL / no return value
```

## Validation

Uses `respect/validation`. Pattern from UserController:

```php
use Respect\Validation\Validator as v;
$validator = v::key('user_email', v::email())->key('user_password', v::stringType()->length(6, null));
try {
    $validator->assert($params);
} catch (NestedValidationException $e) {
    return $response->validationError($e->getMessages());
}
```

## Authentication (`App\Core\Auth`)

Session-based, 4-hour timeout. Static API:

```php
Auth::login($user);   // stores user in session
Auth::check();         // returns bool
Auth::user();          // returns session user or null
Auth::logout();
```

## Developer Workflows

```bash
# Install dependencies
composer install

# Run migrations
php migrate.php migrate
php migrate.php rollback [n]
php migrate.php status
php migrate.php make migration_name

# Run seeds
php seed.php seed
php seed.php run seeder_name
php seed.php status
php seed.php reset
```

## Migration / Seed File Structure

**Migration** (`database/migrations/YYYY_MM_DD_description.php`):
```php
return [
    'up'   => function($db) { $db->execute("CREATE TABLE ..."); },
    'down' => function($db) { $db->execute("DROP TABLE IF EXISTS ..."); },
];
```

**Seeder** (`database/seeds/NNN_description.php`, prefix controls run order):
```php
return function($db) {
    $db->insert("INSERT INTO ...", ['field' => 'value']);
};
```

## Environment & Configuration

`.env` at project root (copy from `.env.example`). Required keys:

```
APP_DEBUG=true|false
DB_CONNECTION=mysql|postgresql
DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD
```

CORS allowed origins are configured in `index.php`.
