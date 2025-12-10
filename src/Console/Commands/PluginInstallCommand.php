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
    protected $signature = 'plugin:install {plugin : Plugin name (for remote) or path to ZIP file (for local)} 
                            {--from-remote : Install from plugin management site}
                            {--remote : Alias for --from-remote}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install a plugin from a ZIP file or from the remote plugin management site';

    /**
     * Execute the console command.
     */
    public function handle(PluginManager $pluginManager): int
    {
        $plugin = $this->argument('plugin');
        $fromRemote = $this->option('from-remote') || $this->option('remote');

        // Determine if this is a remote installation or local file
        $isRemote = $fromRemote || $this->isRemoteInstallation($plugin);

        if ($isRemote) {
            return $this->installFromRemote($pluginManager, $plugin);
        } else {
            return $this->installFromLocal($pluginManager, $plugin);
        }
    }

    /**
     * Determine if the given argument is for remote installation
     */
    protected function isRemoteInstallation(string $plugin): bool
    {
        // If it's a file path (contains / or ends in .zip), it's local
        if (strpos($plugin, '/') !== false || strpos($plugin, '\\') !== false) {
            return false;
        }

        if (str_ends_with($plugin, '.zip')) {
            return false;
        }

        // If it's a valid file path, it's local
        if (file_exists($plugin)) {
            return false;
        }

        // Otherwise, assume it's a plugin name for remote installation
        return true;
    }

    /**
     * Install plugin from remote management site
     */
    protected function installFromRemote(PluginManager $pluginManager, string $pluginName): int
    {
        $this->info("Installing plugin from remote: {$pluginName}");

        try {
            $plugin = $pluginManager->installPluginFromRemote($pluginName);

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

    /**
     * Install plugin from local ZIP file
     */
    protected function installFromLocal(PluginManager $pluginManager, string $path): int
    {
        if (!file_exists($path)) {
            $this->error("ZIP file not found: {$path}");
            $this->line("");
            $this->line("Tip: To install from remote, use:");
            $this->line("  php artisan plugin:install <plugin-name> --from-remote");
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

