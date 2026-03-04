<?php
/**
 * FA-integrated ItemTrace — uses FA DB helper functions (db_query, db_fetch) and requires running inside FA 2.3.X.
 *
 * This class implements the core traceability helpers used by the Sanity Check
 * module. Methods are documented for PHPDoc generation and unit testing.
 */
namespace FA\Sanity;

class ItemTrace
{
    /**
     * ItemTrace constructor.
     *
     * @throws \RuntimeException If required FA helper functions are not available.
     */
    public function __construct()
    {
        if (!function_exists('db_query')) {
            throw new \RuntimeException('ItemTrace requires FA DB helper functions (db_query). Run inside FrontAccounting environment.');
        }
    }

    /**
     * Consume stock using FIFO per location.
     *
     * This method queries `stock_moves` for inbound receipts and outbound
     * issues for the given `stock_id` and `location_id`, then performs a
     * deterministic FIFO allocation of outbound quantities to inbound
     * receipts. Unmatched outbound quantities are returned with null in_doc.
     *
     * @param int|string $stock_id Stock identifier (matches `stock_moves.stock_id`).
     * @param int|string $location_id Location identifier (matches `stock_moves.location_id`).
     * @return array[] Array of assignment records with keys: in_doc_type,in_doc_no,in_date,in_qty,assigned_qty,out_doc_type,out_doc_no,out_date
     */
    public function fifoConsume($stock_id, $location_id)
    {
        $s_stock = db_escape($stock_id);
        $s_loc = db_escape($location_id);

        $in_q = "SELECT id, doc_type, doc_no, transaction_date, qty FROM stock_moves WHERE stock_id = '".$s_stock."' AND location_id = '".$s_loc."' AND move_type IN ('receipt','transfer_in') ORDER BY transaction_date, id";
        $out_q = "SELECT id, doc_type, doc_no, transaction_date, qty FROM stock_moves WHERE stock_id = '".$s_stock."' AND location_id = '".$s_loc."' AND move_type IN ('issue','sale','transfer_out') ORDER BY transaction_date, id";

        $inRes = db_query($in_q);
        $in = [];
        while ($r = db_fetch($inRes)) { $r['remaining'] = (float)$r['qty']; $in[] = $r; }

        $outRes = db_query($out_q);
        $out = [];
        while ($r = db_fetch($outRes)) $out[] = $r;

        $results = [];
        foreach ($out as $o) {
            $qtyToAssign = (float)$o['qty'];
            foreach ($in as & $b) {
                if ($qtyToAssign <= 0) break;
                if ($b['remaining'] <= 0) continue;
                $assign = min($b['remaining'], $qtyToAssign);
                $results[] = [
                    'in_doc_type' => $b['doc_type'],
                    'in_doc_no' => $b['doc_no'],
                    'in_date' => $b['transaction_date'],
                    'in_qty' => $b['qty'],
                    'assigned_qty' => $assign,
                    'out_doc_type' => $o['doc_type'],
                    'out_doc_no' => $o['doc_no'],
                    'out_date' => $o['transaction_date']
                ];
                $b['remaining'] -= $assign;
                $qtyToAssign -= $assign;
            }
            if ($qtyToAssign > 0) {
                $results[] = [
                    'in_doc_type' => null,
                    'in_doc_no' => null,
                    'in_date' => null,
                    'in_qty' => 0,
                    'assigned_qty' => 0,
                    'out_doc_type' => $o['doc_type'],
                    'out_doc_no' => $o['doc_no'],
                    'out_date' => $o['transaction_date']
                ];
            }
        }

        return $results;
    }

    /**
     * Follow a payment through processor accounts to final bank deposit.
     *
     * Uses `sanity_config` driven account lists when available via $config.
     *
     * @param int|string $payment_id The payment identifier to follow.
     * @param array $config Optional configuration overrides: `final_cash_accounts` (array), `processor_accounts` (array), `processor_follow_window_days` (int).
     * @return array Status structure with keys like `status`, `initial_account`, `final_deposit_id`, `fee_amount`.
     */
    public function followPayment($payment_id, array $config = [])
    {
        $final = isset($config['final_cash_accounts']) ? $config['final_cash_accounts'] : [];
        $processors = isset($config['processor_accounts']) ? $config['processor_accounts'] : [];
        $window = isset($config['processor_follow_window_days']) ? (int)$config['processor_follow_window_days'] : 30;

        $p_id = db_escape($payment_id);
        $q = "SELECT bt.id, bt.account_id, bt.transaction_date, bt.amount FROM bank_trans bt JOIN payments p ON p.bank_trans_id = bt.id WHERE p.id = '".$p_id."' LIMIT 1";
        $res = db_query($q);
        $row = db_fetch($res);
        if (!$row) return ['status'=>'no_initial_txn','payment_id'=>$payment_id];

        $initialAccount = $row['account_id'];
        $initialDate = $row['transaction_date'];
        $initialAmount = (float)$row['amount'];

        $isProcessor = in_array($initialAccount, $processors, true);

        if (!$isProcessor && in_array($initialAccount, $final, true)) {
            return ['status'=>'final_direct','payment_id'=>$payment_id,'initial_account'=>$initialAccount,'initial_date'=>$initialDate,'initial_amount'=>$initialAmount];
        }

        if ($isProcessor) {
            $from = db_escape($initialAccount);
            $d1 = db_escape($initialDate);
            $q2 = "SELECT bt2.id, bt2.transaction_date, bt2.amount, bt2.to_account FROM bank_trans bt2 WHERE bt2.from_account = '".$from."' AND bt2.transaction_date BETWEEN '".$d1."' AND DATE_ADD('".$d1."', INTERVAL " . intval($window) . " DAY) LIMIT 1";
            $r2 = db_query($q2);
            $t = db_fetch($r2);
            if (!$t) {
                return ['status'=>'processor_hold','payment_id'=>$payment_id,'initial_account'=>$initialAccount,'initial_date'=>$initialDate,'initial_amount'=>$initialAmount,'processor_account'=>$initialAccount];
            }
            $dep_id = db_escape($t['id']);
            $feeQ = "SELECT COALESCE(SUM(amount * -1),0) AS fee_sum FROM bank_trans WHERE deposit_id = '".$dep_id."' AND amount < 0";
            $feeR = db_query($feeQ);
            $feeRow = db_fetch($feeR);
            $fee = $feeRow ? (float)$feeRow['fee_sum'] : 0.0;
            return ['status'=>'settled','payment_id'=>$payment_id,'initial_account'=>$initialAccount,'initial_date'=>$initialDate,'initial_amount'=>$initialAmount,'final_deposit_id'=>$t['id'],'final_deposit_date'=>$t['transaction_date'],'final_deposit_amount'=>(float)$t['amount'],'fee_amount'=>$fee];
        }

        return ['status'=>'unknown_route','payment_id'=>$payment_id,'initial_account'=>$initialAccount,'initial_date'=>$initialDate,'initial_amount'=>$initialAmount];
    }
}

