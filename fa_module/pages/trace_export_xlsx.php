<?php
// XLSX export for Item Trace — uses PhpSpreadsheet if installed, otherwise falls back to CSV download
require __DIR__ . '/../src/ItemTrace.php';

if (!function_exists('db_query')) { http_response_code(500); echo "This export must run inside FrontAccounting environment."; exit; }

$stock = isset($_GET['stock_id']) ? $_GET['stock_id'] : null;
$loc = isset($_GET['location_id']) ? $_GET['location_id'] : null;
if (!$stock || !$loc) { http_response_code(400); echo "Missing stock_id or location_id"; exit; }

$it = new FA\Sanity\ItemTrace();
$assignments = $it->fifoConsume($stock, $loc);

if (class_exists('\PhpOffice\PhpSpreadsheet\Spreadsheet')) {
    // build spreadsheet
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Item Trace');
    $headers = ['Inbound Doc','In Date','Qty','Assigned Qty','Outbound Doc','Out Date'];
    $col = 1;
    foreach ($headers as $h) { $sheet->setCellValueByColumnAndRow($col++,1,$h); }
    $row = 2;
    foreach ($assignments as $a) {
        $sheet->setCellValueByColumnAndRow(1,$row,$a['in_doc_no'] ?? '');
        $sheet->setCellValueByColumnAndRow(2,$row,$a['in_date'] ?? '');
        $sheet->setCellValueByColumnAndRow(3,$row,$a['in_qty'] ?? '');
        $sheet->setCellValueByColumnAndRow(4,$row,$a['assigned_qty'] ?? '');
        $sheet->setCellValueByColumnAndRow(5,$row,$a['out_doc_no'] ?? '');
        $sheet->setCellValueByColumnAndRow(6,$row,$a['out_date'] ?? '');
        $row++;
    }

    // send as xlsx
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="item_trace_'.preg_replace('/[^A-Za-z0-9_-]/','', $stock).'_'.preg_replace('/[^A-Za-z0-9_-]/','',$loc).'.xlsx"');
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
} else {
    // fallback to CSV
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="item_trace_'.preg_replace('/[^A-Za-z0-9_-]/','', $stock).'_'.preg_replace('/[^A-Za-z0-9_-]/','',$loc).'.csv"');
    $out = fopen('php://output','w');
    fputcsv($out, ['Inbound Doc','In Date','Qty','Assigned Qty','Outbound Doc','Out Date']);
    foreach ($assignments as $a) {
        fputcsv($out, [$a['in_doc_no']??'',$a['in_date']??'',$a['in_qty']??'',$a['assigned_qty']??'',$a['out_doc_no']??'',$a['out_date']??'']);
    }
    fclose($out);
    exit;
}
