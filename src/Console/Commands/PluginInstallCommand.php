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
    protected $signature = 'plugin:install {path : Path to the plugin ZIP file}';

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

        try {
            $plugin = $pluginManager->installPlugin($path);

            $this->info("✓ Plugin installed successfully!");
            $this->line("  Name: {$plugin->name}");
            $this->line("  Title: {$plugin->title}");
            $this->line("  Version: {$plugin->version}");

            if ($this->confirm('Would you like to activate this plugin now?', true)) {
                $pluginManager->activatePlugin($plugin->name);
                $this->info("✓ Plugin activated!");
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to install plugin: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}

