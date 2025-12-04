<?php

namespace Ygs\CoreServices\Plugins;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

/**
 * Migration Runner for Plugins
 * 
 * Handles running migrations for plugins.
 */
class MigrationRunner
{
    /**
     * Run migrations for a plugin
     *
     * @param string $pluginName
     * @return void
     */
    public function runPluginMigrations(string $pluginName): void
    {
        $pluginManager = app(PluginManager::class);
        $plugin = $pluginManager->getPlugin($pluginName);

        if (!$plugin) {
            throw new \Exception("Plugin {$pluginName} not found");
        }

        $migrationsPath = $plugin->rootPath . '/database/migrations';
        
        if (!File::isDirectory($migrationsPath)) {
            Log::info("No migrations directory found for plugin: {$pluginName}");
            return;
        }

        $migrationFiles = File::glob($migrationsPath . '/*.php');
        
        if (empty($migrationFiles)) {
            Log::info("No migrations found for plugin: {$pluginName}");
            return;
        }

        Log::info("Running migrations for plugin: {$pluginName}", [
            'count' => count($migrationFiles),
            'path' => $migrationsPath
        ]);

        // Run migrations using Laravel's migrate command with --path option
        try {
            Artisan::call('migrate', [
                '--path' => 'plugins/' . $pluginName . '/database/migrations',
                '--force' => true,
            ]);

            Log::info("Migrations completed for plugin: {$pluginName}");
        } catch (\Exception $e) {
            // If --path doesn't work, try running migrations directly
            Log::warning("Standard migration path failed, trying alternative method", [
                'error' => $e->getMessage()
            ]);

            // Alternative: Copy migrations to database/migrations temporarily
            $this->runMigrationsAlternative($pluginName, $migrationsPath);
        }
    }

    /**
     * Alternative migration method
     * Copies migrations to database/migrations and runs them
     *
     * @param string $pluginName
     * @param string $migrationsPath
     * @return void
     */
    protected function runMigrationsAlternative(string $pluginName, string $migrationsPath): void
    {
        $tempMigrationsPath = database_path('migrations/plugin_' . $pluginName . '_' . time());

        try {
            // Copy migrations to temp location
            File::copyDirectory($migrationsPath, $tempMigrationsPath);

            // Run migrations
            Artisan::call('migrate', [
                '--path' => 'database/migrations/plugin_' . basename($tempMigrationsPath),
                '--force' => true,
            ]);

            // Clean up temp directory
            File::deleteDirectory($tempMigrationsPath);

            Log::info("Migrations completed for plugin: {$pluginName} (alternative method)");
        } catch (\Exception $e) {
            // Clean up on error
            if (File::isDirectory($tempMigrationsPath)) {
                File::deleteDirectory($tempMigrationsPath);
            }
            throw $e;
        }
    }

    /**
     * Get pending migrations for a plugin
     *
     * @param string $pluginName
     * @return array
     */
    public function getPendingMigrations(string $pluginName): array
    {
        $pluginManager = app(PluginManager::class);
        $plugin = $pluginManager->getPlugin($pluginName);

        if (!$plugin) {
            return [];
        }

        $migrationsPath = $plugin->rootPath . '/database/migrations';
        
        if (!File::isDirectory($migrationsPath)) {
            return [];
        }

        $migrationFiles = File::glob($migrationsPath . '/*.php');
        
        // Filter out already run migrations by checking migration table
        // This is a simplified version - could be improved
        $pending = [];
        foreach ($migrationFiles as $file) {
            $migrationName = basename($file, '.php');
            // Check if migration is in migrations table
            // This would need to be implemented based on your needs
            $pending[] = $migrationName;
        }

        return $pending;
    }
}

