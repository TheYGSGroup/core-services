<?php

namespace Ygs\CoreServices\Console\Commands;

use Illuminate\Console\Command;
use Ygs\CoreServices\Plugins\PluginManager;

class PluginDeactivateCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'plugin:deactivate {name : The name of the plugin}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Deactivate a plugin';

    /**
     * Execute the console command.
     */
    public function handle(PluginManager $pluginManager): int
    {
        $name = $this->argument('name');

        try {
            $plugin = $pluginManager->getPlugin($name);

            if (!$plugin) {
                $this->error("Plugin '{$name}' is not installed.");
                return Command::FAILURE;
            }

            if (!$plugin->isActive) {
                $this->warn("Plugin '{$name}' is already inactive.");
                return Command::SUCCESS;
            }

            $this->info("Deactivating plugin: {$name}...");

            $pluginManager->deactivatePlugin($name);

            $this->info("✓ Plugin '{$name}' deactivated successfully!");

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to deactivate plugin: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}

