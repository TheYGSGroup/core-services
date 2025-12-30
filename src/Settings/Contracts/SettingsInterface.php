<?php

namespace Ygs\CoreServices\Settings\Contracts;

/**
 * Settings Interface
 * 
 * Provides a contract for settings management that plugins can depend on
 * without being tied to a specific implementation.
 */
interface SettingsInterface
{
    /**
     * Get a setting value
     *
     * @param string $key The setting key (supports dot notation, e.g., 'plugin.name.setting')
     * @param mixed $default The default value if setting doesn't exist
     * @return mixed
     */
    public function get(string $key, $default = null);

    /**
     * Set a setting value
     *
     * @param string $key The setting key (supports dot notation)
     * @param mixed $value The value to set
     * @param string $type The type of setting (string, boolean, integer, array, etc.)
     * @param string $group The group/category for the setting (default: 'general')
     * @param string $description Optional description of the setting
     * @return bool True if setting was saved successfully
     */
    public function set(string $key, $value, string $type = 'string', string $group = 'general', string $description = ''): bool;

    /**
     * Check if a setting exists
     *
     * @param string $key The setting key
     * @return bool
     */
    public function has(string $key): bool;

    /**
     * Remove a setting
     *
     * @param string $key The setting key
     * @return bool True if setting was removed successfully
     */
    public function forget(string $key): bool;

    /**
     * Get all settings, optionally filtered by group
     *
     * @param string|null $group The group to filter by (null = all groups)
     * @return array
     */
    public function all(?string $group = null): array;
}

