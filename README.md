# YGS Core Services

WordPress-style hooks and plugin management system for Laravel applications.

## Installation

```bash
composer require ygs/core-services
```

## Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --tag=core-services-config
```

## Usage

### Hook Manager

The Hook Manager provides WordPress-style action and filter hooks for extensible code.

#### Actions

Actions are hooks that execute callbacks without returning values:

```php
use Ygs\CoreServices\Facades\HookManager;

// Register an action hook
HookManager::addAction('user.registered', function($user) {
    \Log::info("User registered: {$user->email}");
}, 10); // priority (optional, default 10)

// Trigger the action
HookManager::doAction('user.registered', $user);
```

#### Filters

Filters are hooks that modify values:

```php
// Register a filter hook
HookManager::addFilter('product.price', function($price, $product) {
    // Apply discount
    return $price * 0.9;
}, 10);

// Apply the filter
$finalPrice = HookManager::applyFilter('product.price', $originalPrice, $product);
```

#### Priority

Lower priority numbers execute first (WordPress style):

```php
HookManager::addAction('order.created', $callback1, 5);  // Executes first
HookManager::addAction('order.created', $callback2, 10); // Executes second
HookManager::addAction('order.created', $callback3, 15); // Executes third
```

### Panel Extension Hooks

Helper for managing Lunar Panel extensions:

```php
use Ygs\CoreServices\Hooks\PanelExtensionHooks;

// Add pages
PanelExtensionHooks::addPages(function($pages) {
    $pages[] = MyCustomPage::class;
    return $pages;
});

// Add resources
PanelExtensionHooks::addResources(function($resources) {
    $resources[] = MyCustomResource::class;
    return $resources;
});

// In your PanelExtensionServiceProvider
$pages = PanelExtensionHooks::getPages($existingPages);
$resources = PanelExtensionHooks::getResources($existingResources);
```

## Migration from CheckoutEventService

If you're migrating from the old `CheckoutEventService`, create a compatibility wrapper:

```php
namespace App\Services;

use Ygs\CoreServices\Facades\HookManager;

class CheckoutEventService
{
    public const EVENTS = [
        'order.created' => 'Triggered when an order is created',
        // ... other events
    ];

    public static function addHook(string $event, callable $callback, int $priority = 10, int $acceptedArgs = 1): bool
    {
        if (!array_key_exists($event, self::EVENTS)) {
            \Log::warning("Attempted to register hook for unknown event: {$event}");
            return false;
        }

        return HookManager::addAction($event, $callback, $priority, $acceptedArgs);
    }

    public static function doAction(string $event, array $args = []): array
    {
        return HookManager::doAction($event, ...$args);
    }

    public static function applyFilter(string $event, $value, array $args = []): mixed
    {
        return HookManager::applyFilter($event, $value, ...$args);
    }

    // ... other compatibility methods
}
```

## License

MIT

