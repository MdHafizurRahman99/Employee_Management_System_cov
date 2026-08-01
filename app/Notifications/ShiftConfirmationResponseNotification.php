<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ShiftConfirmationResponseNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly int $slotId,
        private readonly string $employeeName,
        private readonly string $status,
        private readonly ?string $note = null
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->subject('Shift '.$this->status.' by '.$this->employeeName)
            ->line($this->employeeName.' has '.$this->status.' a published shift.');

        if ($this->note) {
            $mail->line('Reason: '.$this->note);
        }

        return $mail->action('Review Confirmations', route('manager.shifts.confirmations'));
    }

    public function toArray(object $notifiable): array
    {
        return ['type' => 'shift_confirmation_response', 'shift_id' => $this->slotId,
            'employee' => $this->employeeName, 'status' => $this->status, 'note' => $this->note];
    }
}
