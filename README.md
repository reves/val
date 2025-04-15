<h1 align="center">val</h1>

val is a PHP web framework mainly for single page applications.

#### Requirements

- PHP >= 8.3
- Composer
- Mbstring PHP Extension
- Sodium PHP Extension

#### Out of the box compatibility

- Databases: SQLite, PostgreSQL, MySQL / MariaDB
- Servers: Apache, Nginx

#### Table of contents:

- [Installation](#installation)
- [How it works](#how-it-works)
- [Conventions](#conventions)
- [CLI](#cli)
- [Configuring the server](#configuring-the-server)
- [Configuring the app and environments](#configuring-the-app-and-environments)
- [Serving the View](#serving-the-view)
- [Creating an API](#creating-an-api)
- [Migrations](#migrations)
- [Documentation](#documentation)

## Installation

#### 1. Add to `composer.json` the following script that will automatically create the CLI tool on installation.
```json
"scripts": {
    "post-update-cmd": "Val\\Console::create"
},
```
#### 2. Run the installation.
```console
composer require reves/val
```

## How it works

Usage example in `public/index.php`:

```php
<?php

require __DIR__.'/../vendor/autoload.php';

use Val\App;

App::run(function() {

    echo 'Hello, World!';

});
```

The entry point of the application is the static method `Val\App::run()`, which is called in `index.php`. This method processes two types of requests:

#### 1. API Requests
API requests are either `POST` or `GET`, and they require the `_api` parameter to be present in the request URL (e.g. `/index.php?_api=books`). When this parameter is detected, the framework will use the corresponding API class located in your application's `api/` directory (e.g. `api/Books.php`).

In this case the application acts as an API provider, responding with raw JSON data and appropriate HTTP status codes. For API client convenience on frontend, the server should have rewrite rules configured to handle clean URLs, such as `/api/books`.

#### 2. View Requests
For all non-API requests, the application serves the **View**, which is simply an anonymous function passed as the first parameter to ``App::run()``. For example, this function may return HTML of a single-page application, where routing is handled on the client-side.

## Conventions

### Root directory structure
- `api/` – Contains API classes.
- `config/` – Application and environment configuration files.
- `migrations/` – Database migration classes.
- `public/` – The directory exposed to the internet, containing the `index.php` entry point.
- `vendor/` – Dependencies managed by Composer.
- `view/` – Contains templates for the **View** function or email templates.
- ``.gitignore`` – **(!) Important:** Ensure files like `config/env.*` are added.
- ``val`` *(or ``val.bat`` on windows)* – The CLI tool used for app files creation and migration management.

### Timezone

Timezone defaults to UTC.

## CLI

The `val` file, located in the root directory of the app, represents the CLI tool. It helps with files creation and database migration management.

```console
val command [subcommand] [<argument>]
```


### Commands:

#### `val` or `val help`
  Lists all the available commands.

#### `val create`

Creates all the necessary directories and files for the app to function. Useful command especially for new projects.

#### Subcommands of `val create`:

- `val create config <name>` – Creates a specific config file. E.g. `val create config geolocation` will create the `config/geolocation.php` file. If the `<name>` is not specified, will create a config file named `config/config.php`.
  
  The `app`, `auth`, `db`, `env` or `envdev` config names are reserved and will use a built-in template for creation.

- `val create api <name>` – Creates a specific API class. E.g. `val create api Books` will create the `api/Books.php` file. If the `<name>` is not specified, will create an API class file named `api/Test.php`.

- `val create migration <name>` – Creates a new migration class. E.g. `val create migration CreateBooksTable` will create the `migrations/id_CreateBooksTable.php`. If the `<name>` is not specified, will create a migration class file named `migrations/id_NewMigration.php`.

  The migration name `CreateSessionsTable` is reserved and will use a built-in template for creation.

- `val create appkey` – Creates the `config/env.dev.php` env config file with a generated app key or, if the file already exists, regenerates the current app key in that file. This command will not change the app key in the `config/env.php` (production env config).

#### `val migrate <version> [-y/--yes]`

Runs all migrations till (including) the specified version number. E.g. `val migrate 5` will call consecutively all `up()` methods of the new migrations where `id <= 5`. If the `<version>` is not specified, will attempt to migrate to the latest available version.

(!) This command asks for confirmation [y/n] before proceeding.

#### `val rollback <version> [-y/--yes]`

Rollbacks all migrations until (excluding) the specified version number. E.g. `val rollback 3` will call consecutively all `down()` methods of the applied migrations where `id > 3`. If the `<version>` is not specified, will attempt to rollback the latest applied version.

(!) This command asks for confirmation [y/n] before proceeding.

## Configuring the server

### Apache

The `val create` command will create an `.htaccess` file in `/public` directory.

### Nginx

[TODO]

## Configuring the app and environments

### Environment

The environment configuration files are the two config files with reserved names `config/env.php` and `config/env.dev.php`. At least one of these two files is required, to run the application.

```console
val create config env
val create config envdev
```

- `env.php` – Production environment configuration file.
- `env.dev.php` – Development environment configuration file. If this file is present, it takes precedence over `env.php`, so the environment becomes of development. If this file is missing, the default environment is production.

#### Environment-dependant side effects:
- The `display_errors` ini setting is set to `false` in production environment.

### App configurations

The main app configuration files are the config files located in the `config/` folder.

Reserved config file names:

```console
val create config app
val create config auth
val create config db
```

## Serving the View

[TODO] (meanwhile, read [App class](#app-class-valapp))

## Creating an API

[TODO] (meanwhile, read [Api class](#api-class-valapi))

## Migrations

[TODO] (meanwhile, read [CLI](#cli))

# Documentation

- [App class](#app-class-valapp)
- [Api class](#api-class-valapi)
- [App-related modules](#app-related-modules)
- [Api-related modules](#api-related-modules)

### App class `Val\App`

The `App` class manages the application runtime.

#### Usage

`App::run(?\Closure $view = null, ?string $rootPath = null) : void`

The method `run()` represets the application entry point and initializes all modules, directory paths and specific response headers, then runs the API/View (depending on request type).

```php
// in index.php
App::run(function() {}, "/custom/root/dir");
```
```php
// Application entry point, both for View and API.
// Note: the anonymous function represents the View, has nothing to do with APIs.
App::run(function() {
    echo 'Hello, World!';
});
```

`App::isApiRequest() : bool`

```php
App::isApiRequest(); // === isset($_GET['_api'])
```

`App::isProd() : bool`

```php
App::isProd();
// true:  only "config/env.php" exists
// false: only "config/env.dev.php" exists
// false: both "config/env.php" and"config/env.dev.php" exist
```

`App::exit() : never`

```php
App::exit(); // closes the DB connection (if any) and terminates the script execution.
```

### Api class `Val\Api`

The `Api` class offers request/response management for application's API classes. In other words, classes in the `api/` directory should extend the `Api` class (unless a specific API class manages the request/response process in a custom way).

#### Functionality
- Setting request requirements for a specific method (endpoint):
  - the allowed request method: only POST / only GET / both (default).
  - authentication: only Authenticated users / only Unauthenticated users / both (default).
  - required field(s).
  - optional field(s).
- Validation/Pre-processing of field values – automatic calls to corresponding methods (if defined, e.g. `ApiName::validate<Fieldname>(&$value)`), based on specified required/optinal field(s).
- Response structuring:
  - preparing a common error response, by setting `setInvalid()` for each field with invalid data.
  - automatically sent `400` response, if any missing or invalid fields, after all validation methods calls. The error-related data is included in the response.
  - successful response `200`, with/without response data.
  - custom error `400...500` response.

#### Usage

`Api::__invoke()`

This method represents the default endpoint of a specific API (e.g. `example.com/api/books`).

By default, this method is empty and returns a `404` response.

```php
// e.g. in api/Books.php

use Val\Api;

Final Class Books Extends Api
{
    public function __invoke()
    {
        $this->onlyGET();
        // e.g. getting the list of books from database ...
        $list = [
            ['title' => 'Bright Days', 'author' => 'John Doe', 'year' => 2005],
            ['title' => 'The Shadows', 'author' => 'Michael Smith', 'year' => 2006],
            ['title' => 'The Last Ember', 'author' => 'Jonathan Blake', 'year' => 2008],
        ];
        // Sends a response with status "200" and JSON-encoded $list data.
        // The "return" is optional, but recommended, especially when using 
        // Api::peek() internal calls.
        return $this->success($list);
    }
}
```

GET `/api/books` - `200 OK`

```json
[
    {"title":"Bright Days","author":"John Doe","year":2005},
    {"title":"The Shadows","author":"Michael Smith","year":2006},
    {"title":"The Last Ember","author":"Jonathan Blake","year":2008}
]
```

`Api::peek(string $endpoint, array $params = []) : mixed`

This method makes an internal call to any application API in a "frontend-like" format.

```php
// e.g. in api/Books.php
Final Class Books Extends Api
{
    public function __invoke() {/*...*/}

    public function byAuthor()
    {
        $this->onlyGET()->required('author');

        $list = Api::peek('/books'); // calls Books::__invoke(), without parameters
        $author = $this->val('author');
        $result = array_filter($list, function($v) use ($author) {
            return $v['author'] === $author;
        });

        return $this->success($result);
    }
}
```
GET `/api/books/byauthor?author=John+Doe` - `200 OK`

(notice the `author` parameter, which is required)

```json
[
    {"title":"Bright Days","author":"John Doe","year":2005}
]
```
Can be used from the View function as well:
```php
// e.g. in public/index.php
Val\App::run(function() {
    echo '<pre>';
    print_r(Val\Api::peek('/books')); // prints the returned $list from __invoke()
    echo '</pre>';
});
```

`Api::onlyGET() : self`

Allows only GET requests to **this** API action method.

```php
public function byAuthor() {
    $this->onlyGET();
    // ...
}
```

`Api::onlyPOST() : self`

Allows only GET requests to **this** API action method.

```php
public function subscribe() {
    $this->onlyPOST();
    // ...
}
```

`Api::onlyAuthenticated() : self`

Allows only Authenticated users to call **this** API action method.

```php
public function update() {
    $this->onlyPOST()->onlyAuthenticated();
    // ...
}
```

`Api::onlyUnauthenticated() : self`

Allows only Unauthenticated users to call **this** API action method.

```php
public function claimFirstTimeDiscount() {
    $this->onlyPOST()->onlyUnauthenticated();
    // ...
}
```

`Api::required(string|array ...$fields) : self`

Registers the required (mandatory) fields for **this** API action method. Also, calls the corresponding validation method (if defined) for each field.

**(!) Fields with empty `""` values are NOT considered as missing, so remember to check for length (when validating), if needed.**

```php
public function list() {
    $this->onlyGET()->required('author', 'year', 'title');
    // ...
}
```

POST `/api/<api-name>/list?author=John+Doe&title=` - `400 Bad Request`

```json
{
    "missing": ["year"]
}
```

Validation:

```php
public function addComment() {
    $this->onlyPOST()
        ->onlyAuthenticated()
        ->required('name', 'age', 'email', 'message');

        $name = $this->val('name'); // after validation (trimmed value)
        $age =  $this->val('age'); // integer
        // ...
}

// e.g. validation method, which is called automatically (if defined):
// Convention: protected function validate<Fieldname>($value)
protected function validateName(&$name) // `&` means that field value will be modified
{
    $name = trim($name);
    if (!$name) return 'EMPTY_VALUE';
    if (mb_strlen($name) > 100) {
        
        return ['TOO_LONG', ['max' => 100]];

        // ... or manually:
        // $this->setInvalid('name', 'TOO_LONG', ['max' => 100]);
    }
}

// e.g. type conversion inside validation
protected function validateAge(&$age)
{
    $age = intval(trim($age));
    // ...
}
```

POST `/api/<api-name>/addComment` - `400 Bad Request`

```json
{
    "invalid": {
        "name": {
            "status": "TOO_LONG",
            "params": {"max": 100}
        }
    }
}
```

Grouping fields in arrays, to use the same validation method:

```php
public function register() {
    $this->onlyPOST()
        ->onlyUnauthenticated();
        ->required(
            'email',
            ['firstName', 'lastName'],
            'password'
        );
    // ...
}

// e.g. validation method for the grouped fields 'firstName' and 'lastName':
// Convention: <Fieldname> is the name of the first field in the grouping array
protected function validateFirstName($name) {/*...*/} // same validator for both fields 
```

`Api::optional(string|array ...$fields) : self`

Works the same way as `Api::required` does, except it doesn't list these fields as `missing` if they're not specified in the request. Also calls the corresponding validation method for each field (if defined).

```php
$this->optional('note', ['address1', 'address2']);
```

`Api::val(string $field) : mixed`

Returns the value of the specified field.

```php
public function register() {
    $this->onlyPOST()->onlyUnauthenticated()->required('email', 'password');
    $email = $this->val('email'); // getting the value after validation (if defined):
    // ...
}

protected function validatePassword($pw)
{
    // e.g. getting the value of another field inside a validation method:
    $email = $this->val('email');
    if ($pw === $email) return 'PASSWORD_MATCHES_EMAIL';
}
```

`Api::setInvalid(string $field, string $status, ?array $params = null) : self`

Manually adds to the final error response an "invalid" message for a specific field.

```php
public function register() {
    $this->onlyPOST()->onlyUnauthenticated()->required('email', 'password');

    // Manual validation:
    if (!mb_strlen($this->val('email'))) {
        $this->setInvalid('email', 'EMPTY_VALUE');
    }
    //...
}
```

`Api::success(?array $data = null) : array|bool`

```php
public function list()
{
    //...
    return $this->success($list); // status 200 and JSON-encoded $list in the response body
}
```

```php
public function addImage()
{
    //...
    return $this->success(); // status 200
}
```

`Api::error(int $code = 500, ?string $status = null) : never`

Responds with an error. This methods is also used internally in the process of fields validation.

If the requirements like `onlyPOST` or `onlyAuthenticated` are met (if any), then the following error messages will appear (if any) in the response body, in JSON format, in the following order, one at a time:

`missing` `400` --> `invalid` `400` --> `status` `<status-code>` (custom error)

```php
public function addImage()
{
    //...
    if (!$savedOnDisk) return $this->error(); // status 500 by default
    //...
}
```

With a custom status/message:

```php
public function addImage()
{
    //...
    if (!$savedInDatabase) return $this->error(500, 'CUSTOM_STATUS or a verbose message.');
    //...
}
```

POST `/api/<api-name>/addImage` - `500 Internal Server Error`

```json
{
    "status": "CUSTOM_STATUS or a verbose message."
}
```

## App-related modules
The App-related modules are used internally in the framework, also may be used in the application's APIs or in the View as well.

The modules initialized by default are: `Lang`, `CSRF`, `DB`, `Auth`, `Renderer`. Although the modules `Lang`, `DB` and `Auth` require certain configs to work.

- [Auth](#auth-valappauth)
- [Config](#config-valappconfig)
- [Cookie](#cookie-valappcookie)
- [Crypt](#crypt-valappcrypt)
- [CSRF](#csrf-valappcsrf-internal)
- [DB](#db-valappdb-and-valappdbdriver)
- [HTTP](#http-valapphttp)
- [JSON](#json-valappjson)
- [Lang](#lang-valapplang)
- [Renderer](#renderer-valapprenderer)
- [Token](#token-valapptoken)
- [UUID](#uuid-valappuuid)

### Auth `Val\App\Auth`

The Auth module allows to:
- **Authenticate the user account** - checks whether the session token is genuine and not expired/revoked.
- **Manage account's sessions** (on multiple devices).

**(!) The account creation/confirmation/management, access authorization, roles and other... will be managed by application's custom APIs which will use the framework's Auth module just as a sessions management library.**

**(!) The `accountId` is expected to be of `UUIDv7` format (use the `Val\App\UUID` module for generation).**

#### Configs `config/auth.php` (required)

- (optional) `session_lifetime_days => 365` – The session will permanently expire after this duration (in days).
  - Default: `Auth::SESSION_LIFETIME_DAYS`
- (optional) `session_max_offline_days => 7` – The session will expire if the device remains inactive for this duration (in days).
  - Default: `Auth::SESSION_MAX_OFFLINE_DAYS`
- (optional) `token_trust_seconds => 5` – Duration (in seconds) to trust the session token before re-checking in the database if the session still remains valid.
  - Default: `Auth::TOKEN_TRUST_SECONDS`
- (optional) `session_update_seconds => 60` – The "last seen" data is updated in the database no more frequently than this duration (in seconds).
  - Default: `Auth::SESSION_UPDATE_SECONDS`
- (optional) `max_active_sessions => 30` – The maximum number of active sessions per account.
  - Default: `Auth::MAX_ACTIVE_SESSIONS`

- Other configs:
  - `config/db.php` (required), and the default `CreateSessionsTable` migration applied (use the CLI).
  - `config/app.php` (required).

#### Usage

`Auth::initSession(string $accountId) : bool`

Initializes a new session for a given accountId (UUID). Returns true on success, or false on error or if too many active sessions.

```php
// e.g. in api/Account.php
public function signIn()
{
    $this->onlyPOST()
        ->onlyUnauthenticated()
        ->required('username', 'password');

    // getting the user from the database ...
    // checking password ...
    // maybe 2-Factor-Authentication ...

    return Auth::initSession($accountId); // creates a new session in database and sets the session cookie
        ? $this->respondSuccess()
        : $this->respondError();
}
```

`Auth::revokeSession(?string $id = null) : bool`

Revokes the authentication session by a given session UUID. If no parameter given, revokes the current session. Returns true on success, or false if the session is not found in the database.

```php
// e.g. in api/Account.php
public function signOut()
{
    $this->onlyPOST()
        ->onlyAuthenticated();

    return Auth::revokeSession() // deletes the session from database and removes the cookie
        ? $this->respondSuccess()
        : $this->respondError();
}
```

`Auth::revokeAllSessions() : bool`

Revokes all the sessions of the current user. Returns true on success, or false if no sessions were found in the database.

```php
// e.g. in api/Account.php
public function signOutFromAllDevices()
{
    $this->onlyPOST()
        ->onlyAuthenticated();

    return Auth::revokeAllSessions()
        ? $this->respondSuccess()
        : $this->respondError();
}
```

`Auth::revokeOtherSessions() : bool`

Revokes all the sessions of the current user, **except the current session**. Returns true on succes, or false if no other sessions were found in the database.

```php
// e.g. in api/Account.php
public function signOutFromOtherDevices()
{
    $this->onlyPOST()
        ->onlyAuthenticated();

    return Auth::revokeOtherSessions()
        ? $this->respondSuccess()
        : $this->respondError();
}
```

`Auth::removeExpiredSessions(string $accountId) : bool`

Removes all the expired sessions of a given account UUID from the database. Returns true on success, or false if no expired sessions were found in the database.

**(!) This method is automatically called on every Auth::initSession() call.**

```php
// in a cron job (e.g. for cases when the user never signed in again)
Auth::removeExpiredSessions($accountId);
```

`Auth::getAccountId() : ?string`

Returns the accountId (UUID) associated with the current session, or null if the user is unauthenticated.

```php
// e.g. in api/Account.php
public function isAuthenticated()
{
    $this->onlyGET();

    return $this->respondData([
        'isAuthenticated' => (Auth::getAccountId() !== null)
    ]);
}
```

`Auth::getSignedInAt() : ?string`

Returns the dateTime the session was initialized.

```php
if (Auth::getAccountId() !== null) {
    $dateTime = Auth::getSignedInAt() // e.g. '2025-01-01 00:00:00'
}
```

`Auth::getLastSeenAt() : ?string`

Returns the dateTime the session data updated.

`Auth::getSignedInIPAddress() : ?string`

Returns the IP address of the session initialization.

`Auth::getLastSeenIPAddress() : ?string`

Returns the IP address of the session update.

`Auth::getIPAddress() : ?string`

A helper static method that returns the client's IP address, or null if unable to determine.

### Config `Val\App\Config`

The Config module allows to get a config/env value by specifying the config name and the field name.

#### Usage

`Config::<config_name>(string $name) : mixed`

The dynamic `<config_name>` method stands for the config file name and the `$name` parameter stands for the field name inside the config file.

```php
// In config/app.php
'foo' => 'bar',

// ... then
$value = Config::app('foo'); // 'bar'
```
```php
// Get env variable (automatically chooses from which one - env.php or env.dev.php)
$value = Config::env('test');
```
```php
// In config/custom.php
return [
    'something' => Config::env('test'), // get value from the current env
    'foofoo' => Config::app('foo') . 'bar', // get value from another config
];

// ... then
$value = Config::custom('foofoo'); // 'barbar'
```

### Cookie `Val\App\Cookie`

#### Usage

`Cookie::isSet(string $name) : bool`

```php
$isSet = Cookie::isSet('cookiename');
```

`Cookie::get(string $name) : string`

```php
$value = Cookie::get('cookiename'); // may return empty string if the cookie is not set
```

`Cookie::set(string $name, string $value = '', array $options = []) : bool`

```php
$options = [ // these are also the default options values:
    'expires'   => 0,
    'path'      => '/',
    'domain'    => '',
    'secure'    => true,
    'httponly'  => true,
    'samesite'  => 'Lax'
];

$result1 = Cookie::set('name1', 'value1'); // use default options
$result2 = Cookie::set('name2', 'value2', $options);
$result3 = Cookie::set('name3', 'value3', ['httponly' => false]); // change a specific option
```

`Cookie::unset(string $name) : bool`

```php
$result = Cookie::unset('cookiename');
```

`Cookie::setForDays(string $name, string $value = '', int $days = 1, array $options = []) : bool`

```php
$result = Cookie::setForDays('cookiename', 'value'); // 1 day
$result = Cookie::setForDays('cookiename', 'value', 7, ['httponly' => false]); // 7 days, with a custom option
```

`Cookie::setForMinutes(string $name, string $value = '', int $minutes = 1, array $options = []) : bool`

`Cookie::setForSeconds(string $name, string $value = '', int $seconds = 1, array $options = []) : bool`


### Crypt `Val\App\Crypt`

#### Configs `config/app.php` (required)

- (required) `key => 'app-key'` – required for this module to work.

#### Usage

`Crypt::encrypt(?string $message) : ?string`

```php
$encrypted = Crypt::encrypt('an important message'); // may return null
```

`Crypt::decrypt(string $encodedEncryptedMessage) : ?string`

```php
$decrypted = Crypt::decrypt($encrypted); // may return null
```

### CSRF `Val\App\CSRF` (internal)

The CSRF module is automatically applied for the API methods which have `$this->onlyPOST()` set.

#### Configs `config/app.php` (required)

- (required) `key => 'app-key'` – required for encryption.

### DB `Val\App\DB` and `Val\App\DBDriver`

The DB module wraps the `PDO` interface and makes it easier to use.

#### Configs `config/db.php` (required)

- (optional, but recommended) `driver => DBDriver::MySQL` – the database driver.
  - `DBDriver::MySQL` (default) – MySQL, compatible with MariaDB.
  - `DBDriver::PostgreSQL` – PostgreSQL.
  - `DBDriver::SQLite` – SQLite.
- For SQLite driver:
  - (required) `path => App::$DIR_ROOT . '/db.sqlite3'` – Path to database.
- For MySQL or PostgreSQL driver:
  - (required) `'host' => '127.0.0.1'` – Database host.
  - (required) `'db' => 'myapp'` – Database name.
  - (required) `'user' => 'root'` – Database user.
  - (required) `'pass' => ''` – Database user password.

#### Usage

`DB::beginTransaction() : bool`

`DB::commit() : bool`

```php
DB::beginTransaction();
// ...
DB::commit();
```

`DB::rollback() : bool`

`DB::transactionIsActive() : bool`

```php
DB::beginTransaction();
// ...
if ($somethingHappened) {
    DB::rollback(); // cancels the current transaction
}
DB::transactionIsActive(); // `false`
// ...
DB::commit(); // will commit only if the transacrion is still active, safe to use here
```

`DB::raw(string $query) : int|bool`

**(!) Data inside the query should be properly escaped.**

Executes an SQL statement with a custom query. This method cannot be used with any queries that return results. Returns the number of rows that were modified or deleted, or false on error.

```php
$count = DB::raw("DELETE FROM books");
```

`DB::lastInsertId() : string`

**(!) In case of a transaction, should be used before `DB::commit()`.**

Returns the id `string` of the last inserted row.

```php
$id = DB::lastInsertId();
```

`DB::rowCount() : int`

**(!) Not recommended to use with SELECT statements.**

Returns the number of rows affected by the last DELETE, INSERT, or UPDATE statement. 

`DB::prepare(string $query) : self`

```php
$db = DB::prepare("SELECT title FROM books"); // returns the DB instance, for convenience
```

`DB::bind(string|int $placeholder, bool|float|int|string|null $value) : self`

```php
$db = DB::prepare("SELECT title FROM books WHERE author = :author AND published = :published")
    ->bind(':author', 'John Doe')
    ->bind(':published', true);
```
```php
$db = DB::prepare("SELECT title FROM books WHERE author = ? AND published = ?")
    ->bind(1, 'John Doe')
    ->bind(2, true);
```

`DB::bindPlaceholder(bool|float|int|string|null $value) : self`

```php
$db = DB::prepare("SELECT title FROM books WHERE author = ? AND published = ?")
    ->bindPlaceholder('John Doe') // autoindex to 1
    ->bindPlaceholder(true); // autoindex to 2
```

`DB::bindMultiple(array $relations) : self`

```php
$db = DB::prepare("SELECT title FROM books WHERE author = :author AND published = :published")
    ->bindMultiple([
        ':author' => 'John Doe',
        ':published' => true,
    ]); // uses DB::bind() for each entry
```
```php
$db = DB::prepare("SELECT title FROM books WHERE author = ? AND published = ?")
    ->bindMultiple([
        1 => 'John Doe',
        2 => true,
    ]); // uses DB::bind() for each entry
```
```php
$db = DB::prepare("SELECT title FROM books WHERE author = ? AND published = ?")
    ->bindMultiple(['John Doe', true]); // uses DB::bindPlaceholder() for each entry,
                                        // when detects an array with key `0`
```

`DB::execute(?array $relations = null) : bool`

```php
DB::beginTransaction();

$result = DB::prepare("INSERT INTO books (title, author, published)
                       VALUES (:title, :author, :published)")
    ->bind('title', 'The Cool Title') // ->bind() still applicable
    ->execute([
        ':author' => 'John Doe',
        ':published' => false,
    ]); // passes optional $relations to DB::bindMultiple() and then executes

if ($result) {
    $bookId = DB::lastInsertId();
    // ...
} else {
    DB::rollback();
    // handle the error ...
}

DB::commit();
```
```php
$result = DB::prepare("DELETE FROM books WHERE title = ?")
    ->execute(['The Cool Title']);

if (DB::rowCount()) {
    // Successfully deleted...
} else {
    // No rows affected...
}
```

`DB::single(?array $relations = null) : ?array`

```php
$result = DB::prepare("SELECT title FROM books WHERE author = ? AND published = ?")
    ->single(['John Doe', true]);

if ($result) {
    $title = $result['title'];
    // ...
} else {
    // No result, or error ...
}
```

`DB::resultset(?array $relations = null) : array`

```php
$rows = DB::prepare("SELECT title FROM books WHERE author = ? AND published = ?")
    ->resultset(['John Doe', true]);

if (count($rows)) {
    // ...
} else {
    // No result
}
```

`DB::generatePlaceholders(int $count) : string`

Helper method to generate question marks to include into a query.

```php
$placeholders = DB::generatePlaceholders(4); // '?,?,?,?'
```

`DB::dateTime(?int $timestamp = null) : string`

Returns a dateTime string matching the ISO 8601 "YYYY-MM-DD hh:mm:ss" format.

```php
$dateTime = DB::dateTime(1735689600); // '2025-01-01 00:00:00'
$dateTimeNow = DB::dateTime(); // time now (UTC time zone by default)
```

`DB::close() : void`

Closes the database connection. The framework closes the connection automatically on API response, so it's not mandatory to use this method.


### HTTP `Val\App\HTTP`

#### Usage

`HTTP::get(string $url, array $parameters = []) : ?array`

```php
$url = 'https://api.example.com/books'; // do not attach "?params"
$params = [
    'id'   => 123,
    'sort' => 'year'
]

$result = HTTP::get($url, $params); // tries to JSON decode the response, may return null
```

`HTTP::post(string $url, array $parameters = []) : ?array`

```php
$url = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
$params = [
    'secret'   => 'very-secret',
    'response' => 'user-response',
    'remoteip' => Auth::getIPAddress()
];

$result = HTTP::post($url, $params); // tries to JSON decode the response, may return null
```


### JSON `Val\App\JSON`

#### Usage

`JSON::encode(array $data) : ?string`

```php
$data = [
    'name' => "John Doe",
    'age'  => 40
];

$json = JSON::encode($data); // {"name":"John Doe","age":40}
```

`JSON::decode(?string $json) : ?array`

```php
$data = JSON::decode($json); // may return null in case of error
```


### Lang `Val\App\Lang`

The Lang module detects the user preferred language, or sets a specific language for the user. Optionally, manages the language code in the URL path.

Language code format: `<ISO 639 language code>[-<ISO 3166-1 region code>]`

#### Configs `config/app.php` (optional)

- (optional) `languages => ['fr', 'en', ...]` – a list of supported languages (e.g. if not specified in the list, the detected `en-US` will fallback to `en`).
- (optional) `language_in_url => true|false` – whether to manage the language code in the URL path.

#### Detection precedence

1. From `lang` cookie (which was set previously, if it's not the first user's request).
2. From 'Accept-Language' header.
3. Default supported language (the first one in the `Config::app('languages')` list), if the list is set.

#### Usage

`Lang::get() : ?string`

Returns the detected language code, or null if the language couldn't be detected.

```php
$lang = Lang::get(); // 'en'
```

`Lang::set(string $code) : bool`

Returns false, if the language code has an invalid format or an error occurred while setting the `lang` cookie, otherwise true.

```php
// Unsets the language, or if the supported languages list is specified, sets 
// to the first one in the list.
$result = Lang::set('');
$lang = Lang::get(); // 'fr'

// Set the language to "English (United States)".
$result = Lang::set('en-US');
```



### Renderer `Val\App\Renderer`

Renderer module allows to:

- Load a template file with a custom extenstion (.tpl, .html, ...).
- Minify the loaded template by removing the following characters: `[\r\n\t] 1+`, `<space> 2+`, `HTML comments`
- Insert other template's content, e.g. `{@header/nav.html}`.
- Bind data to placeholders, e.g. `{title}`, `{content}`.
- Reveal blocks (the blocks are removed from the result if not "revealed"), e.g. `[in_stock]Product in stock![/in_stock]`.

For convenience, static methods of the `Renderer` class return an instance of this class (singleton), so the "template-processing" methods can be further chained using the `->` operator.

#### Example templates

```html
<!-- view/main.tpl -->
{@subdir/greeting.tpl}
{@reference-to-an-inexisting-template.tpl}

[reveal_me]This will be shown.[/reveal_me]
[block_name]This will be removed.[/block_name]

Good to know:
- Unregistered {binds} are not removed.
- [wrong] A block must have the same start and end tag. [/block]
```

```html
<!-- view/subdir/greeting.tpl -->
Hello, {greeting}!
```
#### Usage

`Renderer::setPath(string $directoryPath) : self`

```php
// Example setting the path to a custom templates directory.
// By default, the path is `App::$DIR_VIEW` (meaning the "view/" directory).
Renderer::setPath(App::$DIR_ROOT . '/mytemplates');
```

`Renderer::load(string $file, bool $minify = true) : self`

```php
// In public/index.php
App::run(function() {
    $content = Renderer::load('main.tpl', false)->getContent();
    echo $content;
}
```
Result:
```html
<!-- view/main.tpl -->
Hello, {greeting}!
{@reference-to-an-inexisting-template.tpl}




Good to know:
- Unregistered {binds} are not removed.
- [wrong] A block must have the same start and end tag. [/block]
```

`Renderer::bind(string $binding, string $value = '') : self`

```php
$content = Renderer::load('main.tpl')
    ->bind('greeting', 'World')
    ->getContent();
```
```html
...
Hello, World!
...
- Unregistered {binds} are not removed.
...
```

`Renderer::bindMultiple(array $relations) : self`

```php
$content = Renderer::load('main.tpl')
    ->bindMultiple([
        'greeting' => 'World',
        'binds' => 'Binds',
    ])
    ->getContent();
```

`Renderer::reveal(string $block) : self`

```php
$content = Renderer::load('main.tpl')
    ->reveal('reveal_me')
    ->getContent();
```
```html
...
This will be shown.
...
- [wrong] A block must have the same start and end tag. [/block]
```

`Renderer::revealMultiple(array $blocks) : self`

```php
$content = Renderer::load('main.tpl')
    ->revealMultiple([
        'reveal_me',
        'block_name'
    ])
    ->getContent();
```

`Renderer::getContent() : string`

Returns the rendered content of the loaded template.
```php
$content1 = Renderer::load('index.tpl')->getContent();
$content2 = Renderer::load('email.tpl')->getContent();
```

### Token `Val\App\Token`

Token module helps to manage custom tokens which represent some data encoded in JSON format and then  encrypted by using the application key. Useful for encrypting and storing custom data in the cookies or local storage.

#### Configs `config/app.php` (required)

- (required) `key => 'app-key'` – required for encryption.

#### Usage

`Token::create(array $data) : ?string`

```php
$testData = [
    'testId' => 2,
    'score'  => 94,
    'username' => 'john_doe',
    'testPassedAt' => DB::dateTime()
];

$token = Token::create($testData);

// Storing the token
// ...
```

`Token::extract(string $token) : ?array`

```php
// Retrieving the token
// ...

$testData = Token::extract($token);
```

`Token::expired(string $createdAt, int $timeToLive, string $timeScale) : bool`

Checks if a token has expired based on creation time and time to live (TTL). The time scale for TTL must be specified using one of the class constants: `Token::TIME_SECONDS`, `Token::TIME_MINUTES`, `Token::TIME_HOURS`, `Token::TIME_DAYS`.

```php
$shouldTakeTest = Token::expired($testData['testPassedAt'], 30, Token::TIME_DAYS);
```

### UUID `Val\App\UUID`

#### Usage

`UUID::generate() : ?string`

```php
// Generating a UUID Version 7 (RFC 9562).
$uuid = UUID::generate(); // may return `null`
```

## API-related modules

`Val\Api\{...}`

The API-related modules are used primarily in `api/` classes.

- [Two-Factor authentication](#twofactorauth)
- [Captcha](#captcha)
- [Mail](#mail)

### TwoFactorAuth `Val\Api\TwoFactorAuth`

#### Usage

`TwoFactorAuth::generateSecretKey() : ?string`

```php
/**
 * Generating a TOTP (Time-based one-time password) secret key for the user.
 */
$secretKey = TwoFactorAuth::generateSecretKey();

/**
 * Storing user's TOTP secret key securely in the database.
 */
$encryptedSecretKey = Crypt::encrypt($secretKey);
// [...]
```

`TwoFactorAuth::createURI(string $secretKey, string $appName, string $accountName) : string`

```php
/**
 * Generating an URI that may be further sent to the frontend and encoded into 
 * a QR code, so the user can scan it with his favorite Authenticator app.
 */
$appName = 'My App'; // your app's name, e.g. 'Example.com', 'app', ...
$accountName = 'john@doe.com'; // user's account name, e.g. 'john_doe', 'John Doe', ...
$URI = TwoFactorAuth::createURI($secretKey, $appName, $accountName);

```

`TwoFactorAuth::verify(string $secretKey, string $code) : bool`

```php
/**
 * Later on, the user enters the code generated by his Authenticator app.
 * Getting the user's secret key from the database and veryfing the code.
 */
// [...]
$secretKey = Crypt::decrypt($encryptedSecretKey); // may return `null`
$code = $this->val('code');
$result = TwoFactorAuth::verify($secretKey, $code); // returns `true` if the code is correct
```

### Captcha `Val\Api\Captcha`

#### Usage

#### [Turnstile (Cloudflare)](https://developers.cloudflare.com/turnstile/get-started/server-side-validation/)

`Captcha::Turnstile(string $secret, string $response) : ?array`

```php
$secret = '<SECRET>'; // your Turnstile secret key
$response = '<CLIENT-RESPONSE>'; // response token from the frontend
$result = Captcha::Turnstile($secret, $response); // makes an HTTP request, may return `null`
```

#### [hCaptcha (Intuition Machines)](https://docs.hcaptcha.com/#verify-the-user-response-server-side)

`Captcha::hCaptcha(string $secret, string $response, ?string $sitekey = null) : ?array`

Example using `config/app.php` and `config/env.php` for storing the secret key.

```php
// In config/env.php ...
'hcaptcha_secret' => '<SECRET>',

// In config/app.php ...
'hcaptcha_secret' => Config::env('hcaptcha_secret'),

// Somewhere in your API's method ...
$secret = Config::app('hcaptcha_secret');
$response = $this->val('hcaptcha_response');
$sitekey = 'optional-site-key';
$result = Captcha::hCaptcha($secret, $response, $sitekey);
```

#### [reCAPTCHA (Google)](https://developers.google.com/recaptcha/docs/v3#site_verify_response)

`Captcha::reCAPTCHA(string $secret, string $response) : ?array`

```php
$result = Captcha::reCAPTCHA($secret, $response);
```

### Mail `Val\Api\Mail`

Mail module uses the standard `mail()` function that provides the very basic functionality. In a real application, it is recommended to use any popular library for email sending.

#### Usage

`Mail::send(array $options) : bool`

```php
$options = [
    'from' => ['name' => "Company name", 'address' => "email@company.com"],
    'to'   => ["User name" => "email@user1.com", "email@user2.com"],
    'cc'   => ["User name" => "email@user1.com", "email@user2.com"],
    'bcc'  => ["User name" => "email@user1.com", "email@user2.com"],
    'subject'          => "The subject",
    'messageHTML'      => "<p>Hello!</p>",
    'messagePlainText' => "Hello!"
];

$result = Mail::send($options); // returns `true` on success
```
