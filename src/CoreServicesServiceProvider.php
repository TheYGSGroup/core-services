<?php

namespace Ygs\CoreServices;

use Illuminate\Support\ServiceProvider;
use Ygs\CoreServices\Hooks\HookManager;
use Ygs\CoreServices\Plugins\PluginManager;
use Ygs\CoreServices\Plugins\MigrationRunner;

class CoreServicesServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // HookManager uses static methods, so we just register the class name
        // The facade will proxy static calls directly to the class
        $this->app->singleton('ygs.hook-manager', function ($app) {
            return HookManager::class;
        });

        // Register PluginManager
        $this->app->singleton(PluginManager::class, function ($app) {
            $pluginsPath = config('core-services.plugins_path', base_path('plugins'));
            return new PluginManager($pluginsPath);
        });

        // Register MigrationRunner
        $this->app->singleton(MigrationRunner::class);

        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                \Ygs\CoreServices\Console\Commands\PluginInstallCommand::class,
                \Ygs\CoreServices\Console\Commands\PluginActivateCommand::class,
                \Ygs\CoreServices\Console\Commands\PluginDeactivateCommand::class,
                \Ygs\CoreServices\Console\Commands\PluginListCommand::class,
                \Ygs\CoreServices\Console\Commands\PluginUpdateCheckCommand::class,
                \Ygs\CoreServices\Console\Commands\PluginUpdateCommand::class,
            ]);
        }

        // Merge configuration
        $this->mergeConfigFrom(
            __DIR__ . '/Config/core-services.php',
            'core-services'
        );
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Auto-load migrations from the package (runs automatically with artisan migrate)
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // Publish configuration file
        $this->publishes([
            __DIR__ . '/Config/core-services.php' => config_path('core-services.php'),
        ], 'core-services-config');

        // Publish migrations (optional - for customization)
        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'core-services-migrations');

        // Load and activate plugins on boot
        $this->loadActivePlugins();
    }

    /**
     * Load and activate all active plugins
     *
     * @return void
     */
    protected function loadActivePlugins(): void
    {
        try {
            $pluginManager = $this->app->make(PluginManager::class);
            $activePlugins = $pluginManager->getActivePlugins();

            foreach ($activePlugins as $plugin) {
                try {
                    // Use PluginManager's registerPluginAutoloader method for consistency
                    $pluginManager = $this->app->make(PluginManager::class);
                    
                    // Get the reflection to access protected method
                    $reflection = new \ReflectionClass($pluginManager);
                    $registerMethod = $reflection->getMethod('registerPluginAutoloader');
                    $registerMethod->setAccessible(true);
                    
                    // Register autoloader using PluginManager's method
                    $registerMethod->invoke($pluginManager, $plugin->name, $plugin->rootPath);
                    
                    // Try to require the plugin file directly if class still doesn't exist
                    // This handles cases where the autoloader might not pick up the class immediately
                    $pluginMetadata = $plugin->metadata ?? [];
                    $mainClass = $pluginMetadata['main_class'] ?? null;
                    
                    if ($mainClass && !class_exists($mainClass, false)) {
                        // Try to find and require ONLY the Plugin class file directly
                        // IMPORTANT: Use class_exists with false to avoid triggering autoloader
                        // which might load ServiceProvider
                        $parts = explode('\\', $mainClass);
                        $className = array_pop($parts);
                        $pluginFile = $plugin->rootPath . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . $className . '.php';
                        
                        if (file_exists($pluginFile)) {
                            // Only require if class doesn't exist
                            if (!class_exists($mainClass, false)) {
                                require_once $pluginFile;
                            }
                        } else {
                            // Try alternative path structure
                            $pluginFile = $plugin->rootPath . DIRECTORY_SEPARATOR . $className . '.php';
                            if (file_exists($pluginFile) && !class_exists($mainClass, false)) {
                                require_once $pluginFile;
                            }
                        }
                        
                        // Check again after direct require (without autoloading)
                        if (!class_exists($mainClass, false)) {
                            \Log::error("Plugin class still not found after direct require: {$mainClass}", [
                                'plugin_path' => $plugin->rootPath,
                                'expected_file' => $plugin->rootPath . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . $className . '.php'
                            ]);
                            continue; // Skip this plugin
                        }
                    }

                    // Register service provider if available
                    // IMPORTANT: Get service provider from metadata, NOT from getInstance()
                    // Don't try to explicitly load ServiceProvider - let it load naturally via autoloader
                    try {
                        // Get service provider class name from metadata instead of getInstance()
                        $serviceProvider = $plugin->metadata['service_provider'] ?? null;
                        
                        if ($serviceProvider) {
                            // Check if service provider is already registered
                            $registeredProviders = $this->app->getLoadedProviders();
                            if (!isset($registeredProviders[$serviceProvider])) {
                                // Now that ServiceProviders are fixed (using fully qualified names),
                                // we can safely try to load and register them
                                // Check if class is already declared (loaded) without triggering autoloading
                                $declaredClasses = get_declared_classes();
                                $alreadyDeclared = in_array($serviceProvider, $declaredClasses);
                                $existsWithoutAutoload = class_exists($serviceProvider, false);
                                
                                if ($alreadyDeclared || $existsWithoutAutoload) {
                                    // Class is already loaded - safe to register
                                    try {
                                        $this->app->register($serviceProvider);
                                        \Log::info("Registered service provider for plugin: {$plugin->name}", [
                                            'service_provider' => $serviceProvider
                                        ]);
                                    } catch (\Throwable $e) {
                                        \Log::error("Failed to register service provider for plugin: {$plugin->name}", [
                                            'service_provider' => $serviceProvider,
                                            'error' => $e->getMessage()
                                        ]);
                                    }
                                } else {
                                    // Class not loaded yet - try to load it via autoloader
                                    // This is now safe because ServiceProviders use fully qualified names
                                    try {
                                        // Trigger autoloading - this should work now
                                        if (class_exists($serviceProvider)) {
                                            $this->app->register($serviceProvider);
                                            \Log::info("Registered service provider for plugin: {$plugin->name} (autoloaded)", [
                                                'service_provider' => $serviceProvider
                                            ]);
                                        } else {
                                            \Log::warning("Service provider class not found for plugin: {$plugin->name}", [
                                                'service_provider' => $serviceProvider
                                            ]);
                                        }
                                    } catch (\Throwable $e) {
                                        \Log::error("Failed to load/register service provider for plugin: {$plugin->name}", [
                                            'service_provider' => $serviceProvider,
                                            'error' => $e->getMessage()
                                        ]);
                                    }
                                }
                            }
                        }
                    } catch (\Exception $e) {
                        \Log::error("Failed to register service provider for {$plugin->name}: " . $e->getMessage());
                    }
                } catch (\Exception $e) {
                    \Log::error("Failed to load plugin {$plugin->name}: " . $e->getMessage(), [
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            }
        } catch (\Exception $e) {
            // Database might not be ready yet, or plugins table doesn't exist
            // This is okay during initial setup
            \Log::debug("Could not load plugins: " . $e->getMessage());
        }
    }
}

