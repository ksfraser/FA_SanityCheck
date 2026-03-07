<?php
namespace FA\Sanity;

/**
 * Simple admin configuration helper for the Sanity module.
 * Stores module-level configuration in `sanity_config` table.
 */
class AdminConfig
{
    /**
     * Return reconciliation accounts mapping as associative array.
     * Stored as JSON in config_key `reconciliation_accounts`.
     * @return array
     */
    public static function getReconciliationAccounts()
    {
        $res = db_query("SELECT config_value FROM sanity_config WHERE config_key='reconciliation_accounts' LIMIT 1");
        if ($row = db_fetch($res)) {
            $v = $row['config_value'];
            $decoded = json_decode($v, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        return [];
    }

    /**
     * Save reconciliation accounts mapping (associative array).
     * @param array $map
     * @return bool
     */
    public static function saveReconciliationAccounts(array $map)
    {
        $json = db_escape(json_encode($map));
        // upsert
        $exists = db_query("SELECT COUNT(*) AS c FROM sanity_config WHERE config_key='reconciliation_accounts'");
        $r = db_fetch($exists);
        if ($r && intval($r['c']) > 0) {
            $sql = "UPDATE sanity_config SET config_value='" . $json . "' WHERE config_key='reconciliation_accounts'";
            $res = db_query($sql);
            return ($res !== false && $res !== null);
        }
        $sql = "INSERT INTO sanity_config (config_key, config_value) VALUES ('reconciliation_accounts', '" . $json . "')";
        $res = db_query($sql);
        return ($res !== false && $res !== null);
    }
}
