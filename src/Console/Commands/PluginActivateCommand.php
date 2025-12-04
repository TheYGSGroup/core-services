<?php

namespace Ygs\CoreServices\Console\Commands;

use Illuminate\Console\Command;
use Ygs\CoreServices\Plugins\PluginManager;

class PluginActivateCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'plugin:activate {name : The name of the plugin}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Activate a plugin';

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

            if ($plugin->isActive) {
                $this->warn("Plugin '{$name}' is already active.");
                return Command::SUCCESS;
            }

            $this->info("Activating plugin: {$name}...");

            $pluginManager->activatePlugin($name);

            $this->info("✓ Plugin '{$name}' activated successfully!");

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to activate plugin: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}

