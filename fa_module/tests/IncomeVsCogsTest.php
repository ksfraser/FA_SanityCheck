<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../src/IncomeVsCogsDiagnostic.php';

class IncomeVsCogsTest extends TestCase
{
    public function testDetectsMissingAndSmallerIncome()
    {
        global $FA_SANITY_QUERY_STACK;

        // Prepare sanity_config rows: income_accounts and cogs_accounts
        $incomeAccounts = ['4000'];
        $cogsAccounts = ['5000'];
        $FA_SANITY_QUERY_STACK = [
            [
                ['config_key' => 'income_accounts', 'config_value' => json_encode($incomeAccounts)],
                ['config_key' => 'cogs_accounts', 'config_value' => json_encode($cogsAccounts)],
            ],
            // Rows returned by the diagnostic SELECT (joined rows)
            [
                // income less than abs(cogs)
                ['type' => 10, 'type_no' => 1, 'tran_date' => '2026-01-01', 'income_account' => '4000', 'income_amount' => '100.00', 'cogs_account' => '5000', 'cogs_amount' => '-120.00'],
                // no cogs found
                ['type' => 10, 'type_no' => 2, 'tran_date' => '2026-01-02', 'income_account' => '4000', 'income_amount' => '200.00'],
            ],
        ];

        $diag = new FA\Sanity\IncomeVsCogsDiagnostic();
        $results = $diag->incomeVsCogs('2026-01-01', '2026-01-31', 0.0);

        $this->assertIsArray($results);
        $this->assertCount(2, $results);
        $flags = array_column($results, 'flag');
        $this->assertContains('income_less_than_cogs', $flags);
        $this->assertContains('no_cogs_found', $flags);
    }
}
