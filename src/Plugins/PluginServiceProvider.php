<?php

namespace Ygs\CoreServices\Plugins;

use Illuminate\Support\ServiceProvider;
use Ygs\CoreServices\Plugins\Contracts\PluginInterface;

/**
 * Base Service Provider for Plugins
 * 
 * Plugins can extend this class for their service provider.
 */
abstract class PluginServiceProvider extends ServiceProvider
{
    /**
     * Plugin instance
     *
     * @var PluginInterface|null
     */
    protected ?PluginInterface $plugin = null;

    /**
     * Register services.
     *
     * @return void
     */
    public function register(): void
    {
        // Override in plugin
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot(): void
    {
        // Override in plugin
    }

    /**
     * Set the plugin instance
     *
     * @param PluginInterface $plugin
     * @return void
     */
    public function setPlugin(PluginInterface $plugin): void
    {
        $this->plugin = $plugin;
    }

    /**
     * Get the plugin instance
     *
     * @return PluginInterface|null
     */
    public function getPlugin(): ?PluginInterface
    {
        return $this->plugin;
    }
}

