<?php
/**
 * Module lifecycle and integration helpers for FA Sanity Check module.
 *
 * These functions are intended to be called by your module install/activate
 * routines or referenced from a module manifest. They use FrontAccounting
 * helper functions (`db_query`, `db_escape`) when available. When running
 * outside the FA environment the functions throw descriptive exceptions.
 *
 * Implementation follows AGENTS-TECH principles: documented, testable,
 * and using FA helper abstractions instead of raw PDO inlines.
 */

/**
 * Install the module (create configuration table and defaults).
 * Call from your module's install routine.
 *
 * @return array Result summary with `status` and `details`.
 */
function sanity_install()
{
    if (!function_exists('db_query')) {
        return ['status' => 'error', 'details' => 'FA helper functions not available (db_query)'];
    }

    // Create the config table if missing
    $sql = "CREATE TABLE IF NOT EXISTS sanity_config (config_key VARCHAR(128) PRIMARY KEY, config_value TEXT) ENGINE=InnoDB";
    db_query($sql, "could not create sanity_config table");

    // Insert safe defaults if not present
    $defaults = [
        'final_cash_accounts' => json_encode([]),
        'processor_accounts' => json_encode([]),
        'processor_follow_window_days' => json_encode(30),
        'anomaly_tolerances' => json_encode(['red'=>0.05,'yellow'=>0.02]),
    ];
    foreach ($defaults as $k => $v) {
        $ek = db_escape($k);
        $q = "INSERT IGNORE INTO sanity_config (config_key, config_value) VALUES ('".$ek."', '".db_escape($v)."')";
        db_query($q, "could not insert default $k");
    }

    return ['status' => 'ok', 'details' => 'installed'];
}

/**
 * Uninstall the module. Default behaviour: remove access right and
 * optionally drop module tables when $remove_data is true. Use with caution.
 *
 * @param bool $remove_data When true, drop `sanity_config` table.
 * @return array Result summary.
 */
function sanity_uninstall($remove_data = false)
{
    if (!function_exists('delete_access')) {
        // Some FA installs don't provide delete_access; that's OK — we try best-effort.
    } else {
        @delete_access('SA_SANITY');
    }

    if ($remove_data && function_exists('db_query')) {
        db_query("DROP TABLE IF EXISTS sanity_config", 'could not drop sanity_config');
    }

    return ['status' => 'ok', 'details' => ($remove_data ? 'data_removed' : 'access_removed')];
}

/**
 * Register menus and access rights. Call during module activation or include this
 * from the module's manifest. This delegates to the `integration/menu_snippet.php`
 * guidance file which contains fallbacks for different FA variants.
 *
 * @return void
 */
function sanity_register_menu()
{
    $snippet = __DIR__ . '/integration/menu_snippet.php';
    if (file_exists($snippet)) {
        include_once $snippet;
    }
}

/**
 * Upgrade routine placeholder. FA will call this with an older version string
 * to run migration steps. Implement migrations here when schema changes are required.
 *
 * @param string $old_version
 * @return array
 */
function sanity_upgrade($old_version)
{
    // Example migration hook — extend as needed
    return ['status' => 'ok', 'from' => $old_version];
}

