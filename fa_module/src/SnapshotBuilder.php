<?php
namespace FA\Sanity;

/**
 * SnapshotBuilder
 *
 * Creates read-only snapshots of `stock_moves` content into a module table
 * `sanity_stock_moves_snapshot` so reconciliation can be run without altering
 * FA core tables. Uses FA helper functions: db_query, db_escape.
 */
class SnapshotBuilder
{
    /**
     * Create a snapshot series by inserting selected rows from `stock_moves`.
     *
     * @param string $series Snapshot series name (e.g., 2026Q1)
     * @param string|null $startDate ISO date string or null to unbounded
     * @param string|null $endDate ISO date string or null to unbounded
     * @param int|string|null $userId optional user id for session mapping
     * @param array $options optional args
     * @return array summary (rows_inserted, series)
     * @throws \Exception on failure
     */
    public function createSnapshot($series, $startDate = null, $endDate = null, $userId = null, array $options = [])
    {
        if (!function_exists('db_query')) {
            throw new SanityException('SnapshotBuilder requires FA DB helpers (db_query)');
        }

        $s_series = db_escape($series);
        $where = "1=1";
        if ($startDate !== null) {
            $where .= " AND transaction_date >= '" . db_escape($startDate) . "'";
        }
        if ($endDate !== null) {
            $where .= " AND transaction_date <= '" . db_escape($endDate) . "'";
        }

        // Build insert-select — select columns we care about
        $insertSql = "INSERT INTO sanity_stock_moves_snapshot (snapshot_series, src_id, stock_id, location_id, transaction_date, qty, move_type, doc_type, doc_no, cost)
            SELECT '".$s_series."', id, stock_id, location_id, transaction_date, qty, move_type, doc_type, doc_no, IFNULL(qty * unit_cost, NULL)
            FROM stock_moves WHERE " . $where . ";";

        try {
            $start = microtime(true);
            db_query($insertSql, 'creating snapshot');
            $dur = microtime(true) - $start;

            // count inserted rows
            $countQ = "SELECT COUNT(*) AS c FROM sanity_stock_moves_snapshot WHERE snapshot_series='" . $s_series . "'";
            $r = db_query($countQ);
            $row = db_fetch($r);
            $count = $row ? (int)$row['c'] : 0;

            // store session mapping if user provided
            if ($userId !== null) {
                $sessKey = bin2hex(random_bytes(6));
                $crit = json_encode(['start'=>$startDate,'end'=>$endDate]);
                $ins = "INSERT INTO sanity_snapshot_sessions (snapshot_series, session_key, user_id, criteria) VALUES ('" . $s_series . "', '" . db_escape($sessKey) . "', '" . db_escape($userId) . "', '" . db_escape($crit) . "')";
                db_query($ins);
            } else {
                $sessKey = null;
            }

            // structured log + event
            Logger::info("Snapshot created", ['series'=>$series, 'rows'=>$count, 'duration_s'=>$dur]);
            Logger::fireEvent('sanity.snapshot.created', ['series'=>$series,'rows'=>$count,'user_id'=>$userId]);

            return ['series'=>$series, 'rows_inserted'=>$count, 'duration_s'=>$dur, 'session_key'=>$sessKey];
        } catch (\Throwable $e) {
            Logger::error('Snapshot creation failed', ['series'=>$series,'error'=>$e->getMessage()]);
            throw new SanityException('Snapshot creation failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Return available snapshot series (distinct values)
     * @return array
     */
    public function listSeries()
    {
        $q = "SELECT DISTINCT snapshot_series FROM sanity_stock_moves_snapshot ORDER BY snapshot_series DESC LIMIT 200";
        $res = db_query($q);
        $out = [];
        while ($r = db_fetch($res)) $out[] = $r['snapshot_series'];
        return $out;
    }
}
