<?php

namespace Ygs\CoreServices\Plugins;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Ygs\CoreServices\Plugins\Contracts\PluginInterface;
use ZipArchive;

/**
 * Plugin Manager
 * 
 * Handles plugin discovery, installation, activation, and deactivation.
 */
class PluginManager
{
    /**
     * Plugins directory path
     *
     * @var string
     */
    protected string $pluginsPath;

    /**
     * Constructor
     *
     * @param string|null $pluginsPath
     */
    public function __construct(?string $pluginsPath = null)
    {
        $this->pluginsPath = $pluginsPath ?? base_path('plugins');
    }

    /**
     * Discover all installed plugins from the database
     *
     * @return array<Plugin>
     */
    public function discoverPlugins(): array
    {
        $plugins = [];

        try {
            $records = DB::table('plugins')->get();

            foreach ($records as $record) {
                $plugin = new Plugin([
                    'name' => $record->name,
                    'title' => $record->title,
                    'version' => $record->version,
                    'description' => $record->description,
                    'author' => $record->author,
                    'main_class' => $record->main_class,
                    'is_active' => (bool) $record->is_active,
                    'metadata' => json_decode($record->metadata ?? '{}', true),
                    'requirements' => json_decode($record->requirements ?? '{}', true),
                    'root_path' => $record->root_path,
                    'installed_at' => $record->installed_at,
                    'activated_at' => $record->activated_at,
                ]);

                $plugins[$plugin->name] = $plugin;
            }
        } catch (\Exception $e) {
            Log::error('Error discovering plugins: ' . $e->getMessage());
        }

        return $plugins;
    }

