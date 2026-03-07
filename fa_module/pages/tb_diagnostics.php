<?php
// Simple FA page to run Trial Balance Diagnostics and output JSON results.
require_once __DIR__ . '/../../src/TrialBalanceDiagnostics.php';

use FA\Sanity\TrialBalanceDiagnostics;

if (!function_exists('db_query')) {
    echo "<p>This page must be run inside FrontAccounting (db_query missing).</p>";
    exit;
}

// Internal module navigation
$menuSnippet = __DIR__ . '/../integration/menu_snippet.php';
if (file_exists($menuSnippet)) {
    include_once $menuSnippet;
    if (function_exists('sanity_render_nav')) sanity_render_nav();
}

$diag = new TrialBalanceDiagnostics();
$period = isset($_GET['period_no']) ? (int)$_GET['period_no'] : null;
$res = $diag->runDiagnostics($period);

header('Content-Type: application/json');
echo json_encode($res, JSON_PRETTY_PRINT);
