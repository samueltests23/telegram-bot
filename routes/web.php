<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AgentPanelController;

Route::get('/', function () {
    return redirect()->route('agent.index');
});

Route::get('/panel/chats/{conversation?}', [AgentPanelController::class, 'index'])->name('agent.show');
Route::get('/panel/chats', [AgentPanelController::class, 'index'])->name('agent.index');
Route::post('/panel/chats/{conversation}/reply', [AgentPanelController::class, 'reply'])->name('agent.reply');
Route::post('/panel/chats/{conversation}/resolve', [AgentPanelController::class, 'resolve'])->name('agent.resolve');