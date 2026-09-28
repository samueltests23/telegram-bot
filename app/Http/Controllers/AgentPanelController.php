<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Events\MessageSent;

class AgentPanelController extends Controller
{
    public function index(?Conversation $conversation = null)
    {
        $conversations = Conversation::with(['messages' => fn($q) => $q->latest()->limit(1)])
            ->orderBy('updated_at', 'desc')
            ->get();

        if (!$conversation && $conversations->isNotEmpty()) {
            $conversation = $conversations->first();
        }

        if ($conversation) {
            $conversation->load(['messages' => fn($q) => $q->orderBy('created_at', 'asc')]);
        }

        $activeConversation = $conversation;

        return view('agent.chat', compact('conversations', 'activeConversation'));
    }

    public function reply(Request $request, Conversation $conversation)
    {
        $messageContent = $request->input('message') ?? $request->input('text');

        if (empty($messageContent)) {
            return back()->withErrors(['message' => 'El mensaje no puede estar vacío.']);
        }

        // Envío directo al usuario por Telegram
        $token = config('services.telegram.bot_token', env('TELEGRAM_BOT_TOKEN'));
        Http::withoutVerifying()->post("https://api.telegram.org/bot{$token}/sendMessage", [
            'chat_id' => $conversation->telegram_chat_id,
            'text' => $messageContent,
        ]);

        // Guardar mensaje usando la columna 'text'
        $agentMsg = Message::create([
            'conversation_id' => $conversation->id,
            'sender' => 'agent',
            'text' => $messageContent,
        ]);

        try {
            broadcast(new MessageSent($agentMsg))->toOthers();
        } catch (\Throwable $e) {}

        return back();
    }

    public function resolve(Conversation $conversation)
    {
        $conversation->update(['status' => 'bot', 'step' => 'main_menu']);

        $token = config('services.telegram.bot_token', env('TELEGRAM_BOT_TOKEN'));
        Http::withoutVerifying()->post("https://api.telegram.org/bot{$token}/sendMessage", [
            'chat_id' => $conversation->telegram_chat_id,
            'text' => "La atención con el asesor ha finalizado. Has vuelto al asistente interactivo principal.",
            'reply_markup' => json_encode([
                'inline_keyboard' => [
                    [['text' => '🔙 Ver Menú Principal', 'callback_data' => 'menu_principal']]
                ]
            ])
        ]);

        return redirect()->back();
    }
}