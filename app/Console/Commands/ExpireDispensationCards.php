<?php

namespace App\Console\Commands;

use App\Enums\FindingStatus;
use App\Enums\KimCardStatus;
use App\Models\KimCard;
use App\Notifications\FindingDispensationExpired;
use App\Notifications\FindingDispensationExpiredStaff;
use App\Support\AppNotifier;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ExpireDispensationCards extends Command
{
    protected $signature = 'okim:expire-dispensations {--dry-run : Tampilkan kartu yang akan diproses tanpa mengubah data}';

    protected $description = 'Tandai KIM sementara yang batas dispensasinya sudah terlewati dan kirim notifikasi';

    public function handle(): int
    {
        $today = Carbon::today()->toDateString();
        $processed = 0;

        KimCard::query()
            ->with(['application.transporter', 'application.vehicle'])
            ->where('is_dispensation', true)
            ->where('status', '!=', KimCardStatus::Revoked->value)
            ->whereNotNull('dispensation_expires_at')
            ->whereDate('dispensation_expires_at', '<', $today)
            ->whereNull('dispensation_lapsed_at')
            ->chunkById(200, function ($cards) use (&$processed) {
                foreach ($cards as $card) {
                    $processed++;

                    if ($this->option('dry-run')) {
                        $this->components->twoColumnDetail(
                            $card->kim_number,
                            'batas '.$card->dispensation_expires_at?->format('d M Y').' (dry-run)',
                        );

                        continue;
                    }

                    $this->expire($card);
                }
            });

        if ($processed === 0) {
            $this->components->info('Tidak ada KIM sementara yang kedaluwarsa.');

            return self::SUCCESS;
        }

        $this->components->info(
            $this->option('dry-run')
                ? $processed.' KIM sementara akan diproses (dry-run, tidak ada perubahan).'
                : $processed.' KIM sementara ditandai kedaluwarsa.'
        );

        return self::SUCCESS;
    }

    private function expire(KimCard $card): void
    {
        $application = $card->application;

        $openItems = $application?->findings()
            ->where('status', FindingStatus::Dispensation->value)
            ->count() ?? 0;

        DB::transaction(function () use ($card, $application) {
            KimCard::whereKey($card->id)
                ->whereNull('dispensation_lapsed_at')
                ->update(['dispensation_lapsed_at' => now()]);

            // Tutup temuan yang dispensasinya berakhir agar tidak menggantung
            // selamanya. Pengajuan lama sudah berstatus Issued (terminal); tindak
            // lanjut dilakukan lewat pengajuan Perpanjangan yang baru.
            $application?->findings()
                ->where('status', FindingStatus::Dispensation->value)
                ->update(['status' => FindingStatus::Expired->value]);
        });

        if ($application === null) {
            return;
        }

        $dueDate = $card->dispensation_expires_at?->translatedFormat('d M Y') ?? '-';
        $registrationNumber = $application->vehicle?->registration_number ?? '-';

        AppNotifier::notifyTransporter(
            $application->transporter_id,
            new FindingDispensationExpired(
                $application->application_number,
                $registrationNumber,
                $card->kim_number,
                $dueDate,
                $openItems,
            ),
        );

        AppNotifier::notifyRole(
            ['ftm', 'pengawas_hsse', 'pengawas_distribusi', 'pengawas_qq'],
            new FindingDispensationExpiredStaff(
                $application->application_number,
                $registrationNumber,
                $card->kim_number,
                $dueDate,
                $openItems,
            ),
        );
    }
}
