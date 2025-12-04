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

// Add navigation groups
PanelExtensionHooks::addNavigationGroups(function($groups) {
    $groups[] = 'My Custom Group';
    // Or reorder existing groups
    // Move 'Settings' to the end
    $groups = array_diff($groups, ['Settings']);
    $groups[] = 'Settings';
    return $groups;
});

// In your PanelExtensionServiceProvider
$pages = PanelExtensionHooks::getPages($existingPages);
$resources = PanelExtensionHooks::getResources($existingResources);
$groups = PanelExtensionHooks::getNavigationGroups($existingGroups);
```

### Navigation Groups

Navigation groups can be modified via hooks. Groups are ordered by their position in the array (first = top of menu):

```php
// Add a new group
PanelExtensionHooks::addNavigationGroups(function($groups) {
    // Add new group at the end
    $groups[] = 'My Plugin Group';
    return $groups;
});

// Reorder groups (e.g., move 'Settings' to the top)
PanelExtensionHooks::addNavigationGroups(function($groups) {
    // Remove from current position
    $groups = array_values(array_diff($groups, ['Settings']));
    // Add at the beginning
    array_unshift($groups, 'Settings');
    return $groups;
}, 5); // Priority 5 = higher priority, executes earlier
```

## Plugin Management System

The plugin management system allows you to install, activate, and manage plugins via ZIP files.

### Installation

Run the migration to create the plugins table:

```bash
php artisan migrate
```

### Artisan Commands

```bash
# Install a plugin from a ZIP file
php artisan plugin:install /path/to/plugin.zip

# Activate a plugin
php artisan plugin:activate plugin-name

# Deactivate a plugin
php artisan plugin:deactivate plugin-name

# List all installed plugins
php artisan plugin:list
```

### Plugin Structure

Plugins must be packaged as ZIP files with the following structure:

```
plugin-name.zip
├── plugin.json          # Required: Plugin metadata
├── src/
│   ├── Plugin.php       # Required: Main plugin class (implements PluginInterface)
│   └── ServiceProvider.php  # Optional: Plugin service provider
├── database/
│   └── migrations/      # Optional: Plugin migrations
└── ...
```

### plugin.json Schema

```json
{
    "name": "plugin-slug",
    "title": "Plugin Display Name",
    "version": "1.0.0",
    "description": "Plugin description",
    "author": "Author Name",
    "main_class": "Namespace\\Plugin",
    "service_provider": "Namespace\\ServiceProvider",
    "requires": {
        "php": ">=8.2",
        "laravel": ">=11.0",
        "core": ">=1.0.0"
    }
}
```

### Creating a Plugin

1. Extend `BasePlugin` or implement `PluginInterface`:

```php
<?php

namespace MyPlugin;

use Ygs\CoreServices\Plugins\BasePlugin;

class Plugin extends BasePlugin
{
    public function activate(): void
    {
        // Plugin activation logic
        // Register hooks, create database tables, etc.
    }

    public function deactivate(): void
    {
        // Plugin deactivation logic
        // Clean up temporary data, etc.
    }
}
```

2. Register hooks in your plugin's service provider:

```php
<?php

namespace MyPlugin;

use Illuminate\Support\ServiceProvider;
use Ygs\CoreServices\Facades\HookManager;

class ServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Register payment gateway
        HookManager::addAction('payment.gateways.register', function($gateways) {
            $gateways[] = MyPaymentGateway::class;
            return $gateways;
        });

        // Register shipping modifier
        HookManager::addAction('shipping.modifiers.register', function($modifiers) {
            $modifiers->add(MyShippingModifier::class);
        });
    }
}
```

3. Package as ZIP and install:

```bash
zip -r my-plugin.zip .
php artisan plugin:install my-plugin.zip
php artisan plugin:activate my-plugin
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
