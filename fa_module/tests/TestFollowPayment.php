<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../src/ItemTrace.php';

global $FA_SANITY_QUERY_STACK;

class TestFollowPayment extends TestCase
{
    public function testFollowProcessorToFinal()
    {
        global $FA_SANITY_QUERY_STACK;

        // 1) initial bank_trans row for payment
        $initial = [ ['id'=>10,'account_id'=>'proc_1','transaction_date'=>'2026-01-10','amount'=>100.00] ];

        // 2) processor -> final transfer found within window
        $transfer = [ ['id'=>20,'transaction_date'=>'2026-01-12','amount'=>95.00,'to_account'=>'bank_1'] ];

        // 3) fee lines for deposit (negative amounts)
        $fee = [ ['fee_sum'=>5.00] ];

        $FA_SANITY_QUERY_STACK = [$initial, $transfer, $fee];

        $it = new \FA\Sanity\ItemTrace();
        $res = $it->followPayment(999, ['final_cash_accounts'=>['bank_1'], 'processor_accounts'=>['proc_1'], 'processor_follow_window_days'=>30]);

        $this->assertIsArray($res);
        $this->assertEquals('settled', $res['status']);
        $this->assertEquals(5.0, $res['fee_amount']);
        $this->assertEquals(20, $res['final_deposit_id']);
    }
}
