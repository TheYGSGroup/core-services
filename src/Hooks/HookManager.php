<?php

namespace Ygs\CoreServices\Hooks;

use Illuminate\Support\Facades\Log;

/**
 * WordPress-style Hook Manager
 * 
 * Provides action and filter hooks similar to WordPress's do_action() and apply_filters().
 * This system allows for extensible, modular code through a priority-based hook system.
 */
class HookManager
{
    /**
     * Registered hooks for different events
     * Structure: $hooks[$hookName][$priority][] = ['callback' => callable, 'accepted_args' => int]
     */
    private static array $hooks = [];

    /**
     * Register an action hook (void return, multiple handlers)
     *
     * @param string $hook The hook name to register
     * @param callable $callback The callback function to execute
     * @param int $priority Priority of the hook (lower numbers = higher priority, default 10)
     * @param int $acceptedArgs Number of arguments the callback accepts (default 1)
     * @return bool True if hook was registered successfully
     */
    public static function addAction(string $hook, callable $callback, int $priority = 10, int $acceptedArgs = 1): bool
    {
        if (!isset(self::$hooks[$hook])) {
            self::$hooks[$hook] = [];
        }

        if (!isset(self::$hooks[$hook][$priority])) {
            self::$hooks[$hook][$priority] = [];
        }

        self::$hooks[$hook][$priority][] = [
            'callback' => $callback,
            'accepted_args' => $acceptedArgs,
        ];

        // Sort by priority (lower numbers first)
        ksort(self::$hooks[$hook]);

        Log::debug("Action hook registered: {$hook} (priority: {$priority})");

        return true;
    }

    /**
     * Register a filter hook (return modified value, multiple handlers)
     *
     * @param string $hook The hook name to register
     * @param callable $callback The callback function to execute
     * @param int $priority Priority of the hook (lower numbers = higher priority, default 10)
     * @param int $acceptedArgs Number of arguments the callback accepts (default 1)
     * @return bool True if hook was registered successfully
     */
    public static function addFilter(string $hook, callable $callback, int $priority = 10, int $acceptedArgs = 1): bool
    {
        // Filters use the same internal structure as actions
        return self::addAction($hook, $callback, $priority, $acceptedArgs);
    }

    /**
     * Remove an action hook
     *
     * @param string $hook The hook name
     * @param callable $callback The callback function to remove
     * @param int $priority Priority of the hook
     * @return bool True if hook was removed successfully
     */
    public static function removeAction(string $hook, callable $callback, int $priority = 10): bool
    {
        return self::removeHook($hook, $callback, $priority);
    }

    /**
     * Remove a filter hook
     *
     * @param string $hook The hook name
     * @param callable $callback The callback function to remove
     * @param int $priority Priority of the hook
     * @return bool True if hook was removed successfully
     */
    public static function removeFilter(string $hook, callable $callback, int $priority = 10): bool
    {
        return self::removeHook($hook, $callback, $priority);
    }

    /**
     * Remove a hook (internal method)
     *
     * @param string $hook The hook name
     * @param callable $callback The callback function to remove
     * @param int $priority Priority of the hook
     * @return bool True if hook was removed successfully
     */
    private static function removeHook(string $hook, callable $callback, int $priority = 10): bool
    {
        if (!isset(self::$hooks[$hook][$priority])) {
            return false;
        }

        foreach (self::$hooks[$hook][$priority] as $key => $hookData) {
            if ($hookData['callback'] === $callback) {
                unset(self::$hooks[$hook][$priority][$key]);
                
                // Clean up empty priority arrays
                if (empty(self::$hooks[$hook][$priority])) {
                    unset(self::$hooks[$hook][$priority]);
                }
                
                // Clean up empty hook arrays
                if (empty(self::$hooks[$hook])) {
                    unset(self::$hooks[$hook]);
                }
                
                Log::debug("Hook removed: {$hook} (priority: {$priority})");
                return true;
            }
        }

        return false;
    }

