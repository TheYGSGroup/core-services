<?php

namespace Ygs\CoreServices\Facades;

use Illuminate\Support\Facades\Facade;
use Ygs\CoreServices\Hooks\HookManager as HookManagerClass;

/**
 * HookManager Facade
 * 
 * Provides easy access to the HookManager service.
 * Since HookManager uses static methods, this facade proxies directly to the class.
 * 
 * @method static bool addAction(string $hook, callable $callback, int $priority = 10, int $acceptedArgs = 1)
 * @method static bool addFilter(string $hook, callable $callback, int $priority = 10, int $acceptedArgs = 1)
 * @method static bool removeAction(string $hook, callable $callback, int $priority = 10)
 * @method static bool removeFilter(string $hook, callable $callback, int $priority = 10)
 * @method static array doAction(string $hook, ...$args)
 * @method static mixed applyFilter(string $hook, $value, ...$args)
 * @method static bool hasAction(string $hook)
 * @method static bool hasFilter(string $hook)
 * @method static array getHooks(?string $hook = null)
 * @method static void clearHooks(?string $hook = null)
 */
class HookManager extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'ygs.hook-manager';
    }

    /**
     * Handle dynamic, static calls to the object.
     *
     * @param  string  $method
     * @param  array  $args
     * @return mixed
     */
    public static function __callStatic($method, $args)
    {
        // Since HookManager uses static methods, proxy directly to the class
        return HookManagerClass::$method(...$args);
    }
}

