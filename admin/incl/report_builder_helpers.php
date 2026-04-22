<?php

/**
 * Helpers for report catalog search (see admin/action/search_handler.php).
 * Expects admin/incl/const.php to be loaded first (SESS_USR_KEY, SUPER_ADMIN_ID).
 */

/**
 * Whether the current visitor may see this report in public catalog search.
 * Listing SQL already excludes internal reports; this confirms the row still matches.
 *
 * @param int         $reportId
 * @param resource|\PgSql\Connection $conn
 */
function canViewReport($reportId, $conn)
{
    $reportId = (int) $reportId;
    if ($reportId <= 0) {
        return false;
    }

    $res = pg_query_params(
        $conn,
        'SELECT 1 FROM reports r WHERE r.id = $1 AND (r.is_internal IS NULL OR r.is_internal = false)',
        [$reportId]
    );
    if (!$res) {
        return false;
    }
    $ok = pg_num_rows($res) > 0;
    pg_free_result($res);

    return $ok;
}