    /**
     * Get a specific plugin by name
     *
     * @param string $name
     * @return Plugin|null
     */
    public function getPlugin(string $name): ?Plugin
    {
        try {
            $record = DB::table('plugins')->where('name', $name)->first();

            if (!$record) {
                return null;
            }

            return new Plugin([
                'name' => $record->name,
                'title' => $record->title,
                'version' => $record->version,
                'description' => $record->description,
                'author' => $record->author,
                'main_class' => $record->main_class,
                'is_active' => (bool) $record->is_active,
                'metadata' => json_decode($record->metadata ?? '{}', true),
                'requirements' => json_decode($record->requirements ?? '{}', true),
                'root_path' => $record->root_path,
                'installed_at' => $record->installed_at,
                'activated_at' => $record->activated_at,
            ]);
        } catch (\Exception $e) {
            Log::error("Error getting plugin {$name}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get all installed plugins
     *
     * @return array<Plugin>
     */
    public function getInstalledPlugins(): array
    {
        return $this->discoverPlugins();
    }

    /**
     * Get all active plugins
     *
     * @return array<Plugin>
     */
    public function getActivePlugins(): array
    {
        return array_filter($this->discoverPlugins(), fn($plugin) => $plugin->isActive);
    }

    /**
     * Check if a plugin is active
     *
     * @param string $name
     * @return bool
     */
    public function isActive(string $name): bool
    {
        $plugin = $this->getPlugin($name);
        return $plugin && $plugin->isActive;
    }

    /**
     * Install a plugin from a ZIP file
     *
     * @param string $zipPath Path to the ZIP file
     * @return Plugin
     * @throws \Exception
     */
    public function installPlugin(string $zipPath): Plugin
    {
        if (!File::exists($zipPath)) {
            throw new \Exception("ZIP file not found: {$zipPath}");
        }

        // Create plugins directory if it doesn't exist
        if (!File::isDirectory($this->pluginsPath)) {
            File::makeDirectory($this->pluginsPath, 0755, true);
        }

        // Open ZIP archive
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new \Exception("Failed to open ZIP file: {$zipPath}");
        }

        // Extract to temporary directory first
        $tempPath = sys_get_temp_dir() . '/plugin_' . uniqid();
        $zip->extractTo($tempPath);
        $zip->close();

        // Find plugin.json in extracted files
        $pluginJsonPath = $this->findPluginJson($tempPath);
        if (!$pluginJsonPath) {
            File::deleteDirectory($tempPath);
            throw new \Exception("plugin.json not found in ZIP archive");
        }

        // Load and validate plugin.json
        $metadata = json_decode(File::get($pluginJsonPath), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            File::deleteDirectory($tempPath);
            throw new \Exception("Invalid plugin.json: " . json_last_error_msg());
        }

        $this->validatePluginMetadata($metadata);

        $pluginName = $metadata['name'];
        $pluginPath = $this->pluginsPath . DIRECTORY_SEPARATOR . $pluginName;

        // Check if plugin is already installed
        if ($this->getPlugin($pluginName)) {
            File::deleteDirectory($tempPath);
            throw new \Exception("Plugin {$pluginName} is already installed");
        }

        // Copy extracted files to plugins directory
        // Use copyDirectory instead of moveDirectory for better reliability in Docker/permission-restricted environments
        if (File::isDirectory($pluginPath)) {
            File::deleteDirectory($pluginPath);
        }
        
        if (!File::copyDirectory($tempPath, $pluginPath)) {
            File::deleteDirectory($tempPath);
            throw new \Exception("Failed to copy plugin files to {$pluginPath}");
        }
        
        // Clean up temp directory
        File::deleteDirectory($tempPath);
        
        // Verify the copy succeeded
        if (!File::isDirectory($pluginPath)) {
            throw new \Exception("Plugin directory was not created at {$pluginPath}");
        }
        
        // Verify plugin.json exists in the new location
        if (!File::exists($pluginPath . DIRECTORY_SEPARATOR . 'plugin.json')) {
            File::deleteDirectory($pluginPath);
            throw new \Exception("plugin.json not found in plugin directory after installation");
        }

        // Check dependencies
        if (!$this->satisfiesRequirements($metadata)) {
            File::deleteDirectory($pluginPath);
            throw new \Exception("Plugin requirements not satisfied");
        }

        // Create plugin instance to verify it can be loaded
        $mainClass = $metadata['main_class'] ?? '';
        if ($mainClass) {
            // Register autoloader first
            $this->registerPluginAutoloader($pluginName, $pluginPath);
            
            // Try to load the class - check if file exists and require it directly if autoloader fails
            if (!class_exists($mainClass)) {
                // Try to find and require the file directly
                $parts = explode('\\', $mainClass);
                $className = array_pop($parts);
                $pluginFile = $pluginPath . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . $className . '.php';
                
                // Use file_exists() instead of File::exists() for direct file system check
                if (file_exists($pluginFile)) {
                    require_once $pluginFile;
                } else {
                    // Try alternative path structure
                    $pluginFile = $pluginPath . DIRECTORY_SEPARATOR . $className . '.php';
                    if (file_exists($pluginFile)) {
                        require_once $pluginFile;
                    }
                }
                
                // Check again after direct require
                if (!class_exists($mainClass)) {
                    // Debug: Check if file actually exists
                    $actualFile = $pluginPath . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . $className . '.php';
                    $fileExists = file_exists($actualFile);
                    $dirExists = is_dir($pluginPath . DIRECTORY_SEPARATOR . 'src');
                    File::deleteDirectory($pluginPath);
                    throw new \Exception("Plugin class {$mainClass} not found. File exists: " . ($fileExists ? 'YES' : 'NO') . ", Dir exists: " . ($dirExists ? 'YES' : 'NO') . ", Path: {$actualFile}");
                }
            }
        }

        // Save to database
        $plugin = new Plugin([
            'name' => $pluginName,
            'title' => $metadata['title'] ?? $pluginName,
            'version' => $metadata['version'] ?? '1.0.0',
            'description' => $metadata['description'] ?? null,
            'author' => $metadata['author'] ?? null,
            'main_class' => $mainClass,
            'is_active' => false,
            'metadata' => $metadata,
            'requirements' => $metadata['requires'] ?? [],
            'root_path' => $pluginPath,
            'installed_at' => now(),
        ]);

        $this->savePlugin($plugin);

        Log::info("Plugin installed: {$pluginName} v{$plugin->version}");

        return $plugin;
    }

    /**
     * Activate a plugin
     *
     * @param string $name
     * @return bool
     * @throws \Exception
     */
    public function activatePlugin(string $name): bool
    {
        $plugin = $this->getPlugin($name);

        if (!$plugin) {
            throw new \Exception("Plugin {$name} is not installed");
        }

        if ($plugin->isActive) {
            return true; // Already active
        }

        // Check requirements again
        if (!$this->satisfiesRequirements($plugin->metadata)) {
            throw new \Exception("Plugin requirements not satisfied");
        }

        // Register autoloader for plugin
        $this->registerPluginAutoloader($name, $plugin->rootPath);

        // Get plugin instance
        $instance = $plugin->getInstance();

        // Run migrations if migration runner is available
        if (class_exists(\Ygs\CoreServices\Plugins\MigrationRunner::class)) {
            $migrationRunner = app(\Ygs\CoreServices\Plugins\MigrationRunner::class);
            $migrationRunner->runPluginMigrations($name);
        }

        // Register service provider if available
        $serviceProvider = $instance->getServiceProvider();
        if ($serviceProvider && class_exists($serviceProvider)) {
            app()->register($serviceProvider);
        }

        // Call activation hook
        $instance->activate();

        // Update database
        DB::table('plugins')
            ->where('name', $name)
            ->update([
                'is_active' => true,
                'activated_at' => now(),
                'updated_at' => now(),
            ]);

        $plugin->isActive = true;
        $plugin->activatedAt = now();

        Log::info("Plugin activated: {$name}");

        return true;
    }

    /**
     * Deactivate a plugin
     *
     * @param string $name
     * @return bool
     * @throws \Exception
     */
    public function deactivatePlugin(string $name): bool
    {
        $plugin = $this->getPlugin($name);

        if (!$plugin) {
            throw new \Exception("Plugin {$name} is not installed");
        }

        if (!$plugin->isActive) {
            return true; // Already inactive
        }

        try {
            // Get plugin instance
            $instance = $plugin->getInstance();

            // Call deactivation hook
            $instance->deactivate();
        } catch (\Exception $e) {
            Log::warning("Error during plugin deactivation: " . $e->getMessage());
        }

        // Update database
        DB::table('plugins')
            ->where('name', $name)
            ->update([
                'is_active' => false,
                'updated_at' => now(),
            ]);

        $plugin->isActive = false;

        Log::info("Plugin deactivated: {$name}");

        return true;
    }

    /**
     * Uninstall a plugin
     *
     * @param string $name
     * @return bool
     * @throws \Exception
     */
    public function uninstallPlugin(string $name): bool
    {
        $plugin = $this->getPlugin($name);

        if (!$plugin) {
            throw new \Exception("Plugin {$name} is not installed");
        }

        // Deactivate first if active
        if ($plugin->isActive) {
            $this->deactivatePlugin($name);
        }

        // Delete plugin files
        if (File::isDirectory($plugin->rootPath)) {
            File::deleteDirectory($plugin->rootPath);
        }

        // Remove from database
        DB::table('plugins')->where('name', $name)->delete();

        Log::info("Plugin uninstalled: {$name}");

        return true;
    }

    /**
     * Check for available updates for all installed plugins
     *
     * @return array Array of update information: ['plugin_name' => ['current' => '1.0.0', 'available' => '1.0.1', 'plugin' => Plugin]]
     */
    public function checkForUpdates(): array
    {
        $updates = [];
        $installedPlugins = $this->getInstalledPlugins();
        $managementSiteUrl = config('core-services.plugins.management_site_url');

        if (!$managementSiteUrl) {
            Log::warning('Plugin management site URL not configured');
            return $updates;
        }

        try {
            // Fetch all available plugins from management site
            $response = Http::timeout(10)->get("{$managementSiteUrl}/api/plugins");

            if (!$response->successful()) {
                Log::warning('Failed to fetch plugins from management site', [
                    'status' => $response->status(),
                    'url' => "{$managementSiteUrl}/api/plugins",
                ]);
                return $updates;
            }

            $availablePlugins = $response->json('data', []);

            // Check each installed plugin for updates
            foreach ($installedPlugins as $installedPlugin) {
                // Find matching plugin in available plugins by name/slug
                $availablePlugin = collect($availablePlugins)->first(function ($plugin) use ($installedPlugin) {
                    return ($plugin['name'] ?? $plugin['slug'] ?? '') === $installedPlugin->name;
                });

                if ($availablePlugin) {
                    $currentVersion = $installedPlugin->version;
                    $availableVersion = $availablePlugin['version'] ?? '0.0.0';

                    // Compare versions
                    if (version_compare($availableVersion, $currentVersion, '>')) {
                        $updates[$installedPlugin->name] = [
                            'current' => $currentVersion,
                            'available' => $availableVersion,
                            'plugin' => $installedPlugin,
                            'download_url' => $availablePlugin['download_url'] ?? null,
                            'changelog' => $availablePlugin['changelog'] ?? null,
                        ];
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error('Error checking for plugin updates: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return $updates;
    }

    /**
     * Get update information for a specific plugin
     *
     * @param string $name
     * @return array|null Update information or null if no update available
     */
    public function getUpdateInfo(string $name): ?array
    {
        $updates = $this->checkForUpdates();
        return $updates[$name] ?? null;
    }

    /**
     * Update a plugin to the latest version
     *
     * @param string $name Plugin name
     * @param string|null $downloadUrl Optional direct download URL (if null, will fetch from management site)
     * @return bool
     * @throws \Exception
     */
    public function updatePlugin(string $name, ?string $downloadUrl = null): bool
    {
        $plugin = $this->getPlugin($name);

        if (!$plugin) {
            throw new \Exception("Plugin {$name} is not installed");
        }

        $wasActive = $plugin->isActive;

        // Get update information
        $updateInfo = $this->getUpdateInfo($name);

        if (!$updateInfo) {
            throw new \Exception("No update available for plugin {$name}");
        }

        // Deactivate plugin before update
        if ($wasActive) {
            $this->deactivatePlugin($name);
        }

        // Download the update
        $downloadUrl = $downloadUrl ?? $updateInfo['download_url'];
        if (!$downloadUrl) {
            throw new \Exception("Download URL not available for plugin {$name}");
        }

        $tempZipPath = sys_get_temp_dir() . '/plugin_update_' . uniqid() . '.zip';

        try {
            // Download the ZIP file
            $response = Http::timeout(60)->get($downloadUrl);

            if (!$response->successful()) {
                throw new \Exception("Failed to download plugin update: HTTP {$response->status()}");
            }

            File::put($tempZipPath, $response->body());

            // Backup current plugin
            $backupPath = sys_get_temp_dir() . '/plugin_backup_' . $name . '_' . time();
            if (File::isDirectory($plugin->rootPath)) {
                File::copyDirectory($plugin->rootPath, $backupPath);
            }

            try {
                // Uninstall current version (but keep database entry)
                if (File::isDirectory($plugin->rootPath)) {
                    File::deleteDirectory($plugin->rootPath);
                }

                // Install new version
                $updatedPlugin = $this->installPlugin($tempZipPath);

                // Update database entry with new version
                DB::table('plugins')
                    ->where('name', $name)
                    ->update([
                        'version' => $updateInfo['available'],
                        'title' => $updatedPlugin->title,
                        'description' => $updatedPlugin->description,
                        'metadata' => json_encode($updatedPlugin->metadata),
                        'root_path' => $updatedPlugin->rootPath,
                        'updated_at' => now(),
                    ]);

                // Reactivate if it was active before
                if ($wasActive) {
                    $this->activatePlugin($name);
                }

                // Clean up backup
                if (File::isDirectory($backupPath)) {
                    File::deleteDirectory($backupPath);
                }

                Log::info("Plugin updated: {$name} from {$updateInfo['current']} to {$updateInfo['available']}");

                return true;
            } catch (\Exception $e) {
                // Rollback: restore backup
                if (File::isDirectory($backupPath)) {
                    if (File::isDirectory($plugin->rootPath)) {
                        File::deleteDirectory($plugin->rootPath);
                    }
                    File::moveDirectory($backupPath, $plugin->rootPath);
                }

                // Reactivate if it was active before
                if ($wasActive) {
                    try {
                        $this->activatePlugin($name);
                    } catch (\Exception $activateException) {
                        Log::error("Failed to reactivate plugin after rollback: " . $activateException->getMessage());
                    }
                }

                throw new \Exception("Failed to update plugin {$name}: " . $e->getMessage());
            }
        } finally {
            // Clean up temp ZIP file
            if (File::exists($tempZipPath)) {
                File::delete($tempZipPath);
            }
        }
    }

    /**
     * Save plugin to database
     *
     * @param Plugin $plugin
     * @return void
     */
    protected function savePlugin(Plugin $plugin): void
    {
        $data = $plugin->toArray();

        DB::table('plugins')->insert([
            'name' => $data['name'],
            'title' => $data['title'],
            'version' => $data['version'],
            'description' => $data['description'],
            'author' => $data['author'],
            'main_class' => $data['main_class'],
            'is_active' => $data['is_active'],
            'metadata' => json_encode($data['metadata']),
            'requirements' => json_encode($data['requirements']),
            'root_path' => $data['root_path'],
            'installed_at' => $data['installed_at'],
            'activated_at' => $data['activated_at'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Find plugin.json in extracted directory
     *
     * @param string $path
     * @return string|null
     */
    protected function findPluginJson(string $path): ?string
    {
        // Check root directory
        $pluginJsonPath = $path . '/plugin.json';
        if (File::exists($pluginJsonPath)) {
            return $pluginJsonPath;
        }

        // Check subdirectories (common in ZIP structures)
        $directories = File::directories($path);
        foreach ($directories as $dir) {
            $pluginJsonPath = $dir . '/plugin.json';
            if (File::exists($pluginJsonPath)) {
                return $pluginJsonPath;
            }
        }

        return null;
    }

    /**
     * Validate plugin metadata
     *
     * @param array $metadata
     * @return void
     * @throws \Exception
     */
    protected function validatePluginMetadata(array $metadata): void
    {
        $required = ['name', 'version'];
        foreach ($required as $field) {
            if (empty($metadata[$field])) {
                throw new \Exception("Missing required field in plugin.json: {$field}");
            }
        }

        // Validate name format (slug)
        if (!preg_match('/^[a-z0-9\-]+$/', $metadata['name'])) {
            throw new \Exception("Invalid plugin name. Must be lowercase alphanumeric with hyphens.");
        }
    }

    /**
     * Check if plugin requirements are satisfied
     *
     * @param array $metadata
     * @return bool
     */
    protected function satisfiesRequirements(array $metadata): bool
    {
        $requirements = $metadata['requires'] ?? [];

        // Check PHP version
        if (isset($requirements['php'])) {
            $requiredVersion = $requirements['php'];
            if (!version_compare(PHP_VERSION, $requiredVersion, '>=')) {
                Log::warning("PHP version requirement not met: requires {$requiredVersion}, have " . PHP_VERSION);
                return false;
            }
        }

        // Check Laravel version
        if (isset($requirements['laravel'])) {
            $requiredVersion = $requirements['laravel'];
            $laravelVersion = app()->version();
            // Simple version comparison - could be improved
            if (!version_compare($laravelVersion, $requiredVersion, '>=')) {
                Log::warning("Laravel version requirement not met: requires {$requiredVersion}, have {$laravelVersion}");
                return false;
            }
        }

        // Check core-services version if specified
        if (isset($requirements['core'])) {
            $requiredVersion = $requirements['core'];
            // This would need to check the actual installed version
            // For now, we'll skip this check
        }

        return true;
    }

    /**
     * Register autoloader for a plugin
     *
     * @param string $pluginName
     * @param string $pluginPath
     * @return void
     */
    protected function registerPluginAutoloader(string $pluginName, string $pluginPath): void
    {
        // Look for composer.json and register autoloader
        $composerJsonPath = $pluginPath . '/composer.json';
        if (File::exists($composerJsonPath)) {
            $composerJson = json_decode(File::get($composerJsonPath), true);
            
            if (isset($composerJson['autoload']['psr-4'])) {
                $vendorPath = $pluginPath . '/vendor';
                $autoloadPath = $vendorPath . '/autoload.php';
                
                if (File::exists($autoloadPath)) {
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
        // This handles plugins without composer.json
        // For plugins like AuthNetPayment\Plugin in src/Plugin.php
        $srcPath = $pluginPath . '/src';
        if (File::isDirectory($srcPath)) {
            spl_autoload_register(function ($class) use ($srcPath, $pluginName) {
                // Extract class name from fully qualified name
                $parts = explode('\\', $class);
                $className = array_pop($parts);
                
                // Try direct class name first (e.g., src/Plugin.php for AuthNetPayment\Plugin)
                // This is the most common pattern for simple plugins
                $file = $srcPath . '/' . $className . '.php';
                if (file_exists($file)) {
                    require_once $file;
                    return;
                }
                
                // Try namespace path (e.g., src/AuthNetPayment/Plugin.php)
                $file = $srcPath . '/' . str_replace('\\', '/', $class) . '.php';
                if (file_exists($file)) {
                    require_once $file;
                    return;
                }
                
                // Try subdirectory matching namespace (e.g., src/AuthNetPayment/Plugin.php)
                if (!empty($parts)) {
                    $namespacePath = implode('/', $parts);
                    $file = $srcPath . '/' . $namespacePath . '/' . $className . '.php';
                    if (file_exists($file)) {
                        require_once $file;
                        return;
                    }
                }
            }, true); // Prepend to autoloader stack for priority ($throw = true, $prepend = true)
        }
    }
}

