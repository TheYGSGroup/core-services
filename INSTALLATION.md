# Installation Instructions

## For Local Development (Current Setup)

The package is configured as a path repository in `order-platform/composer.json`:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "../core-services"
        }
    ]
}
```

To install:

```bash
cd order-platform
composer update ygs/core-services --ignore-platform-req=ext-bcmath
composer dump-autoload
```

## For Production (After GitHub Push)

Update `order-platform/composer.json` to use VCS repository:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "git@github.com:TheYGSGroup/core-services.git"
        }
    ],
    "require": {
        "ygs/core-services": "^1.0.0"
    }
}
```

Then:

```bash
composer update ygs/core-services
```

## Package Auto-Discovery

The package automatically registers its service provider via Laravel's package discovery system. No manual registration needed in `config/app.php`.

The `CoreServicesServiceProvider` will:
- Register the HookManager service
- Merge configuration files
- Make the HookManager facade available

## Verification

After installation, verify the package is loaded:

```bash
php artisan tinker
>>> Ygs\CoreServices\Facades\HookManager::class
=> "Ygs\CoreServices\Facades\HookManager"

>>> Ygs\CoreServices\Hooks\PanelExtensionHooks::class
=> "Ygs\CoreServices\Hooks\PanelExtensionHooks"
```

