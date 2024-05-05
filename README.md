# Laravel Evolver

Evolver is a Laravel package designed to manage installation and upgrade sequences through a series of actions. It simplifies the deployment and upgrade processes by encapsulating them into actionable steps defined within your configuration file.

## Installation

To install the package, use Composer:

```shell
composer require ezappslab/laravel-evolver
```

After installing the package, you must execute the following Artisan command to copy the necessary configuration file to your project:

```shell
php artisan evolver:install
```

This command copies the evolver.php configuration file into your Laravel config directory.

## Configuration

Actions are defined in the evolver.php configuration file. Each action is a class that implements the Actionable interface, which requires an execute method where the action's task is defined.

Here's a basic example of an action:

```php
namespace App\Actions;

use Infinity\Evolver\Contracts\Actionable;

class UpdateDatabaseSchema implements Actionable
{
    public function execute()
    {
        // Logic to update the database schema
    }
}
```

Add your actions to the evolver.php file as follows:

```php
return [
    'install' => [
        App\Actions\InitializeDatabase::class,
        App\Actions\SeedDatabase::class,
    ],
    'upgrade' => [
        App\Actions\UpdateDatabaseSchema::class,
    ],
];
```

## Usage

The package provides two commands to execute the defined actions:

Installation command execute all installation actions defined in the configuration file:

```shell
php artisan app:install
```

Upgrade command execute all upgrade actions defined in the configuration file:

```shell
php artisan app:upgrade
```

These commands will run the respective actions in the order they are defined in the evolver.php configuration file.

## Utilities

When installing or upgrading the application, it is crucial to detect the version using the Versioning helper.

```php
use Infinity\Evolver\Facades\Versioning;

Versioning::isEqual(string $version)
Versioning::isNotEqual(string $version)
Versioning::isLessThan(string $version)
Versioning::isLessThanOrEqual(string $version)
Versioning::isGreaterThan(string $version)
Versioning::isGreaterThanOrEqual(string $version)
```

To simplify usage, register the Facade in the Facades array:

```php
'Versioning' => Infinity\Evolver\Facades\Versioning::class
```

## Contributing

Contributions to the Evolver package are welcome. Please ensure that any pull requests are compliant with Laravel's standards and are accompanied by the necessary tests.

## License

This package is open-sourced software licensed under the MIT license.
