<?php

namespace Ygs\CoreServices\Console\Commands;

use Illuminate\Console\Command;
use Ygs\CoreServices\Plugins\PluginManager;

class PluginListCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'plugin:list';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List all installed plugins';

    /**
     * Execute the console command.
     */
    public function handle(PluginManager $pluginManager): int
    {
        $plugins = $pluginManager->getInstalledPlugins();

        if (empty($plugins)) {
            $this->info('No plugins installed.');
            return Command::SUCCESS;
        }

        $headers = ['Name', 'Title', 'Version', 'Status', 'Installed'];
        $rows = [];

        foreach ($plugins as $plugin) {
            $rows[] = [
                $plugin->name,
                $plugin->title,
                $plugin->version,
                $plugin->isActive ? '<fg=green>Active</>' : '<fg=red>Inactive</>',
                $plugin->installedAt ? $plugin->installedAt->format('Y-m-d') : 'N/A',
            ];
        }

        $this->table($headers, $rows);

        return Command::SUCCESS;
    }
}

