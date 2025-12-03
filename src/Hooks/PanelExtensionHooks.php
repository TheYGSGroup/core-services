<?php

namespace Ygs\CoreServices\Hooks;

/**
 * Panel Extension Hooks
 * 
 * Helper class for managing Lunar Panel extensions via hooks.
 * This provides a clean API for adding pages and resources dynamically.
 */
class PanelExtensionHooks
{
    const HOOK_PAGES = 'panel.extension.pages';
    const HOOK_RESOURCES = 'panel.extension.resources';

    /**
     * Add pages to the Lunar Panel
     *
     * @param callable $callback Callback that receives existing pages array and returns modified array
     * @param int $priority Priority of the filter (lower numbers = higher priority, default 10)
     * @return void
     */
    public static function addPages(callable $callback, int $priority = 10): void
    {
        HookManager::addFilter(self::HOOK_PAGES, $callback, $priority);
    }

    /**
     * Add resources to the Lunar Panel
     *
     * @param callable $callback Callback that receives existing resources array and returns modified array
     * @param int $priority Priority of the filter (lower numbers = higher priority, default 10)
     * @return void
     */
    public static function addResources(callable $callback, int $priority = 10): void
    {
        HookManager::addFilter(self::HOOK_RESOURCES, $callback, $priority);
    }

    /**
     * Get pages with all extensions applied
     *
     * @param array $existingPages Initial pages array
     * @return array Modified pages array
     */
    public static function getPages(array $existingPages): array
    {
        return HookManager::applyFilter(self::HOOK_PAGES, $existingPages);
    }

    /**
     * Get resources with all extensions applied
     *
     * @param array $existingResources Initial resources array
     * @return array Modified resources array
     */
    public static function getResources(array $existingResources): array
    {
        return HookManager::applyFilter(self::HOOK_RESOURCES, $existingResources);
    }
}

