<?php
// PHPUnit bootstrap for Sanity module tests
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Include Composer autoload if available (enables PSR-4 autoload during CI)
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

// Lightweight FA DB helper stubs used by tests when a real FA environment is not present.
global $FA_SANITY_QUERY_STACK;
$FA_SANITY_QUERY_STACK = [];

if (!function_exists('db_query')) {
    function db_query($sql)
    {
        global $FA_SANITY_QUERY_STACK;
        $rows = array_shift($FA_SANITY_QUERY_STACK);
        $obj = new stdClass();
        $obj->rows = is_array($rows) ? $rows : [];
        $obj->pos = 0;
        return $obj;
    }
}

if (!function_exists('db_fetch')) {
    function db_fetch($res)
    {
        if (!is_object($res) || !isset($res->rows)) return false;
        if ($res->pos >= count($res->rows)) return false;
        return $res->rows[$res->pos++];
    }
}

if (!function_exists('db_escape')) {
    function db_escape($v)
    {
        return addslashes((string)$v);
    }
}

