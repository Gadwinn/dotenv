# Dotenv

A lightweight, immutable PHP dotenv parser. Loads environment variables from `.env` files or inline strings into `$_ENV` and optionally `putenv()`, with support for cascading file loading, glob patterns, variable interpolation, and multiline values.

## Tech Stack

- **PHP** `>=8.3`
- No runtime dependencies

## Installation

```bash
composer require gadwinn/dotenv
```

## Usage

### Basic file loading

```php
use G\Dotenv\Dotenv;

$dotenv = (new Dotenv())->load('.env');

$dotenv->get('APP_ENV');               // 'production'
$dotenv->get('MISSING_KEY', 'default'); // 'default'
$dotenv->has('APP_ENV');               // true
$dotenv->getValues();                  // ['APP_ENV' => 'production', ...]
```

`load()` is **immutable** - it returns a new instance and leaves the original unchanged.

---

### Cascading file loading

By default (`OPT_CASCADE_FILES = true`), every file passed to `load()` is merged in order. Pass multiple paths to layer overrides:

```php
$dotenv = (new Dotenv())->load(
    '.env',
    '.env.local',
    '.env.${APP_ENV}',       // ${APP_ENV} is resolved at load time
    '.env.${APP_ENV}.local',
);
```

Use `DEFAULT_ENV_FILES` to apply the standard cascade pattern:

```php
$dotenv = (new Dotenv())->load(...Dotenv::DEFAULT_ENV_FILES);
// Loads: .env, .env.local, .env.${APP_ENV}, .env.${APP_ENV}.local
```

Stop after the first file found by disabling cascade:

```php
$dotenv = (new Dotenv([Dotenv::OPT_CASCADE_FILES => false]))->load(
    '.env.local',
    '.env',
);
```

---

### Glob patterns

`load()` accepts glob patterns, including brace expansion:

```php
$dotenv = (new Dotenv())->load('/etc/myapp/*.env');
$dotenv = (new Dotenv())->load('/config/.env.{prod,staging}');
```

---

### Inline strings

Pass raw `KEY=VALUE` content directly - no file required:

```php
$dotenv = (new Dotenv())->load('APP_ENV=production');
$dotenv = (new Dotenv())->load("DB_HOST=127.0.0.1\nDB_PORT=5432");
```

---

### Variable interpolation

`${VAR}` placeholders are resolved against `getenv()`, `$_ENV`, and already-loaded keys (in that order), in file paths and in values, whether they come from a file or an inline string. An unknown variable is left as is. A single-quoted value is literal, as in a shell:

```dotenv
DB_HOST=localhost
DB_URL=mysql://${DB_HOST}/app   # mysql://localhost/app
TEMPLATE='${DB_HOST}'            # ${DB_HOST}
```

In file paths:

```php
// .env.${APP_ENV} becomes .env.production when APP_ENV=production
$dotenv = (new Dotenv())->load('.env', '.env.${APP_ENV}');
```

---

### Persisting to `$_ENV` / `putenv()`

`load()` does **not** populate `$_ENV` automatically. Call `store()` to persist:

```php
$dotenv = (new Dotenv())->load('.env');
$dotenv->store();

$_ENV['APP_ENV'];   // now accessible
getenv('APP_ENV');  // still empty (putenv disabled by default)
```

Enable `putenv()` writing with `OPT_UNSAFE`:

```php
$dotenv = (new Dotenv([Dotenv::OPT_UNSAFE => true]))->load('.env');
$dotenv->store();

getenv('APP_ENV');  // now accessible
```

> **Note:** `OPT_UNSAFE` is opt-in because `putenv()` is process-global and not thread-safe.

---

### Immutable mutations

Add or remove variables without altering the loaded instance:

```php
$dotenv = (new Dotenv())->load('.env');

$extended  = $dotenv->withValue('FEATURE_FLAG', 'true');
$reduced   = $dotenv->withoutValue('LEGACY_KEY');

$dotenv->has('FEATURE_FLAG');   // false - original unchanged
$extended->has('FEATURE_FLAG'); // true
$reduced->has('LEGACY_KEY');    // false
```

`withValue()` throws `\RuntimeException` if the key is not a valid identifier (`[a-zA-Z_][a-zA-Z0-9_]*`).

---

### Strict file resolution

By default, missing files are silently skipped. Enable strict mode to throw on missing paths:

```php
$dotenv = (new Dotenv([Dotenv::OPT_STRICT_FILES => true]))->load('.env.missing');
// throws RuntimeException: File not found: .env.missing
```

---

### Supported `.env` syntax

```dotenv
# Unquoted
APP_ENV=production

# Quoted (single or double)
APP_SECRET='s3cr3t'
DB_DSN="mysql:host=127.0.0.1;dbname=app"

# Value with equals sign
JDBC_URL=jdbc:mysql://host:3306/db?useSSL=false

# Inline comment: a # at the start of the value, or after a space or a closing quote
APP_NAME=MyApp # ignored
PASSWORD=abc#123 # the first # belongs to the value: abc#123

# export keyword
export LEGACY_VAR=value

# Multiline (triple-quoted)
PRIVATE_KEY="""
-----BEGIN RSA PRIVATE KEY-----
MIIEo...
-----END RSA PRIVATE KEY-----
"""

# Empty value
OPTIONAL_VAR=
```

---

## API Reference

### `__construct(array $options = [])`

| Option | Type | Default | Description |
|---|---|---|---|
| `Dotenv::OPT_UNSAFE` | `bool` | `false` | Enable `putenv()` writes on `store()` |
| `Dotenv::OPT_CASCADE_FILES` | `bool` | `true` | Merge all matched files; `false` stops after the first |
| `Dotenv::OPT_STRICT_FILES` | `bool` | `false` | Throw `RuntimeException` on missing file paths |

### Public methods

| Method | Return | Description |
|---|---|---|
| `load(string ...$string)` | `DotenvInterface` | Load from file path(s), glob pattern(s), or inline string(s) |
| `get(string $key, string $default = '')` | `string` | Read a variable, with optional fallback |
| `has(string $key)` | `bool` | Check existence of a variable |
| `getValues()` | `array<string,string>` | Return all loaded variables |
| `withValue(string $key, string $value)` | `DotenvInterface` | Return new instance with an added/overridden variable |
| `withoutValue(string $key)` | `DotenvInterface` | Return new instance with a variable removed |
| `store()` | `bool` | Persist all variables to `$_ENV` (and `putenv()` if `OPT_UNSAFE`) |

Files with Windows line endings (CRLF) are supported. Quoted values keep their leading and trailing spaces. Escape sequences (`\n`, `\"`) are not interpreted.

## Development

```bash
composer install
composer test-all
```

## License

MIT
