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
        // Publish configuration file
        $this->publishes([
            __DIR__ . '/Config/core-services.php' => config_path('core-services.php'),
        ], 'core-services-config');

        // Publish migrations
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
                    // Create a helper closure to register autoloader
                    $registerAutoloader = function($pluginName, $pluginPath) {
                        // Look for composer.json and register autoloader
                        $composerJsonPath = $pluginPath . '/composer.json';
                        if (\Illuminate\Support\Facades\File::exists($composerJsonPath)) {
                            $composerJson = json_decode(\Illuminate\Support\Facades\File::get($composerJsonPath), true);
                            
                            if (isset($composerJson['autoload']['psr-4'])) {
                                $vendorPath = $pluginPath . '/vendor';
                                $autoloadPath = $vendorPath . '/autoload.php';
                                
                                if (\Illuminate\Support\Facades\File::exists($autoloadPath)) {
                                    require_once $autoloadPath;
                                } else {
                                    // Register PSR-4 autoloading manually
                                    foreach ($composerJson['autoload']['psr-4'] as $namespace => $path) {
                                        $namespace = rtrim($namespace, '\\');
                                        $path = $pluginPath . '/' . trim($path, '/');
                                        
                                        spl_autoload_register(function ($class) use ($namespace, $path) {
                                            if (str_starts_with($class, $namespace)) {
                                                $relativeClass = substr($class, strlen($namespace));
                                                $file = $path . str_replace('\\', '/', $relativeClass) . '.php';
                                                
                                                if (file_exists($file)) {
                                                    require_once $file;
                                                }
                                            }
                                        });
                                    }
                                }
                            }
                        }

                        // Also register src/ directory as fallback
                        $srcPath = $pluginPath . '/src';
                        if (\Illuminate\Support\Facades\File::isDirectory($srcPath)) {
                            spl_autoload_register(function ($class) use ($srcPath) {
                                // Try to find class file in src directory
                                $file = $srcPath . '/' . str_replace('\\', '/', $class) . '.php';
                                if (file_exists($file)) {
                                    require_once $file;
                                }
                            });
                        }
                    };

                    // Register autoloader
                    $registerAutoloader($plugin->name, $plugin->rootPath);

                    // Register service provider if available
                    $instance = $plugin->getInstance();
                    $serviceProvider = $instance->getServiceProvider();
                    
                    if ($serviceProvider && class_exists($serviceProvider)) {
                        $this->app->register($serviceProvider);
                    }
                } catch (\Exception $e) {
                    \Log::error("Failed to load plugin {$plugin->name}: " . $e->getMessage());
                }
            }
        } catch (\Exception $e) {
            // Database might not be ready yet, or plugins table doesn't exist
            // This is okay during initial setup
            \Log::debug("Could not load plugins: " . $e->getMessage());
        }
    }
}

