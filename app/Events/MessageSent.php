<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;

    public function __construct(Message $message)
    {
        // Cargar la relación con la conversación
        $this->message = $message->load('conversation');
    }

    public function broadcastOn(): array
    {
        // Emitir el evento en un canal público accesible por el panel
        return [
            new Channel('chat.' . $this->message->conversation_id),
            new Channel('conversations'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }
}