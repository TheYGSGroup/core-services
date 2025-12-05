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
                    
                    if ($mainClass && !class_exists($mainClass)) {
                        // Try to find and require the file directly
                        $parts = explode('\\', $mainClass);
                        $className = array_pop($parts);
                        $pluginFile = $plugin->rootPath . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . $className . '.php';
                        
                        if (file_exists($pluginFile)) {
                            require_once $pluginFile;
                        } else {
                            // Try alternative path structure
                            $pluginFile = $plugin->rootPath . DIRECTORY_SEPARATOR . $className . '.php';
                            if (file_exists($pluginFile)) {
                                require_once $pluginFile;
                            }
                        }
                        
                        // Check again after direct require
                        if (!class_exists($mainClass)) {
                            \Log::error("Plugin class still not found after direct require: {$mainClass}", [
                                'plugin_path' => $plugin->rootPath,
                                'expected_file' => $plugin->rootPath . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . $className . '.php'
                            ]);
                            continue; // Skip this plugin
                        }
                    }

                    // Register service provider if available
                    $instance = $plugin->getInstance();
                    $serviceProvider = $instance->getServiceProvider();
                    
                    if ($serviceProvider && class_exists($serviceProvider)) {
                        $this->app->register($serviceProvider);
                        \Log::info("Registered service provider for plugin: {$plugin->name}", [
                            'service_provider' => $serviceProvider
                        ]);
                    } else {
                        \Log::warning("Service provider not found or not registered for plugin: {$plugin->name}", [
                            'service_provider' => $serviceProvider,
                            'class_exists' => $serviceProvider ? class_exists($serviceProvider) : false
                        ]);
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

