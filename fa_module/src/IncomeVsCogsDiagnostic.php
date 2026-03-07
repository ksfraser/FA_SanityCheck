<?php
namespace FA\Sanity;

/**
 * IncomeVsCogsDiagnostic
 *
 * Extracted from TrialBalanceDiagnostics: focused sanity check matching
 * income GL lines to COGS GL lines by transaction. Returns findings.
 */
class IncomeVsCogsDiagnostic
{
    /**
     * Attempts to match income GL lines to COGS GL lines by transaction (type/type_no).
     * Flags rows where no COGS line exists or income < COGS (beyond tolerance).
     * @param string|null $start_date YYYY-MM-DD
     * @param string|null $end_date YYYY-MM-DD
     * @param float $tolerance positive tolerance (e.g., 0.01)
     * @return array list of findings or ['error'=>string]
     */
    public function incomeVsCogs($start_date = null, $end_date = null, $tolerance = 0.0)
    {
        // load configured accounts from sanity_config when present
        $incomeAccounts = [];
        $cogsAccounts = [];
        $cfgRes = db_query("SELECT config_key, config_value FROM sanity_config WHERE config_key IN ('income_accounts','cogs_accounts')");
        while ($c = db_fetch($cfgRes)) {
            if ($c['config_key'] === 'income_accounts') $incomeAccounts = json_decode($c['config_value'], true) ?? [];
            if ($c['config_key'] === 'cogs_accounts') $cogsAccounts = json_decode($c['config_value'], true) ?? [];
        }

        if (!$incomeAccounts || !$cogsAccounts) {
            return ['error' => 'income_accounts or cogs_accounts not configured in sanity_config'];
        }

        // build IN lists
        $incomeIn = implode(',', array_map(function($a){ return "'".db_escape($a)."'"; }, $incomeAccounts));
        $cogsIn = implode(',', array_map(function($a){ return "'".db_escape($a)."'"; }, $cogsAccounts));

        $whereDate = '';
        if ($start_date) $whereDate .= " AND gi.tran_date >= '".db_escape($start_date)."'";
        if ($end_date) $whereDate .= " AND gi.tran_date <= '".db_escape($end_date)."'";

        $q = "SELECT gi.type, gi.type_no, gi.tran_date, gi.account AS income_account, gi.amount AS income_amount, gc.account AS cogs_account, gc.amount AS cogs_amount
              FROM gl_trans gi
              LEFT JOIN gl_trans gc ON gc.type = gi.type AND gc.type_no = gi.type_no AND gc.account IN ($cogsIn)
              WHERE gi.account IN ($incomeIn) $whereDate
              ORDER BY gi.tran_date DESC LIMIT 1000";

        $res = db_query($q);
        $findings = [];
        while ($r = db_fetch($res)) {
            $income = floatval($r['income_amount']);
            $cogs = isset($r['cogs_amount']) ? floatval($r['cogs_amount']) : null;
            $flag = null;
            if ($cogs === null) {
                $flag = 'no_cogs_found';
            } else {
                // consider signs: income typically positive, cogs typically negative debit
                // Use absolute comparison: income >= abs(cogs) within tolerance
                if ($income + $tolerance < abs($cogs)) {
                    $flag = 'income_less_than_cogs';
                }
            }
            if ($flag !== null) {
                $findings[] = [
                    'type'=>$r['type'],'type_no'=>$r['type_no'],'tran_date'=>$r['tran_date'],
                    'income_account'=>$r['income_account'],'income_amount'=>$income,
                    'cogs_account'=>$r['cogs_account'] ?? null,'cogs_amount'=>$cogs,'flag'=>$flag
                ];
            }
        }

        return $findings;
    }
}
