<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../src/SnapshotBuilder.php';

global $FA_SANITY_QUERY_STACK;
$FA_SANITY_QUERY_STACK = [];

function db_query($sql, $msg = null)
{
    global $FA_SANITY_QUERY_STACK;
    // return next prepared result set if available, else simulate success
    $rows = array_shift($FA_SANITY_QUERY_STACK);
    if ($rows === null) {
        // simulate a successful statement object for insert
        $obj = new stdClass(); $obj->rows = []; $obj->pos = 0; return $obj;
    }
    $obj = new stdClass(); $obj->rows = $rows; $obj->pos = 0; return $obj;
}

function db_fetch($res)
{
    if (!is_object($res) || !isset($res->rows)) return false;
    if ($res->pos >= count($res->rows)) return false;
    return $res->rows[$res->pos++];
}

function db_escape($v) { return addslashes((string)$v); }

class TestSnapshotBuilder extends TestCase
{
    public function testCreateSnapshotReturnsSummary()
    {
        global $FA_SANITY_QUERY_STACK;

        // After insert, builder issues a count query — prepare count result
        $FA_SANITY_QUERY_STACK = [
            // count result
            [['c' => 3]]
        ];

        $b = new \FA\Sanity\SnapshotBuilder();
        $res = $b->createSnapshot('2026Q1', '2026-01-01', '2026-03-31', 1);

        $this->assertIsArray($res);
        $this->assertEquals('2026Q1', $res['series']);
        $this->assertEquals(3, $res['rows_inserted']);
        $this->assertArrayHasKey('duration_s', $res);
    }
}
