<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel de Atención - Gerencia</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://js.pusher.com/8.0.1/pusher.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/laravel-echo@1.16.1/dist/echo.iife.js"></script>
</head>
<body class="bg-gray-100 h-screen flex flex-col">
    <header class="bg-blue-800 text-white p-4 font-bold shadow flex justify-between items-center">
        <span>Panel de Atención (Telegram)</span>
        <span class="text-xs bg-green-500 text-white px-2 py-1 rounded-full font-normal">● Pusher Activo</span>
    </header>

    <div class="flex flex-1 overflow-hidden">
        <!-- BARRA LATERAL: LISTA DE CHATS -->
        <aside class="w-1/3 bg-white border-r overflow-y-auto">
            <h2 class="p-4 font-semibold text-gray-700 border-b">Conversaciones Activas</h2>
            <ul id="conversations-list">
                @forelse($conversations as $c)
                    <li id="conv-item-{{ $c->id }}" class="border-b hover:bg-gray-50 {{ optional($conversation)->id === $c->id ? 'bg-blue-50' : '' }}">
                        <a href="{{ route('agent.show', $c->id) }}" class="block p-4">
                            <div class="font-bold text-gray-800">{{ $c->user_name ?? 'Usuario '.$c->telegram_chat_id }}</div>
                            <div class="text-sm text-gray-500 truncate last-message">
                                {{ optional($c->messages->last())->text ?? 'Sin mensajes' }}
                            </div>
                        </a>
                    </li>
                @empty
                    <li class="p-4 text-gray-500 text-center">No hay chats pendientes.</li>
                @endforelse
            </ul>
        </aside>

        <!-- CONTENIDO PRINCIPAL DEL CHAT -->
        <main class="flex-1 flex flex-col bg-gray-50">
            @if($conversation)
                <input type="hidden" id="active-conversation-id" value="{{ $conversation->id }}">
                
                <div class="p-4 bg-white border-b flex justify-between items-center">
                    <span class="font-bold text-lg text-gray-800">Atendiendo a: {{ $conversation->user_name }}</span>
                    <form action="{{ route('agent.resolve', $conversation->id) }}" method="POST">
                        @csrf
                        <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded text-sm hover:bg-green-700">✔ Resolver Chat</button>
                    </form>
                </div>

                <div id="messages-container" class="flex-1 p-4 overflow-y-auto space-y-4">
                    @php
                        $chatMessages = $activeMessages ?? $conversation->messages ?? [];
                    @endphp

                    @foreach($chatMessages as $msg)
                        <div class="flex {{ $msg->sender === 'agent' ? 'justify-end' : 'justify-start' }}">
                            <div class="max-w-md px-4 py-2 rounded-lg text-sm shadow {{ $msg->sender === 'agent' ? 'bg-blue-600 text-white' : ($msg->sender === 'bot' ? 'bg-gray-200 text-gray-700' : 'bg-white border text-gray-800') }}">
                                <div class="text-xs opacity-75 font-semibold mb-1">{{ strtoupper($msg->sender) }}</div>
                                <div>{{ $msg->text }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <form action="{{ route('agent.reply', $conversation->id) }}" method="POST" class="p-4 bg-white border-t flex gap-2">
                    @csrf
                    <input type="text" name="text" placeholder="Escribe un mensaje al usuario..." required autofocus class="flex-1 border rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg font-semibold hover:bg-blue-700">Enviar</button>
                </form>
            @else
                <div class="flex-1 flex items-center justify-center text-gray-400">Selecciona un chat de la izquierda para comenzar.</div>
            @endif
        </main>
    </div>

    <!-- CONEXIÓN DE WEBSOCKETS (PUSHER) -->
    <script>
        // Inicialización nativa de Pusher con tus llaves reales
        window.Echo = new Echo({
            broadcaster: 'pusher',
            key: '8a202d012a31b981f250',
            cluster: 'us2',
            forceTLS: true
        });

        const activeConvElement = document.getElementById('active-conversation-id');
        const activeConversationId = activeConvElement ? activeConvElement.value : null;

        function scrollToBottom() {
            const container = document.getElementById('messages-container');
            if (container) container.scrollTop = container.scrollHeight;
        }
        scrollToBottom();

        // 1. Escuchar actualizaciones en la lista lateral
        window.Echo.channel('conversations').listen('.message.sent', (e) => {
            const convItem = document.querySelector(`#conv-item-${e.message.conversation_id} .last-message`);
            if (convItem) {
                convItem.textContent = e.message.text;
            }
        });

        // 2. Escuchar mensajes del chat activo en tiempo real
        if (activeConversationId) {
            window.Echo.channel(`chat.${activeConversationId}`).listen('.message.sent', (e) => {
                const messagesContainer = document.getElementById('messages-container');
                if (!messagesContainer) return;

                const isAgent = e.message.sender === 'agent';
                const isBot = e.message.sender === 'bot';
                
                const html = `
                    <div class="flex ${isAgent ? 'justify-end' : 'justify-start'}">
                        <div class="max-w-md px-4 py-2 rounded-lg text-sm shadow ${isAgent ? 'bg-blue-600 text-white' : (isBot ? 'bg-gray-200 text-gray-700' : 'bg-white border text-gray-800')}">
                            <div class="text-xs opacity-75 font-semibold mb-1">${e.message.sender.toUpperCase()}</div>
                            <div>${e.message.text}</div>
                        </div>
                    </div>
                `;
                messagesContainer.insertAdjacentHTML('beforeend', html);
                scrollToBottom();
            });
        }
    </script>
</body>
</html>