<?php

namespace App\Console\Commands;

use App\Models\KimCard;
use App\Models\VehicleDocument;
use App\Notifications\ExpiryReminder;
use App\Notifications\ExpiryReminderStaff;
use App\Services\Document\DocumentExpiryService;
use App\Services\Setting\SettingsService;
use App\Support\AppNotifier;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class NotifyExpiringDocuments extends Command
{
    protected $signature = 'okim:notify-expiry';

    protected $description = 'Kirim pengingat harian dokumen & KIM yang kedaluwarsa/akan habis';

    public function handle(DocumentExpiryService $expiry, SettingsService $settings): int
    {
        $threshold = $settings->expiryWarningDays();

        // Cegah pengiriman ganda bila command terpanggil lebih dari sekali sehari.
        if (! Cache::add('okim:expiry-reminder:'.now()->toDateString(), true, now()->addDays(2))) {
            $this->components->info('Pengingat hari ini sudah dikirim.');

            return self::SUCCESS;
        }

        $documents = $expiry->expiringDocumentsQuery()
            ->with(['vehicle.transporter', 'documentType'])
            ->get();

        $cards = $this->expiringKimCards($threshold);

        if ($documents->isEmpty() && $cards->isEmpty()) {
            $this->components->info('Tidak ada dokumen/KIM yang mendekati kedaluwarsa.');

            return self::SUCCESS;
        }

        $this->notifyTransporters($documents, $cards, $threshold);
        $this->notifyStaff($documents, $cards, $threshold);

        $this->components->info('Pengingat kedaluwarsa terkirim.');

        return self::SUCCESS;
    }

    /**
     * KIM aktif yang akan kedaluwarsa dalam ambang hari (atau sudah lewat).
     *
     * @return Collection<int, KimCard>
     */
    private function expiringKimCards(int $threshold)
    {
        return KimCard::query()
            ->with(['application.transporter', 'application.vehicle'])
            ->where('status', '!=', 'revoked')
            ->whereNotNull('expires_at')
            ->whereDate('expires_at', '<=', now()->addDays($threshold)->toDateString())
            ->whereDate('expires_at', '>=', now()->subDays(7)->toDateString())
            ->get();
    }

    /**
     * @param  Collection<int, VehicleDocument>  $documents
     * @param  Collection<int, KimCard>  $cards
     */
    private function notifyTransporters($documents, $cards, int $threshold): void
    {
        $grouped = [];

        foreach ($documents as $document) {
            $transporterId = $document->vehicle?->transporter_id;

            if ($transporterId === null) {
                continue;
            }

            $grouped[$transporterId][] = [
                'label' => $document->documentType?->name ?? 'Dokumen',
                'nopol' => $document->vehicle?->registration_number ?? '-',
                'expires_at' => Carbon::parse($document->expires_at)->translatedFormat('d M Y'),
                'days_left' => DocumentExpiryService::daysUntil(Carbon::parse($document->expires_at)),
            ];
        }

        foreach ($cards as $card) {
            $transporterId = $card->application?->transporter_id;

            if ($transporterId === null) {
                continue;
            }

            $grouped[$transporterId][] = [
                'label' => 'KIM '.$card->kim_number,
                'nopol' => $card->application?->vehicle?->registration_number ?? '-',
                'expires_at' => Carbon::parse($card->expires_at)->translatedFormat('d M Y'),
                'days_left' => DocumentExpiryService::daysUntil(Carbon::parse($card->expires_at)),
            ];
        }

        $usersByTransporter = AppNotifier::usersGroupedByTransporter(array_keys($grouped));

        foreach ($grouped as $transporterId => $items) {
            usort($items, fn (array $a, array $b) => $a['days_left'] <=> $b['days_left']);

            $notification = new ExpiryReminder($items, $threshold, (int) $transporterId);

            foreach ($usersByTransporter[(int) $transporterId] ?? [] as $user) {
                $user->notify($notification);
            }
        }
    }

    /**
     * @param  Collection<int, VehicleDocument>  $documents
     * @param  Collection<int, KimCard>  $cards
     */
    private function notifyStaff($documents, $cards, int $threshold): void
    {
        $items = [];

        foreach ($documents as $document) {
            $items[] = [
                'company' => $document->vehicle?->transporter?->company_name ?? '-',
                'label' => $document->documentType?->name ?? 'Dokumen',
                'nopol' => $document->vehicle?->registration_number ?? '-',
                'expires_at' => Carbon::parse($document->expires_at)->translatedFormat('d M Y'),
                'days_left' => DocumentExpiryService::daysUntil(Carbon::parse($document->expires_at)),
            ];
        }

        foreach ($cards as $card) {
            $items[] = [
                'company' => $card->application?->transporter?->company_name ?? '-',
                'label' => 'KIM '.$card->kim_number,
                'nopol' => $card->application?->vehicle?->registration_number ?? '-',
                'expires_at' => Carbon::parse($card->expires_at)->translatedFormat('d M Y'),
                'days_left' => DocumentExpiryService::daysUntil(Carbon::parse($card->expires_at)),
            ];
        }

        usort($items, fn (array $a, array $b) => $a['days_left'] <=> $b['days_left']);

        AppNotifier::notifyStaffWithPermission(
            'verify_documents',
            new ExpiryReminderStaff($items, $threshold),
        );
    }
}
