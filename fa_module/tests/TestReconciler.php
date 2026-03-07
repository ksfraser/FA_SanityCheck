<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../src/Reconciler.php';

global $FA_SANITY_QUERY_STACK;
$FA_SANITY_QUERY_STACK = [];

function db_query($sql)
{
    global $FA_SANITY_QUERY_STACK;
    $rows = array_shift($FA_SANITY_QUERY_STACK);
    $obj = new stdClass(); $obj->rows = is_array($rows) ? $rows : []; $obj->pos = 0; return $obj;
}

function db_fetch($res) { if (!is_object($res) || !isset($res->rows)) return false; if ($res->pos >= count($res->rows)) return false; return $res->rows[$res->pos++]; }
function db_escape($v) { return addslashes((string)$v); }

class TestReconciler extends TestCase
{
    public function testReconcileBankDetectsDiffs()
    {
        global $FA_SANITY_QUERY_STACK;

        // Simulate one bank row with bank_sum 100, gl_sum 90
        $FA_SANITY_QUERY_STACK = [ [ ['bank_act'=>'bank_1','bank_sum'=>100,'gl_sum'=>90] ] ];

        $r = new \FA\Sanity\Reconciler();
        $res = $r->reconcileBank('2026Q1');

        $this->assertCount(1, $res);
        $this->assertEquals(10.00, $res[0]['diff']);
    }
}
