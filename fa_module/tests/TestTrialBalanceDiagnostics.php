<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../src/TrialBalanceDiagnostics.php';

global $FA_SANITY_QUERY_STACK;
$FA_SANITY_QUERY_STACK = [];

class TestTrialBalanceDiagnostics extends TestCase
{
    public function testFindUnpostedJournals()
    {
        global $FA_SANITY_QUERY_STACK;

        $sample = [ ['id'=>1,'tran_date'=>'2026-02-01','amount'=>100.0,'narration'=>'test','posted'=>0] ];
        $FA_SANITY_QUERY_STACK = [$sample];

        $diag = new \FA\Sanity\TrialBalanceDiagnostics();
        $res = $diag->runDiagnostics(null);

        $this->assertArrayHasKey('unposted_journals', $res);
        $this->assertNotEmpty($res['unposted_journals']);
        $this->assertEquals(1, $res['unposted_journals'][0]['id']);
    }
}
