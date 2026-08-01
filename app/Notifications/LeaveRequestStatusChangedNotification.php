<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LeaveRequestStatusChangedNotification extends Notification
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $leaveRequest
     */
    public function __construct(
        private readonly array $leaveRequest,
        private readonly string $statusLabel,
        private readonly string $managerName,
        private readonly ?string $managerNote = null
    ) {
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('Leave Request '.$this->statusLabel)
            ->line('Your leave request status has changed.')
            ->line('Leave type: '.$this->leaveRequest['type'])
            ->line('Dates: '.$this->leaveRequest['start_date_label'].' to '.$this->leaveRequest['end_date_label'])
            ->line('New status: '.$this->statusLabel)
            ->line('Updated by: '.$this->managerName);

        if ($this->managerNote) {
            $mail->line('Manager note: '.$this->managerNote);
        }

        return $mail->action('Open Leave Requests', route('employee.leave.index'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'leave_request_id' => $this->leaveRequest['id'],
            'leave_type' => $this->leaveRequest['type'],
            'status' => $this->statusLabel,
            'manager_name' => $this->managerName,
            'manager_note' => $this->managerNote,
            'start_date' => $this->leaveRequest['start_date'],
            'end_date' => $this->leaveRequest['end_date'],
        ];
    }
}
