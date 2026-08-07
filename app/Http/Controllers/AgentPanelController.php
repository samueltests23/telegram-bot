<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use Illuminate\Http\Request;
use Telegram\Bot\Laravel\Facades\Telegram;
use App\Events\MessageSent;

class AgentPanelController extends Controller
{
    public function index(Conversation $conversation = null)
    {
        $conversations = Conversation::where('status', 'human')
            ->with(['messages' => fn($q) => $q->latest()->limit(1)])
            ->orderBy('updated_at', 'desc')
            ->get();

        $activeMessages = $conversation ? $conversation->messages()->orderBy('created_at', 'asc')->get() : [];

        return view('agent.chat', compact('conversations', 'conversation', 'activeMessages'));
    }

    public function reply(Request $request, Conversation $conversation)
    {
        $request->validate(['text' => 'required|string']);

        Telegram::sendMessage([
            'chat_id' => $conversation->telegram_chat_id,
            'text' => $request->text,
        ]);

        $agentMsg = $conversation->messages()->create([
            'sender' => 'agent',
            'text' => $request->text,
        ]);

        broadcast(new MessageSent($agentMsg));

        return back();
    }

    public function resolve(Conversation $conversation)
    {
        $conversation->update(['status' => 'bot']);

        Telegram::sendMessage([
            'chat_id' => $conversation->telegram_chat_id,
            'text' => "La atención con el agente ha finalizado. Has vuelto al asistente automático.",
        ]);

        return redirect()->route('agent.index');
    }
}
