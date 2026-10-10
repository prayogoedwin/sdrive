<?php

namespace App\Notifications;

use App\Models\DriveAccessRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class EditAccessRequested extends Notification
{
    use Queueable;

    public function __construct(public DriveAccessRequest $request) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, int|string>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'request_id' => $this->request->id,
            'item_id' => $this->request->drive_item_id,
            'requester_id' => $this->request->user_id,
        ];
    }
}