    /**
     * Trigger an action (void return, multiple handlers)
     * This method is designed to be fault-tolerant - hook failures won't break the execution
     *
     * @param string $hook The hook name to trigger
     * @param mixed ...$args Arguments to pass to the hook callbacks
     * @return array Results from all executed hooks
     */
    public static function doAction(string $hook, ...$args): array
    {
        return self::executeHooks($hook, null, $args);
    }

    /**
     * Apply a filter (return modified value, multiple handlers)
     *
     * @param string $hook The hook name to trigger
     * @param mixed $value The value to filter
     * @param mixed ...$args Additional arguments to pass to the hook callbacks
     * @return mixed The filtered value
     */
    public static function applyFilter(string $hook, $value, ...$args): mixed
    {
        $allArgs = array_merge([$value], $args);
        $results = self::executeHooks($hook, $value, $allArgs);
        
        // For filters, return the last successful result, or the original value
        $filteredValue = $value;
        foreach ($results as $result) {
            if ($result['success'] && $result['result'] !== null) {
                $filteredValue = $result['result'];
            }
        }
        
        return $filteredValue;
    }

    /**
     * Execute hooks for a given hook name
     *
     * @param string $hook The hook name
     * @param mixed|null $initialValue Initial value for filters (null for actions)
     * @param array $args Arguments to pass to callbacks
     * @return array Results from all executed hooks
     */
    private static function executeHooks(string $hook, $initialValue = null, array $args = []): array
    {
        $results = [];
        
        if (!isset(self::$hooks[$hook])) {
            Log::debug("No hooks registered for: {$hook}");
            return $results;
        }

        $isFilter = $initialValue !== null;
        $logType = $isFilter ? 'filter' : 'action';
        Log::debug("Triggering {$logType} hook: {$hook} with " . count($args) . " arguments");

        foreach (self::$hooks[$hook] as $priority => $hooks) {
            foreach ($hooks as $hookIndex => $hookData) {
                try {
                    $callback = $hookData['callback'];
                    $acceptedArgs = $hookData['accepted_args'];
                    
                    // For filters, pass the current filtered value as first argument
                    if ($isFilter) {
                        $currentValue = !empty($results) && end($results)['success'] 
                            ? end($results)['result'] 
                            : $initialValue;
                        $callbackArgs = array_merge([$currentValue], $args);
                    } else {
                        $callbackArgs = $args;
                    }
                    
                    // Use reflection to properly handle function calls with named parameters
                    $result = self::executeHookCallback($callback, $callbackArgs, $acceptedArgs);
                    
                    // For filters, update the value if result is not null
                    if ($isFilter && $result !== null) {
                        $filteredValue = $result;
                    } else {
                        $filteredValue = $result;
                    }
                    
                    $results[] = [
                        'priority' => $priority,
                        'hook_index' => $hookIndex,
                        'result' => $filteredValue,
                        'success' => true,
                    ];
                    
                    Log::debug("Hook executed successfully for {$logType}: {$hook} with priority: {$priority}");
                    
                } catch (\Throwable $e) {
                    // Use Throwable instead of Exception to catch all errors including fatal errors
                    Log::error("Hook execution failed for {$logType}: {$hook} with priority: {$priority}", [
                        'error' => $e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                    
                    $results[] = [
                        'priority' => $priority,
                        'hook_index' => $hookIndex,
                        'result' => null,
                        'success' => false,
                        'error' => $e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                    ];
                    
                    // Continue with other hooks - don't let one failure break the entire process
                    continue;
                }
            }
        }

        return $results;
    }

    /**
     * Execute a hook callback with proper argument handling
     *
     * @param callable $callback The callback function
     * @param array $args The arguments to pass
     * @param int $acceptedArgs Number of arguments the callback accepts
     * @return mixed The result of the callback
     */
    private static function executeHookCallback(callable $callback, array $args, int $acceptedArgs)
    {
        // Validate callback
        if (!is_callable($callback)) {
            throw new \InvalidArgumentException('Callback is not callable');
        }

        // If it's a closure or function, use reflection to get parameter information
        if (is_callable($callback) && !is_string($callback)) {
            try {
                $reflection = new \ReflectionFunction($callback);
                $parameters = $reflection->getParameters();
                
                // Check if args is associative (has string keys) or positional (has numeric keys)
                $isAssociative = false;
                foreach (array_keys($args) as $key) {
                    if (is_string($key)) {
                        $isAssociative = true;
                        break;
                    }
                }
                
                if ($isAssociative) {
                    // Convert associative array to positional array based on parameter names
                    $callbackArgs = [];
                    
                    foreach ($parameters as $index => $parameter) {
                        $paramName = $parameter->getName();
                        
                        if (isset($args[$paramName])) {
                            $callbackArgs[] = $args[$paramName];
                        } elseif (isset($args[$index])) {
                            // Fallback to numeric index
                            $callbackArgs[] = $args[$index];
                        } elseif ($parameter->isDefaultValueAvailable()) {
                            // Use default value if available
                            $callbackArgs[] = $parameter->getDefaultValue();
                        } else {
                            // Pass null for missing required parameters
                            $callbackArgs[] = null;
                        }
                    }
                } else {
                    // Positional array - use simple slicing
                    $callbackArgs = array_slice($args, 0, count($parameters));
                    
                    // Fill remaining parameters with defaults or null
                    while (count($callbackArgs) < count($parameters)) {
                        $paramIndex = count($callbackArgs);
                        $parameter = $parameters[$paramIndex];
                        
                        if ($parameter->isDefaultValueAvailable()) {
                            $callbackArgs[] = $parameter->getDefaultValue();
                        } else {
                            $callbackArgs[] = null;
                        }
                    }
                }
                
                return call_user_func_array($callback, $callbackArgs);
                
            } catch (\ReflectionException $e) {
                // Fallback to simple argument slicing if reflection fails
                Log::warning("Reflection failed for hook callback, using fallback method", [
                    'error' => $e->getMessage()
                ]);
                
                // Convert associative array to positional array if needed
                if (array_keys($args) !== range(0, count($args) - 1)) {
                    // This is an associative array, convert to positional
                    $callbackArgs = array_values($args);
                } else {
                    $callbackArgs = $args;
                }
                
                $callbackArgs = array_slice($callbackArgs, 0, $acceptedArgs);
                return call_user_func_array($callback, $callbackArgs);
            }
        } else {
            // For string callbacks (class methods), use simple argument slicing
            // Convert associative array to positional array if needed
            if (array_keys($args) !== range(0, count($args) - 1)) {
                // This is an associative array, convert to positional
                $callbackArgs = array_values($args);
            } else {
                $callbackArgs = $args;
            }
            
            $callbackArgs = array_slice($callbackArgs, 0, $acceptedArgs);
            return call_user_func_array($callback, $callbackArgs);
        }
    }

    /**
     * Check if any hooks are registered for a specific hook
     *
     * @param string $hook The hook name
     * @return bool True if hooks are registered for the hook
     */
    public static function hasAction(string $hook): bool
    {
        return isset(self::$hooks[$hook]) && !empty(self::$hooks[$hook]);
    }

    /**
     * Check if any filters are registered for a specific hook
     *
     * @param string $hook The hook name
     * @return bool True if filters are registered for the hook
     */
    public static function hasFilter(string $hook): bool
    {
        return self::hasAction($hook); // Filters and actions use the same storage
    }

    /**
     * Get all registered hooks
     *
     * @param string|null $hook Optional hook name to filter by
     * @return array All registered hooks, or hooks for the specified hook name
     */
    public static function getHooks(?string $hook = null): array
    {
        if ($hook !== null) {
            return self::$hooks[$hook] ?? [];
        }
        
        return self::$hooks;
    }

    /**
     * Clear all hooks (useful for testing)
     *
     * @param string|null $hook Optional hook name to clear hooks for a specific hook
     */
    public static function clearHooks(?string $hook = null): void
    {
        if ($hook !== null) {
            if (isset(self::$hooks[$hook])) {
                unset(self::$hooks[$hook]);
                Log::info("Hooks cleared for hook: {$hook}");
            }
        } else {
            self::$hooks = [];
            Log::info("All hooks cleared");
        }
    }
}

