<?php
/**
 * PDF export for Income vs COGS diagnostic.
 */
require_once __DIR__ . '/../../src/IncomeVsCogsDiagnostic.php';

use FA\Sanity\IncomeVsCogsDiagnostic;

if (!function_exists('db_query')) {
    echo "<p>This page must be run inside FrontAccounting (db_query missing).</p>";
    exit;
}

$start = $_GET['start'] ?? null;
$end = $_GET['end'] ?? null;
$tol = isset($_GET['tolerance']) ? floatval($_GET['tolerance']) : 0.0;

$diag = new IncomeVsCogsDiagnostic();
$results = $diag->incomeVsCogs($start, $end, $tol);

if (isset($results['error'])) {
    echo '<p>Error: '.htmlspecialchars($results['error']).'</p>';
    exit;
}

$html = '<html><head><meta charset="utf-8"><style>table{border-collapse:collapse}td,th{border:1px solid #ccc;padding:6px}</style></head><body>';
$html .= '<h1>Income vs COGS Audit</h1>';
$html .= '<p>Period: ' . htmlspecialchars($start) . ' - ' . htmlspecialchars($end) . ' | Tolerance: ' . htmlspecialchars($tol) . '</p>';
$html .= '<table><tr><th>Date</th><th>Type</th><th>Type No</th><th>Income Acc</th><th>Income</th><th>COGS Acc</th><th>COGS</th><th>Flag</th></tr>';
foreach ($results as $r) {
    $html .= '<tr>';
    $html .= '<td>'.htmlspecialchars($r['tran_date']).'</td>';
    $html .= '<td>'.htmlspecialchars($r['type']).'</td>';
    $html .= '<td>'.htmlspecialchars($r['type_no']).'</td>';
    $html .= '<td>'.htmlspecialchars($r['income_account']).'</td>';
    $html .= '<td>'.htmlspecialchars($r['income_amount']).'</td>';
    $html .= '<td>'.htmlspecialchars($r['cogs_account'] ?? '').'</td>';
    $html .= '<td>'.htmlspecialchars($r['cogs_amount'] ?? '').'</td>';
    $html .= '<td>'.htmlspecialchars($r['flag']).'</td>';
    $html .= '</tr>';
}
$html .= '</table></body></html>';

// Try wkhtmltopdf if configured
$wk = null;
$res = db_query("SELECT config_value FROM sanity_config WHERE config_key='wkhtmltopdf_path' LIMIT 1");
if ($r = db_fetch($res)) $wk = $r['config_value'];

if ($wk && file_exists($wk)) {
    // write temp HTML and call wkhtmltopdf
    $tmpHtml = tempnam(sys_get_temp_dir(), 'fa_sanity_html_') . '.html';
    $tmpPdf = tempnam(sys_get_temp_dir(), 'fa_sanity_pdf_') . '.pdf';
    file_put_contents($tmpHtml, $html);
    $cmd = escapeshellarg($wk) . ' ' . escapeshellarg($tmpHtml) . ' ' . escapeshellarg($tmpPdf);
    @exec($cmd, $out, $rc);
    if ($rc === 0 && file_exists($tmpPdf)) {
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="income_vs_cogs.pdf"');
        readfile($tmpPdf);
        @unlink($tmpHtml);
        @unlink($tmpPdf);
        exit;
    }
}

// Fallback to Dompdf if installed via Composer
if (file_exists(__DIR__ . '/../../vendor/autoload.php')) {
    require_once __DIR__ . '/../../vendor/autoload.php';
    if (class_exists('\Dompdf\Dompdf')) {
        $dom = new \Dompdf\Dompdf();
        $dom->loadHtml($html);
        $dom->setPaper('A4', 'landscape');
        $dom->render();
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="income_vs_cogs.pdf"');
        echo $dom->output();
        exit;
    }
}

// Otherwise output HTML
echo $html;
