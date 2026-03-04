<?php
// CSV export for Item Trace

require __DIR__ . '/../src/ItemTrace.php';

$stock = isset($_GET['stock_id']) ? $_GET['stock_id'] : null;
$loc = isset($_GET['location_id']) ? $_GET['location_id'] : null;
if (!$stock || !$loc) { http_response_code(400); echo "Missing stock_id or location_id"; exit; }

if (!function_exists('db_query')) { http_response_code(500); echo "This export must run inside FrontAccounting environment."; exit; }

$it = new FA\Sanity\ItemTrace();
$assignments = $it->fifoConsume($stock, $loc);

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="item_trace_'.preg_replace('/[^A-Za-z0-9_-]/','', $stock).'_'.preg_replace('/[^A-Za-z0-9_-]/','',$loc).'.csv"');
$out = fopen('php://output','w');
fputcsv($out, ['in_doc_type','in_doc_no','in_date','in_qty','assigned_qty','out_doc_type','out_doc_no','out_date']);
foreach ($assignments as $a) {
    fputcsv($out, [$a['in_doc_type']??'',$a['in_doc_no']??'',$a['in_date']??'',$a['in_qty']??'',$a['assigned_qty']??'',$a['out_doc_type']??'',$a['out_doc_no']??'',$a['out_date']??'']);
}
fclose($out);
exit;
