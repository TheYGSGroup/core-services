<?php

namespace Ygs\CoreServices\Plugins;

/**
 * Plugin Model
 * 
 * Represents an installed plugin in the database.
 */
class Plugin
{
    /**
     * Plugin name (slug)
     *
     * @var string
     */
    public string $name;

    /**
     * Plugin title (display name)
     *
     * @var string
     */
    public string $title;

    /**
     * Plugin version
     *
     * @var string
     */
    public string $version;

    /**
     * Plugin description
     *
     * @var string|null
     */
    public ?string $description;

    /**
     * Plugin author
     *
     * @var string|null
     */
    public ?string $author;

    /**
     * Main plugin class
     *
     * @var string
     */
    public string $mainClass;

    /**
     * Whether the plugin is active
     *
     * @var bool
     */
    public bool $isActive;

    /**
     * Plugin metadata (full plugin.json data)
     *
     * @var array
     */
    public array $metadata;

    /**
     * Plugin requirements
     *
     * @var array
     */
    public array $requirements;

    /**
     * Installation timestamp
     *
     * @var \DateTime|null
     */
    public ?\DateTime $installedAt;

    /**
     * Activation timestamp
     *
     * @var \DateTime|null
     */
    public ?\DateTime $activatedAt;

    /**
     * Plugin root directory path
     *
     * @var string
     */
    public string $rootPath;

    /**
     * PluginInterface instance
     *
     * @var PluginInterface|null
     */
    protected ?PluginInterface $instance = null;

    /**
     * Constructor
     *
     * @param array $attributes
     */
    public function __construct(array $attributes = [])
    {
        $this->name = $attributes['name'] ?? '';
        $this->title = $attributes['title'] ?? '';
        $this->version = $attributes['version'] ?? '1.0.0';
        $this->description = $attributes['description'] ?? null;
        $this->author = $attributes['author'] ?? null;
        $this->mainClass = $attributes['main_class'] ?? '';
        $this->isActive = $attributes['is_active'] ?? false;
        $this->metadata = $attributes['metadata'] ?? [];
        $this->requirements = $attributes['requirements'] ?? [];
        $this->rootPath = $attributes['root_path'] ?? '';
        
        $this->installedAt = isset($attributes['installed_at']) 
            ? \Carbon\Carbon::parse($attributes['installed_at']) 
            : null;
            
        $this->activatedAt = isset($attributes['activated_at']) 
            ? \Carbon\Carbon::parse($attributes['activated_at']) 
            : null;
    }

    /**
     * Get the plugin instance
     *
     * @return PluginInterface
     * @throws \Exception
     */
    public function getInstance(): PluginInterface
    {
        if ($this->instance === null) {
            if (!class_exists($this->mainClass)) {
                throw new \Exception("Plugin class {$this->mainClass} not found");
            }

            $this->instance = new $this->mainClass($this->rootPath, $this->metadata);
        }

        return $this->instance;
    }

    /**
     * Convert to array for database storage
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'title' => $this->title,
            'version' => $this->version,
            'description' => $this->description,
            'author' => $this->author,
            'main_class' => $this->mainClass,
            'is_active' => $this->isActive,
            'metadata' => $this->metadata,
            'requirements' => $this->requirements,
            'root_path' => $this->rootPath,
            'installed_at' => $this->installedAt?->toDateTimeString(),
            'activated_at' => $this->activatedAt?->toDateTimeString(),
        ];
    }
}

