<?php

namespace Ygs\CoreServices\Plugins\Contracts;

/**
 * Plugin Interface
 * 
 * All plugins must implement this interface.
 */
interface PluginInterface
{
    /**
     * Get the plugin name (slug)
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Get the plugin title (display name)
     *
     * @return string
     */
    public function getTitle(): string;

    /**
     * Get the plugin version
     *
     * @return string
     */
    public function getVersion(): string;

    /**
     * Get the plugin description
     *
     * @return string|null
     */
    public function getDescription(): ?string;

    /**
     * Get the plugin author
     *
     * @return string|null
     */
    public function getAuthor(): ?string;

    /**
     * Get plugin requirements
     *
     * @return array
     */
    public function getRequirements(): array;

    /**
     * Plugin activation hook
     * Called when the plugin is activated
     *
     * @return void
     */
    public function activate(): void;

    /**
     * Plugin deactivation hook
     * Called when the plugin is deactivated
     *
     * @return void
     */
    public function deactivate(): void;

    /**
     * Get the plugin service provider class name
     *
     * @return string|null
     */
    public function getServiceProvider(): ?string;

    /**
     * Get plugin metadata from plugin.json
     *
     * @return array
     */
    public function getMetadata(): array;
}

