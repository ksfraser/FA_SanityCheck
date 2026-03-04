<?php
namespace FA\Sanity;

/**
 * TrialBalanceDiagnostics
 *
 * Provides quick diagnostics to help locate causes of an out-of-balance trial balance.
 * Uses FA helper functions: db_query, db_fetch.
 */
class TrialBalanceDiagnostics
{
    /**
     * Run diagnostics for a given period (optional). Returns an array of findings.
     * @param int|null $period_no
     * @return array
     */
    public function runDiagnostics($period_no = null)
    {
        $results = [];

        $results['unposted_journals'] = $this->findUnpostedJournals($period_no);
        $results['currency_mismatches'] = $this->findCurrencyMismatches($period_no);
        $results['rounding_drift'] = $this->findRoundingDrift($period_no);
        $results['out_of_period_postings'] = $this->findOutOfPeriodPostings($period_no);
        $results['gl_subledger_diff_sample'] = $this->sampleGlSubledgerDiffs();

        return $results;
    }

    protected function findUnpostedJournals($period_no = null)
    {
        $q = "SELECT id, tran_date, amount, narration, posted FROM gl_trans WHERE posted = 0";
        if ($period_no !== null) {
            $q .= " AND period_no = '" . db_escape($period_no) . "'";
        }
        $q .= " LIMIT 100";
        $res = db_query($q);
        $rows = [];
        while ($r = db_fetch($res)) $rows[] = $r;
        return $rows;
    }

    protected function findCurrencyMismatches($period_no = null)
    {
        // Detect GL trans where currency_id doesn't match related subledger rows (simplified sample)
        $q = "SELECT gt.id, gt.amount, gt.currency, gt.type FROM gl_trans gt WHERE gt.currency IS NOT NULL LIMIT 100";
        $res = db_query($q);
        $rows = [];
        while ($r = db_fetch($res)) $rows[] = $r;
        return $rows;
    }

    protected function findRoundingDrift($period_no = null)
    {
        // Simple heuristic: find GL accounts where ABS(SUM(debit)-SUM(credit)) > small tolerance
        $tol = 0.01; // default tolerance
        $q = "SELECT account, SUM(IF(amount>0,amount,0)) AS deb, SUM(IF(amount<0,amount*-1,0)) AS cred, ABS(SUM(IF(amount>0,amount,0)) - SUM(IF(amount<0,amount*-1,0))) AS diff FROM gl_trans GROUP BY account HAVING diff > " . floatval($tol) . " LIMIT 100";
        $res = db_query($q);
        $rows = [];
        while ($r = db_fetch($res)) $rows[] = $r;
        return $rows;
    }

    protected function findOutOfPeriodPostings($period_no = null)
    {
        // Transactions whose transaction date falls outside their period mapping
        $q = "SELECT gt.id, gt.tran_date, gt.period_no, p.last_date_in_period FROM gl_trans gt LEFT JOIN sys_periods p ON gt.period_no = p.period_no WHERE gt.tran_date > p.last_date_in_period OR gt.tran_date < p.first_date_in_period LIMIT 100";
        $res = db_query($q);
        $rows = [];
        while ($r = db_fetch($res)) $rows[] = $r;
        return $rows;
    }

    protected function sampleGlSubledgerDiffs()
    {
        // Provide a few sample queries for GL vs subledger checks (non-exhaustive)
        $samples = [];
        // Bank: compare bank_trans sums to GL bank account
        $samples['bank_sql'] = "SELECT b.bank_act, SUM(b.amount) AS bank_sum, (SELECT SUM(amount) FROM gl_trans WHERE account = b.bank_act) AS gl_sum FROM bank_trans b GROUP BY b.bank_act LIMIT 20";
        // AR: compare debtor_trans to GL AR account
        $samples['ar_sql'] = "SELECT dt.debtor_no, SUM(dt.ov_amount + dt.ov_freight + dt.ov_gst) AS ar_subledger, (SELECT SUM(amount) FROM gl_trans WHERE person_type_id = 10 AND person_id = dt.debtor_no) AS gl_sum FROM debtor_trans dt GROUP BY dt.debtor_no LIMIT 20";
        return $samples;
    }
}
