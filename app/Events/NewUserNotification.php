<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Database\Eloquent\Model;

class NewUserNotification implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $user_id;
    public ?Model $new_record;
    public string $new_status;

    /**
     * Create a new event instance.
     */
    public function __construct(int $user_id, ?Model $new_record=null, string $new_status)
    {
        $this->user_id = $user_id;
        $this->new_record = $new_record;
        $this->new_status = $new_status;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user-'.$this->user_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'new.user.notification';
    }
}
