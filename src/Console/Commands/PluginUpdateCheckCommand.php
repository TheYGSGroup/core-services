<?php

namespace Ygs\CoreServices\Console\Commands;

use Illuminate\Console\Command;
use Ygs\CoreServices\Plugins\PluginManager;

class PluginUpdateCheckCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'plugin:check-updates {--plugin= : Check updates for a specific plugin}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for available plugin updates';

    /**
     * Execute the console command.
     */
    public function handle(PluginManager $pluginManager): int
    {
        $this->info('Checking for plugin updates...');

        $updates = $pluginManager->checkForUpdates();

        if (empty($updates)) {
            $this->info('All plugins are up to date.');
            return Command::SUCCESS;
        }

        $this->info('Available updates:');
        $this->newLine();

        $headers = ['Plugin', 'Current Version', 'Available Version', 'Changelog'];
        $rows = [];

        foreach ($updates as $pluginName => $updateInfo) {
            $rows[] = [
                $pluginName,
                $updateInfo['current'],
                $updateInfo['available'],
                $updateInfo['changelog'] ? substr($updateInfo['changelog'], 0, 50) . '...' : 'N/A',
            ];
        }

        $this->table($headers, $rows);

        $this->newLine();
        $this->info('Run "php artisan plugin:update <plugin-name>" to update a plugin.');

        return Command::SUCCESS;
    }
}

