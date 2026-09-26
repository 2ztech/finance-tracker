<?php

declare(strict_types=1);

/**
 * One-time, idempotent data-repair routines.
 *
 * Guarded by a settings flag so a full scan does not run on every request.
 * If the flag is missing (fresh DB, or a restored older DB) the routine runs
 * once and sets the flag.
 */
final class Maintenance
{
    public static function run(): void
    {
        self::repairPhantomDates();
    }

    /**
     * Older versions of the recurring processor built due dates with sprintf()
     * without clamping to the month length, producing dates such as 2026-02-30
     * or 2026-04-31. Those strings sort outside every month's range filter, so
     * the affected transactions became invisible in all month views while still
     * counting toward all-time totals. This re-dates them to the last valid day
     * of their month.
     */
    private static function repairPhantomDates(): void
    {
        if (Settings::get('phantom_repair_done') !== null) {
            return;
        }

        $db = Database::getConnection();
        $stmt = $db->query("SELECT id, date FROM transactions WHERE date IS NOT NULL");
        $update = $db->prepare("UPDATE transactions SET date = ? WHERE id = ?");
        $fixed = 0;

        foreach ($stmt->fetchAll() as $row) {
            $parts = explode('-', (string) $row['date']);
            if (count($parts) !== 3) {
                continue;
            }
            $y = (int) $parts[0];
            $m = (int) $parts[1];
            $d = (int) $parts[2];

            if ($y < 1900 || $m < 1 || $m > 12 || $d < 1 || $d > 31) {
                continue;
            }
            if (checkdate($m, $d, $y)) {
                continue;
            }

            $lastDay = (int) date('t', strtotime(sprintf('%04d-%02d-01', $y, $m)));
            $corrected = sprintf('%04d-%02d-%02d', $y, $m, min($d, $lastDay));
            $update->execute([$corrected, $row['id']]);
            $fixed++;
        }

        if ($fixed > 0) {
            error_log("Maintenance: repaired {$fixed} phantom-dated transaction(s).");
        }

        Settings::set('phantom_repair_done', date('Y-m-d H:i:s'));
    }
}
