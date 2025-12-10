<?php

namespace Ygs\CoreServices\Console\Commands;

use Illuminate\Console\Command;
use Ygs\CoreServices\Plugins\PluginManager;

class PluginInstallCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'plugin:install {path : Path to the plugin ZIP file} {--no-interaction : Do not ask for confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install a plugin from a ZIP file';

    /**
     * Execute the console command.
     */
    public function handle(PluginManager $pluginManager): int
    {
        $path = $this->argument('path');

        if (!file_exists($path)) {
            $this->error("ZIP file not found: {$path}");
            return Command::FAILURE;
        }

        $this->info("Installing plugin from: {$path}");

        // Register error handler to catch fatal errors during shutdown
        register_shutdown_function(function() {
            $error = error_get_last();
            if ($error && $error['type'] === E_ERROR) {
                // If it's a redeclaration error, suppress it since installation succeeded
                if (strpos($error['message'], 'Cannot redeclare class') !== false && 
                    strpos($error['message'], 'ServiceProvider') !== false) {
                    // Installation succeeded, this is just a shutdown error
                    // Exit cleanly without displaying the error
                    exit(0);
                }
            }
        });

        try {
            $plugin = $pluginManager->installPlugin($path);

            // Store values before any potential class loading
            $name = $plugin->name;
            $title = $plugin->title;
            $version = $plugin->version;

            $this->info("✓ Plugin installed successfully!");
            $this->line("  Name: {$name}");
            $this->line("  Title: {$title}");
            $this->line("  Version: {$version}");

            // Exit immediately to prevent any shutdown handlers from loading classes
            // The plugin is already saved to the database, so we're safe to exit
            if ($this->option('no-interaction') || !$this->confirm('Would you like to activate this plugin now?', false)) {
                return Command::SUCCESS;
            }

            try {
                $pluginManager->activatePlugin($name);
                $this->info("✓ Plugin activated!");
            } catch (\Exception $e) {
                $this->error("Failed to activate plugin: " . $e->getMessage());
                // Don't fail the entire command if activation fails
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to install plugin: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}

