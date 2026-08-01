<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SchedulePublishedNotification extends Notification
{
    use Queueable;

    /** @param array<string,mixed> $shift */
    public function __construct(private readonly array $shift, private readonly bool $reminder = false)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $subject = $this->reminder ? 'Shift confirmation reminder' : 'Your schedule has been published';
        $mail = (new MailMessage)->subject($subject)
            ->line($this->reminder ? 'Your manager is waiting for your response to this shift.' : 'A shift has been published to your schedule.')
            ->line('Shift: '.($this->shift['title'] ?? $this->shift['role'] ?? 'Assigned shift'))
            ->line('Date: '.($this->shift['date_label'] ?? $this->shift['shift_date_value'] ?? ''))
            ->line('Time: '.($this->shift['time_label'] ?? ''))
            ->line('Location: '.($this->shift['company'] ?? ''));

        if ($this->shift['requires_confirmation'] ?? false) {
            $mail->line('Please accept or decline this shift.');
        }

        return $mail->action('Open My Shifts', route('employee.shifts.index', [
            'month' => substr((string) ($this->shift['shift_date_value'] ?? ''), 0, 7),
            'day' => $this->shift['shift_date_value'] ?? null,
        ]));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => $this->reminder ? 'shift_confirmation_reminder' : 'schedule_published',
            'shift_id' => $this->shift['id'] ?? null,
            'title' => $this->shift['title'] ?? $this->shift['role'] ?? 'Assigned shift',
            'date' => $this->shift['shift_date_value'] ?? null,
            'time' => $this->shift['time_label'] ?? null,
            'company' => $this->shift['company'] ?? null,
            'requires_confirmation' => (bool) ($this->shift['requires_confirmation'] ?? false),
        ];
    }
}
