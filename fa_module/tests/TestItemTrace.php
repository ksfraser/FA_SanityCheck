<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../src/ItemTrace.php';

// Lightweight FA DB helper stubs for unit testing. These operate on an
// in-memory queue ($FA_SANITY_QUERY_STACK) of result sets to allow tests
// to control what `db_query`/`db_fetch` return without a real DB.
global $FA_SANITY_QUERY_STACK;
$FA_SANITY_QUERY_STACK = [];

function db_query($sql)
{
    global $FA_SANITY_QUERY_STACK;
    $rows = array_shift($FA_SANITY_QUERY_STACK);
    $obj = new stdClass();
    $obj->rows = is_array($rows) ? $rows : [];
    $obj->pos = 0;
    return $obj;
}

function db_fetch($res)
{
    if (!is_object($res) || !isset($res->rows)) return false;
    if ($res->pos >= count($res->rows)) return false;
    return $res->rows[$res->pos++];
}

function db_escape($v)
{
    return addslashes((string)$v);
}

class TestItemTrace extends TestCase
{
    public function testFifoSimple()
    {
        global $FA_SANITY_QUERY_STACK;

        // Two receipts: 10 and 5
        $in = [
            ['id'=>1,'doc_type'=>'GRN','doc_no'=>'GRN1','transaction_date'=>'2026-01-01','qty'=>10],
            ['id'=>2,'doc_type'=>'GRN','doc_no'=>'GRN2','transaction_date'=>'2026-01-02','qty'=>5],
        ];

        // One outbound sale of 12
        $out = [
            ['id'=>3,'doc_type'=>'INV','doc_no'=>'INV1','transaction_date'=>'2026-02-01','qty'=>12],
        ];

        // db_query will return $in first, then $out (matching ItemTrace expectations)
        $FA_SANITY_QUERY_STACK = [$in, $out];

        $it = new \FA\Sanity\ItemTrace();
        $res = $it->fifoConsume(123, 1);

        $this->assertIsArray($res);
        // Expect two assignment rows: 10 (from first receipt), 2 (from second receipt)
        $this->assertCount(2, $res);
        $this->assertEquals(10, $res[0]['assigned_qty']);
        $this->assertEquals(2, $res[1]['assigned_qty']);
    }
}
