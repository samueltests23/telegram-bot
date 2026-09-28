<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\AgentPanelController;
use App\Models\Conversation;

Route::get('/', function () {
    return redirect()->route('agent.index');
});

// 1. Rutas principales del panel de agentes
Route::get('/panel/chats', [AgentPanelController::class, 'index'])->name('agent.index');
Route::get('/panel/chats/{conversation}', [AgentPanelController::class, 'index'])->name('agent.show');

// 2. Acciones del asesor
Route::post('/panel/chats/{conversation}/reply', [AgentPanelController::class, 'reply'])->name('agent.reply');
Route::post('/panel/chats/{conversation}/resolve', [AgentPanelController::class, 'resolve'])->name('agent.resolve');

// 3. Endpoint de Sondeo Automático en tiempo real (Polling cada 2.5s)
Route::get('/panel/chats/{conversation}/messages-poll', function (Conversation $conversation, Request $request) {
    $afterId = (int) $request->query('after', 0);

    $newMessages = $conversation->messages()
        ->where('id', '>', $afterId)
        ->orderBy('id', 'asc')
        ->get()
        ->map(function ($msg) {
            return [
                'id'         => $msg->id,
                'sender'     => $msg->sender,
                'text'       => $msg->text ?? $msg->body ?? '',
                'created_at' => $msg->created_at ? $msg->created_at->format('H:i') : '',
            ];
        });

    return response()->json([
        'messages' => $newMessages
    ]);
})->name('panel.chats.poll');