<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../src/AdminConfig.php';

// Provide minimal DB helper stubs used by AdminConfig for tests
if (!function_exists('db_query')) {
    function db_query($sql, $err = null)
    {
        // very small in-memory simulation using static storage
        static $storage = [];
        // simple SELECT count or SELECT config_value handling
        if (stripos($sql, "SELECT config_value FROM sanity_config WHERE config_key='reconciliation_accounts'") !== false) {
            if (isset($storage['reconciliation_accounts'])) {
                return [['config_value' => $storage['reconciliation_accounts']]];
            }
            return [];
        }
        if (stripos($sql, 'SELECT COUNT(*) AS c FROM sanity_config') !== false) {
            return [['c' => isset($storage['reconciliation_accounts']) ? 1 : 0]];
        }
        if (stripos($sql, "UPDATE sanity_config SET config_value=") !== false) {
            // extract json between quotes
            if (preg_match("/'(.*)' WHERE config_key='reconciliation_accounts'/U", $sql, $m)) {
                $storage['reconciliation_accounts'] = stripslashes($m[1]);
            }
            return true;
        }
        if (stripos($sql, "INSERT INTO sanity_config") !== false) {
            if (preg_match("/VALUES \('reconciliation_accounts', '(.*)'\)/U", $sql, $m)) {
                $storage['reconciliation_accounts'] = stripslashes($m[1]);
            }
            return true;
        }
        return null;
    }
}

if (!function_exists('db_fetch')) {
    function db_fetch($res)
    {
        if (is_array($res) && isset($res[0])) {
            return $res[0];
        }
        return false;
    }
}

if (!function_exists('db_escape')) {
    function db_escape($v) { return addslashes($v); }
}

class TestAdminConfig extends TestCase
{
    public function testSaveAndGet()
    {
        $map = ['bank' => '1010', 'sales' => '4000'];
        $ok = \FA\Sanity\AdminConfig::saveReconciliationAccounts($map);
        $this->assertTrue($ok);

        $loaded = \FA\Sanity\AdminConfig::getReconciliationAccounts();
        $this->assertIsArray($loaded);
        $this->assertEquals($map, $loaded);
    }

    public function testEmptyDefaults()
    {
        // Simulate empty storage by saving empty map
        $ok = \FA\Sanity\AdminConfig::saveReconciliationAccounts([]);
        $this->assertTrue($ok);
        $loaded = \FA\Sanity\AdminConfig::getReconciliationAccounts();
        $this->assertEquals([], $loaded);
    }
}
