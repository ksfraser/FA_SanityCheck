<?php
require __DIR__ . '/../src/ItemTrace.php';

use FA\Sanity\ItemTrace;

$dsn = 'mysql:host=127.0.0.1;dbname=fa_uat;charset=utf8mb4';
$user = 'fa_user';
$pass = 'fa_pass';

try {
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
} catch (Exception $e) {
    echo "Cannot connect to DB: " . $e->getMessage() . PHP_EOL;
    exit(1);
}

$it = new ItemTrace($pdo);

// Example: run FIFO consumption for sample item
$fifo = $it->fifoConsume('ITEM-001', 'LOC1');
echo "FIFO assignments:\n";
print_r($fifo);

// Example: follow payment with id 1000
$cfg = [
    'final_cash_accounts' => ['BANK_MAIN'],
    'processor_accounts' => ['PROC_SQUARE'],
    'processor_follow_window_days' => 7
];

$pf = $it->followPayment(1000, $cfg);
echo "Payment follow result:\n";
print_r($pf);

// Exit
echo "Done.\n";

