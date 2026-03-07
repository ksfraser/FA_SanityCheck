<?php
/**
 * Module-internal navigation snippet for FA Sanity Check.
 *
 * This file no longer attempts to register entries in the host FA menus.
 * Instead it provides helper functions that return a list of internal
 * module page links which can be rendered inside the module UI to allow
 * convenient navigation between the module's pages.
 */

if (!function_exists('sanity_module_links')) {
    function sanity_module_links()
    {
        return [
            ['label' => 'Trace Viewer', 'url' => '/modules/fa_sanity/fa_module/pages/trace.php'],
            ['label' => 'Snapshots', 'url' => '/modules/fa_sanity/fa_module/pages/snapshots.php'],
            ['label' => 'Reconcile', 'url' => '/modules/fa_sanity/fa_module/pages/reconcile.php'],
            ['label' => 'Income vs COGS Audit', 'url' => '/modules/fa_sanity/fa_module/pages/tb_income_vs_cogs.php'],
            ['label' => 'Admin: Reconciliation Accounts', 'url' => '/modules/fa_sanity/fa_module/pages/admin_reconciliation_accounts.php'],
        ];
    }
}

if (!function_exists('sanity_render_nav')) {
    function sanity_render_nav()
    {
        $links = sanity_module_links();
        // Minimal inline styles for consistent display inside FA pages
        echo '<style>.sanity-nav ul{list-style:none;padding:0;margin:0 0 1em 0;display:flex;gap:12px;align-items:center}.sanity-nav li{display:inline-block}.sanity-nav a{display:inline-block;padding:6px 10px;background:#f4f4f4;border:1px solid #ddd;border-radius:4px;text-decoration:none;color:#222}.sanity-nav a:hover{background:#e9ecef}</style>';
        echo "<nav class=\"sanity-nav\"><ul>";
        foreach ($links as $l) {
            echo '<li><a href="' . htmlspecialchars($l['url']) . '">' . htmlspecialchars(_($l['label'])) . '</a></li>';
        }
        echo "</ul></nav>";
    }
}

?>
