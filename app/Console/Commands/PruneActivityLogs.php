<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use Illuminate\Console\Command;

/**
 * Hapus log aktivitas yang lebih lama dari batas retensi.
 *
 * Sengaja tidak dijadwalkan: retensi log adalah keputusan kebijakan, bukan
 * default sistem. Jalankan manual bila diperlukan:
 *
 *     php artisan okim:prune-activity-logs --days=365
 */
class PruneActivityLogs extends Command
{
    protected $signature = 'okim:prune-activity-logs
                            {--days=365 : Simpan log selama N hari terakhir}
                            {--dry-run : Hitung saja tanpa menghapus}';

    protected $description = 'Hapus log aktivitas pengguna yang lebih lama dari batas retensi';

    public function handle(): int
    {
        $days = max((int) $this->option('days'), 1);
        $cutoff = now()->subDays($days);

        $query = ActivityLog::query()->where('created_at', '<', $cutoff);

        if ($this->option('dry-run')) {
            $this->components->info($query->count().' log lebih lama dari '.$cutoff->toDateString().' akan dihapus.');

            return self::SUCCESS;
        }

        $deleted = $query->delete();

        $this->components->info($deleted.' log aktivitas dihapus (lebih lama dari '.$cutoff->toDateString().').');

        return self::SUCCESS;
    }
}
