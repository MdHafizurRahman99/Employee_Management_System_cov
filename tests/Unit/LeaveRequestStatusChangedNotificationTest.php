<?php

namespace Tests\Unit;

use App\Notifications\LeaveRequestStatusChangedNotification;
use Illuminate\Notifications\Messages\MailMessage;
use Tests\TestCase;

class LeaveRequestStatusChangedNotificationTest extends TestCase
{
    public function test_it_builds_the_expected_notification_payloads(): void
    {
        $notification = new LeaveRequestStatusChangedNotification(
            [
                'id' => 81,
                'type' => 'Annual Leave',
                'start_date' => '2026-06-20',
                'end_date' => '2026-06-22',
                'start_date_label' => '20 Jun 2026',
                'end_date_label' => '22 Jun 2026',
            ],
            'Approved',
            'Manager Jane',
            'Enjoy your trip.'
        );

        $channels = $notification->via((object) []);
        $mail = $notification->toMail((object) []);
        $array = $notification->toArray((object) []);

        $this->assertSame(['database', 'mail'], $channels);
        $this->assertInstanceOf(MailMessage::class, $mail);
        $this->assertSame(81, $array['leave_request_id']);
        $this->assertSame('Approved', $array['status']);
        $this->assertSame('Manager Jane', $array['manager_name']);
        $this->assertSame('Enjoy your trip.', $array['manager_note']);
    }
}
