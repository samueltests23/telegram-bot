<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Events\MessageSent;

class AgentPanelController extends Controller
{
    public function index(Conversation $conversation = null)
    {
        // Carga todas las conversaciones con el último mensaje para la lista lateral
        $conversations = Conversation::with(['messages' => fn($q) => $q->latest()->limit(1)])
            ->orderBy('updated_at', 'desc')
            ->get();

        // Carga los mensajes del chat activo seleccionado
        $activeMessages = $conversation ? $conversation->messages()->orderBy('created_at', 'asc')->get() : [];

        // Retorna tu vista real en resources/views/agent/chat.blade.php
        return view('agent.chat', compact('conversations', 'conversation', 'activeMessages'));
    }

    public function reply(Request $request, Conversation $conversation)
    {
        $request->validate(['text' => 'required|string']);

        // Envío directo de la respuesta del agente a Telegram vía API HTTP
        $token = env('TELEGRAM_BOT_TOKEN');
        Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
            'chat_id' => $conversation->telegram_chat_id,
            'text' => $request->text,
        ]);

        // Guardar el mensaje del agente en la base de datos
        $agentMsg = $conversation->messages()->create([
            'sender' => 'agent',
            'text' => $request->text,
        ]);

        // Emitir evento por Pusher para actualizar la interfaz del panel
        try {
            broadcast(new MessageSent($agentMsg))->toOthers();
        } catch (\Exception $e) {}

        return back();
    }

    public function resolve(Conversation $conversation)
    {
        // Devuelve la conversación al control del Bot interactivo
        $conversation->update(['status' => 'bot']);

        // Notificar al usuario en Telegram que regresa al bot
        $token = env('TELEGRAM_BOT_TOKEN');
        Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
            'chat_id' => $conversation->telegram_chat_id,
            'text' => "La atención con el agente ha finalizado. Has vuelto al asistente automático.",
        ]);

        // Redirección segura de vuelta a la pantalla previa del panel
        return redirect()->back();
    }
}