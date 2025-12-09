<?php

namespace Ygs\CoreServices\Console\Commands;

use Illuminate\Console\Command;
use Ygs\CoreServices\Plugins\PluginManager;

class PluginUpdateCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'plugin:update {plugin : The name of the plugin to update} {--force : Force update even if no update is available}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update a plugin to the latest version';

    /**
     * Execute the console command.
     */
    public function handle(PluginManager $pluginManager): int
    {
        $pluginName = $this->argument('plugin');
        $force = $this->option('force');

        $this->info("Checking for updates for plugin: {$pluginName}");

        try {
            // Check if plugin is installed
            $plugin = $pluginManager->getPlugin($pluginName);
            if (!$plugin) {
                $this->error("Plugin {$pluginName} is not installed.");
                return Command::FAILURE;
            }

            // Check for updates
            $updateInfo = $pluginManager->getUpdateInfo($pluginName);

            if (!$updateInfo && !$force) {
                $this->info("Plugin {$pluginName} is already up to date (v{$plugin->version}).");
                return Command::SUCCESS;
            }

            if ($updateInfo) {
                $this->info("Update available: {$updateInfo['current']} -> {$updateInfo['available']}");
                if ($updateInfo['changelog']) {
                    $this->line("Changelog: {$updateInfo['changelog']}");
                }
            }

            if (!$this->confirm("Do you want to update {$pluginName}?", true)) {
                $this->info('Update cancelled.');
                return Command::SUCCESS;
            }

            $this->info("Updating plugin {$pluginName}...");

            // Put application in maintenance mode
            $this->call('down', ['--render' => 'errors::503']);

            try {
                $pluginManager->updatePlugin($pluginName);

                $this->info("Plugin {$pluginName} updated successfully!");
            } finally {
                // Take application out of maintenance mode
                $this->call('up');
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to update plugin: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}

