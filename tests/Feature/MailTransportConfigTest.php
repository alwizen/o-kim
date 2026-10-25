<?php

namespace Tests\Feature;

use App\Jobs\SendNotificationMail;
use App\Models\User;
use App\Notifications\KimCardIssued;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class MailTransportConfigTest extends TestCase
{
    use RefreshDatabase;

    public function test_smtp_timeout_is_set_so_stale_connections_fail_fast(): void
    {
        $timeout = config('mail.mailers.smtp.timeout');

        $this->assertNotNull($timeout, 'MAIL_TIMEOUT harus diisi agar koneksi SMTP basi cepat terdeteksi.');
        $this->assertLessThanOrEqual(10, (int) $timeout);
    }

    public function test_smtp_mailer_has_no_legacy_encryption_key(): void
    {
        // Laravel 13 memakai MAIL_SCHEME, bukan MAIL_ENCRYPTION.
        $this->assertArrayNotHasKey('encryption', config('mail.mailers.smtp'));
    }

    public function test_notification_mail_job_retries_more_than_once_with_backoff(): void
    {
        $job = new SendNotificationMail(
            User::factory()->make(),
            new KimCardIssued('KIM/FT-TGL/2026/10/0001', 'B 1 AA', '01 Jan 2027'),
        );

        $this->assertGreaterThanOrEqual(3, $job->tries);
        $this->assertIsArray($job->backoff);
        $this->assertSame(15, $job->backoff[0]);
        $this->assertGreaterThanOrEqual(3, $job->maxExceptions);
    }

    public function test_failed_hook_logs_notification_and_recipient(): void
    {
        Log::spy();

        $user = User::factory()->create(['email' => 'penerima@example.com']);

        $job = new SendNotificationMail(
            $user,
            new KimCardIssued('KIM/FT-TGL/2026/10/0001', 'B 1 AA', '01 Jan 2027'),
        );

        $job->failed(new \RuntimeException('451 4.4.2 Timeout'));

        Log::shouldHaveReceived('error')
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'Notifikasi email gagal dikirim'
                    && $context['notification'] === KimCardIssued::class
                    && $context['notifiable_email'] === 'penerima@example.com'
                    && $context['error'] === '451 4.4.2 Timeout';
            })
            ->once();
    }

    public function test_supervisor_worker_restarts_before_smtp_idle_timeout(): void
    {
        $config = file_get_contents(base_path('deploy/supervisor/okim-worker.conf'));

        $this->assertMatchesRegularExpression('/--max-time=300\b/', $config);
        $this->assertStringContainsString('stopwaitsecs=300', $config);
    }
}
