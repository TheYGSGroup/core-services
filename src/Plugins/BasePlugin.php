<?php

namespace Ygs\CoreServices\Plugins;

use Ygs\CoreServices\Plugins\Contracts\PluginInterface;

/**
 * Base Plugin Class
 * 
 * Provides default implementation for common plugin functionality.
 * Plugins can extend this class instead of implementing PluginInterface directly.
 */
abstract class BasePlugin implements PluginInterface
{
    /**
     * Plugin metadata loaded from plugin.json
     *
     * @var array
     */
    protected array $metadata = [];

    /**
     * Plugin root directory path
     *
     * @var string
     */
    protected string $rootPath;

    /**
     * Constructor
     *
     * @param string $rootPath Plugin root directory path
     * @param array $metadata Plugin metadata from plugin.json
     */
    public function __construct(string $rootPath, array $metadata = [])
    {
        $this->rootPath = $rootPath;
        $this->metadata = $metadata;
    }

    /**
     * Get the plugin name (slug)
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->metadata['name'] ?? '';
    }

    /**
     * Get the plugin title (display name)
     *
     * @return string
     */
    public function getTitle(): string
    {
        return $this->metadata['title'] ?? $this->getName();
    }

    /**
     * Get the plugin version
     *
     * @return string
     */
    public function getVersion(): string
    {
        return $this->metadata['version'] ?? '1.0.0';
    }

    /**
     * Get the plugin description
     *
     * @return string|null
     */
    public function getDescription(): ?string
    {
        return $this->metadata['description'] ?? null;
    }

    /**
     * Get the plugin author
     *
     * @return string|null
     */
    public function getAuthor(): ?string
    {
        return $this->metadata['author'] ?? null;
    }

    /**
     * Get plugin requirements
     *
     * @return array
     */
    public function getRequirements(): array
    {
        return $this->metadata['requires'] ?? [];
    }

    /**
     * Plugin activation hook
     * Override this method in your plugin class
     *
     * @return void
     */
    public function activate(): void
    {
        // Default: no activation logic needed
    }

    /**
     * Plugin deactivation hook
     * Override this method in your plugin class
     *
     * @return void
     */
    public function deactivate(): void
    {
        // Default: no deactivation logic needed
    }

    /**
     * Get the plugin service provider class name
     *
     * @return string|null
     */
    public function getServiceProvider(): ?string
    {
        return $this->metadata['service_provider'] ?? null;
    }

    /**
     * Get plugin metadata from plugin.json
     *
     * @return array
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Get the plugin root directory path
     *
     * @return string
     */
    public function getRootPath(): string
    {
        return $this->rootPath;
    }

    /**
     * Get a path relative to the plugin root
     *
     * @param string $path
     * @return string
     */
    public function path(string $path = ''): string
    {
        return $this->rootPath . ($path ? DIRECTORY_SEPARATOR . $path : '');
    }
}

