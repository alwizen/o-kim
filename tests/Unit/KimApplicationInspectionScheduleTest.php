<?php

namespace Tests\Unit;

use App\Models\KimApplication;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class KimApplicationInspectionScheduleTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_application_without_schedule_is_not_scheduled_and_not_due(): void
    {
        $application = new KimApplication;

        $this->assertFalse($application->isInspectionScheduled());
        $this->assertFalse($application->isInspectionDue());
    }

    public function test_application_with_future_schedule_is_scheduled_but_not_due(): void
    {
        Carbon::setTestNow('2026-10-18 10:00:00');

        $application = new KimApplication([
            'inspection_scheduled_at' => Carbon::parse('2026-10-18 10:01:00'),
        ]);

        $this->assertTrue($application->isInspectionScheduled());
        $this->assertFalse($application->isInspectionDue());
    }

    public function test_application_is_due_when_schedule_equals_current_time(): void
    {
        Carbon::setTestNow('2026-10-18 10:00:00');

        $application = new KimApplication([
            'inspection_scheduled_at' => Carbon::parse('2026-10-18 10:00:00'),
        ]);

        $this->assertTrue($application->isInspectionDue());
    }

    public function test_application_is_due_when_schedule_is_in_the_past(): void
    {
        Carbon::setTestNow('2026-10-18 10:00:00');

        $application = new KimApplication([
            'inspection_scheduled_at' => Carbon::parse('2026-10-17 08:30:00'),
        ]);

        $this->assertTrue($application->isInspectionDue());
    }
}
