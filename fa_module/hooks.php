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
class SanityHooks
{
    /**
     * Install hook called by FA module installer.
     * Keep DDL/migrations in SQL files; do lightweight registration here.
     * @return array
     */
    public static function install()
    {
        if (!function_exists('db_query')) {
            return ['status' => 'error', 'details' => 'FA helper functions not available (db_query)'];
        }

        // Schema creation is handled by `sql/create_sanity_config_table.sql`.
        // Register access and menus if host provides helpers
        if (function_exists('add_access')) {
            @add_access('SA_SANITY', _('Sanity Check Access'));
        }
        if (function_exists('sanity_register_all_menus')) {
            @sanity_register_all_menus();
        }

        return ['status' => 'ok', 'details' => 'installed'];
    }

    /**
     * Uninstall hook.
     * @param bool $remove_data
     * @return array
     */
    public static function uninstall($remove_data = false)
    {
        if (!function_exists('delete_access')) {
            // best-effort
        } else {
            @delete_access('SA_SANITY');
        }

        if ($remove_data && function_exists('db_query')) {
            db_query("DROP TABLE IF EXISTS sanity_config", 'could not drop sanity_config');
        }

        return ['status' => 'ok', 'details' => ($remove_data ? 'data_removed' : 'access_removed')];
    }

    /**
     * Register standard menus (delegates to integration/menu_snippet.php)
     * @return void
     */
    public static function register_menu()
    {
        // Register access right
        if (function_exists('add_access')) {
            @add_access('SA_SANITY', _('Sanity Check Access'));
        }

        // Preferred: use add_menu_item when available
        if (function_exists('add_menu_item')) {
            @add_menu_item('Banking', _('Sanity Check Trace'), '/modules/fa_sanity/fa_module/pages/trace.php', 'SA_SANITY');
            @add_menu_item('Ledger', _('Sanity Check Admin'), '/modules/fa_sanity/fa_module/pages/admin_reconciliation_accounts.php', 'SA_SANITY');
            @add_menu_item('Reports', _('Income vs COGS Audit'), '/modules/fa_sanity/fa_module/pages/tb_income_vs_cogs.php', 'SA_SANITY');
            return;
        }

        // Fallbacks for other FA variants
        if (function_exists('add_menu')) {
            @add_menu('Banking', 'Sanity Check Trace', '/modules/fa_sanity/fa_module/pages/trace.php', 'SA_SANITY');
            @add_menu('Ledger', 'Sanity Check Admin', '/modules/fa_sanity/fa_module/pages/admin_reconciliation_accounts.php', 'SA_SANITY');
            @add_menu('Reports', 'Income vs COGS Audit', '/modules/fa_sanity/fa_module/pages/tb_income_vs_cogs.php', 'SA_SANITY');
            return;
        }

        // Last resort: attempt to inject into global $menu structure if present
        global $menu;
        if (isset($menu) && is_array($menu)) {
            foreach ($menu as &$mgroup) {
                if (is_array($mgroup) && isset($mgroup['name'])) {
                    if (strcasecmp($mgroup['name'], 'Banking') === 0) {
                        $mgroup['items'][] = ['label'=>_('Sanity Check Trace'), 'url'=>'/modules/fa_sanity/fa_module/pages/trace.php', 'access'=>'SA_SANITY'];
                    }
                    if (strcasecmp($mgroup['name'], 'Ledger') === 0) {
                        $mgroup['items'][] = ['label'=>_('Sanity Check Admin'), 'url'=>'/modules/fa_sanity/fa_module/pages/admin_reconciliation_accounts.php', 'access'=>'SA_SANITY'];
                    }
                    if (strcasecmp($mgroup['name'], 'Reports') === 0) {
                        $mgroup['items'][] = ['label'=>_('Income vs COGS Audit'), 'url'=>'/modules/fa_sanity/fa_module/pages/tb_income_vs_cogs.php', 'access'=>'SA_SANITY'];
                    }
                }
            }
        }
    }

    /**
     * Register admin menu helpers when host exposes menu APIs.
     * @return void
     */
    public static function register_admin_menu()
    {
        if (!function_exists('add_access') || !function_exists('add_menu_item')) {
            return;
        }
        @add_access('SA_SANITY', _('Sanity Check Access'));
        try {
            add_menu_item('modules', 'Sanity Check', 'admin_reconciliation_accounts.php', 'SA_SANITY');
        } catch (\Throwable $e) {
            if (function_exists('add_context_menu')) {
                @add_context_menu('modules', 'Sanity Check', 'admin_reconciliation_accounts.php');
            }
        }
    }

    /**
     * Convenience to register both snippet and admin menu.
     */
    public static function register_all_menus()
    {
        self::register_menu();
        if (function_exists('SanityHooks::register_admin_menu')) {
            @self::register_admin_menu();
        }
    }

    /**
     * Upgrade hook placeholder.
     * @param string $old_version
     * @return array
     */
    public static function upgrade($old_version)
    {
        return ['status' => 'ok', 'from' => $old_version];
    }
}

// Note: FA manifest should reference the `SanityHooks` class methods directly
// if your FA variant supports class-based hooks. Example manifest entries:
//  - install:  ["FA\\Sanity\\SanityHooks", "install"]
//  - uninstall: ["FA\\Sanity\\SanityHooks", "uninstall"]
//  - upgrade: ["FA\\Sanity\\SanityHooks", "upgrade"]

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

// Backwards-compatible registration that also attempts to add admin menu.
function sanity_register_all_menus()
{
    sanity_register_menu();
    if (function_exists('sanity_register_admin_menu')) {
        @sanity_register_admin_menu();
    }
}

// Add a convenience admin menu registration when running inside FA that
// supports `add_access` and `add_menu_item` style helpers. This is a
// conservative addition that will silently no-op when the host FA doesn't
// expose these functions.
function sanity_register_admin_menu()
{
    if (!function_exists('add_access') || !function_exists('add_menu_item')) {
        return; // host doesn't provide the menu helpers
    }

    // Register a capability and add a menu entry under Setup -> Modules
    @add_access('SA_SANITY', 1);
    // try to add a menu item; some FA variants expose different APIs — keep defensive
    try {
        add_menu_item('modules', 'Sanity Check', 'admin_reconciliation_accounts.php', 'SA_SANITY');
    } catch (\Throwable $e) {
        // fall back to legacy helper if available
        if (function_exists('add_context_menu')) {
            @add_context_menu('modules', 'Sanity Check', 'admin_reconciliation_accounts.php');
        }
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

