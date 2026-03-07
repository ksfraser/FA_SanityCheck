<?php
/**
 * Menu integration snippet for FA 2.3.X.
 *
 * Place the relevant calls into your module's install/activate routine or into the appropriate menu registration area.
 * This is a guidance snippet — adapt API calls to your FA installation if function names differ.
 */

// Register access right
if (function_exists('add_access')) {
    add_access('SA_SANITY', _('Sanity Check Access'));
}

// Try common FA menu registration functions to place entries under Banking and Ledger
// 1) add_menu_item(parent, title, url, access)
if (function_exists('add_menu_item')) {
    add_menu_item('Banking', _('Sanity Check Trace'), '/modules/fa_sanity/fa_module/pages/trace.php', 'SA_SANITY');
    add_menu_item('Ledger', _('Sanity Check Admin'), '/modules/fa_sanity/fa_module/pages/admin_reconciliation_accounts.php', 'SA_SANITY');
}
// 2) add_menu(parent, title, url, access) — some FA forks use different helper names
elseif (function_exists('add_menu')) {
    add_menu('Banking', 'Sanity Check Trace', '/modules/fa_sanity/fa_module/pages/trace.php', 'SA_SANITY');
    add_menu('Ledger', 'Sanity Check Admin', '/modules/fa_sanity/fa_module/pages/admin_reconciliation_accounts.php', 'SA_SANITY');
}
// 3) Directly modify $menu structure (last resort) — many FA installs build $menu arrays in code
elseif (isset($menu) && is_array($menu)) {
    // Append entries under Banking and Ledger groups if present
    foreach ($menu as &$mgroup) {
        if (is_array($mgroup) && isset($mgroup['name'])) {
                if (strcasecmp($mgroup['name'], 'Banking') === 0) {
                $mgroup['items'][] = ['label'=>_('Sanity Check Trace'), 'url'=>'/modules/fa_sanity/fa_module/pages/trace.php', 'access'=>'SA_SANITY'];
            }
            if (strcasecmp($mgroup['name'], 'Ledger') === 0) {
                $mgroup['items'][] = ['label'=>_('Sanity Check Admin'), 'url'=>'/modules/fa_sanity/fa_module/pages/admin_reconciliation_accounts.php', 'access'=>'SA_SANITY'];
            }
        }
    }
}
else {
    // As a fallback, provide instructions for manual registration in FA module manifest
    // Add these paths to your module descriptor or manifest for FA 2.3.X:
    // - /modules/fa_sanity/fa_module/pages/trace.php (Banking menu)
    // - /modules/fa_sanity/fa_module/admin/config.php (Ledger menu)
}

?>
