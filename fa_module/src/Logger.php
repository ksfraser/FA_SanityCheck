<?php
namespace FA\Sanity;

/**
 * Lightweight logger and event helper that delegates to available infrastructure.
 */
class Logger
{
    public static function info($msg, array $meta = [])
    {
        self::log('info', $msg, $meta);
    }

    public static function error($msg, array $meta = [])
    {
        self::log('error', $msg, $meta);
    }

    public static function log($level, $msg, array $meta = [])
    {
        $payload = json_encode(array_merge(['msg'=>$msg], $meta));
        if (function_exists('kflog')) {
            kflog($level, $payload);
            return;
        }
        error_log('[sanity]['.$level.'] '.$payload);
    }

    /**
     * Fire an event via available hook system (fa-hooks or generic fire_event)
     */
    public static function fireEvent($name, $payload = [])
    {
        if (function_exists('fa_fire_event')) {
            try { fa_fire_event($name, $payload); } catch (\Throwable $e) {}
            return;
        }
        if (function_exists('fire_event')) {
            try { fire_event($name, $payload); } catch (\Throwable $e) {}
            return;
        }
        // else no-op
    }
}
