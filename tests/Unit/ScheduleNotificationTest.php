<?php

namespace Tests\Unit;

use App\Notifications\SchedulePublishedNotification;
use App\Notifications\ShiftConfirmationResponseNotification;
use Tests\TestCase;

class ScheduleNotificationTest extends TestCase
{
    public function test_published_shift_notification_uses_mail_without_local_database_storage(): void
    {
        $notification = new SchedulePublishedNotification([
            'id' => 71, 'title' => 'Reception', 'role' => 'Front Desk',
            'date_label' => 'Mon, 13 Jul 2026', 'shift_date_value' => '2026-07-13',
            'time_label' => '09:00 AM - 05:00 PM', 'company' => 'Clinic',
            'requires_confirmation' => true,
        ]);

        $this->assertSame(['mail'], $notification->via((object) []));
        $this->assertSame('Your schedule has been published', $notification->toMail((object) [])->subject);
        $this->assertSame('schedule_published', $notification->toArray((object) [])['type']);
    }

    public function test_manager_response_notification_contains_decline_reason(): void
    {
        $notification = new ShiftConfirmationResponseNotification(71, 'Alice', 'declined', 'Training conflict');

        $this->assertSame(['mail'], $notification->via((object) []));
        $this->assertSame('Shift declined by Alice', $notification->toMail((object) [])->subject);
        $this->assertSame('Training conflict', $notification->toArray((object) [])['note']);
    }
}
