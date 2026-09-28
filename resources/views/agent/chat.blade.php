<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Asesores - Formación CONATEL</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- Pusher y Laravel Echo -->
    <script src="https://js.pusher.com/7.2/pusher.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/laravel-echo/1.15.3/echo.iife.js"></script>
</head>
<body class="bg-slate-100 h-screen flex flex-col font-sans">

    @php
        $selectedConv = $activeConversation ?? $conversation ?? null;
        $chatList =$conversations ?? [];
        $lastMessageId = 0;
        if ($selectedConv && $selectedConv->messages &&$selectedConv->messages->count() > 0) {
            $lastMessageId =$selectedConv->messages->last()->id;
        }
    @endphp

    <header class="bg-white border-b px-6 py-4 flex items-center justify-between shadow-sm">
        <div class="flex items-center space-x-3">
            <span class="w-3 h-3 rounded-full bg-emerald-500 animate-pulse"></span>
            <h1 class="text-lg font-bold text-slate-800">Panel de Asesores | CONATEL Formación</h1>
        </div>
        <div class="flex items-center space-x-4">
            <!-- Control de Audio -->
            <button id="toggleAudioBtn" type="button" class="flex items-center gap-1.5 text-xs font-semibold px-3 py-1.5 rounded-lg border border-slate-300 bg-slate-50 text-slate-700 hover:bg-slate-100 transition shadow-xs cursor-pointer">
                <span id="audioIcon">🔇</span>
                <span id="audioStatusText">Activar Sonido</span>
            </button>

            <div class="text-sm text-slate-500">
                Usuario: <span class="font-semibold text-slate-700">{{ auth()->user()->name ?? 'Asesor en Turno' }}</span>
            </div>
        </div>
    </header>

    <div class="flex-1 flex overflow-hidden">
        
        <!-- Bandeja de Entrada -->
        <aside class="w-1/3 bg-white border-r overflow-y-auto">
            <div class="p-4 border-b bg-slate-50 flex justify-between items-center">
                <h2 class="text-sm font-semibold uppercase tracking-wider text-slate-600">Bandeja de Entrada</h2>
                <span class="text-[11px] text-emerald-600 font-medium flex items-center gap-1">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 inline-block"></span> En vivo
                </span>
            </div>
            <ul class="divide-y divide-slate-100">
                <?php if (count($chatList) > 0): ?>
                    <?php foreach ($chatList as$item): ?>
                        <?php 
                            $isCurrent =$selectedConv && $selectedConv->id ===$item->id; 
                        ?>
                        <li class="p-4 hover:bg-blue-50 transition cursor-pointer <?= $isCurrent ? 'bg-blue-50 border-l-4 border-blue-600' : '' ?>">
                            <a href="/panel/chats/<?= $item->id ?>" class="block">
                                <div class="flex justify-between items-center mb-1">
                                    <span class="font-bold text-slate-800"><?= htmlspecialchars($item->user_name ?? 'Usuario') ?></span>
                                    <span class="text-xs px-2 py-0.5 rounded-full <?= ($item->status === 'human') ? 'bg-amber-100 text-amber-800' : 'bg-slate-200 text-slate-700' ?>">
                                        <?= ($item->status === 'human') ? 'Requiere Asesor' : 'Bot' ?>
                                    </span>
                                </div>
                                <p class="text-xs text-slate-500 truncate">ID Telegram: <?= htmlspecialchars($item->telegram_chat_id) ?></p>
                                <p class="text-xs text-slate-400 mt-1"><?= $item->updated_at ? $item->updated_at->diffForHumans() : '' ?></p>
                            </a>
                        </li>
                    <?php endforeach; ?>
                <?php else: ?>
                    <li class="p-6 text-center text-sm text-slate-400">No hay conversaciones registradas</li>
                <?php endif; ?>
            </ul>
        </aside>

        <!-- Chat Activo -->
        <main class="flex-1 flex flex-col bg-slate-50">
            <?php if ($selectedConv): ?>
                <div class="p-4 bg-white border-b flex justify-between items-center shadow-xs">
                    <div>
                        <h2 class="font-bold text-slate-800"><?= htmlspecialchars($selectedConv->user_name ?? 'Usuario') ?></h2>
                        <span class="text-xs text-slate-500">ID Chat: <?= htmlspecialchars($selectedConv->telegram_chat_id) ?></span>
                    </div>
                    <form action="/panel/chats/<?= $selectedConv->id ?>/resolve" method="POST">
                        @csrf
                        <button type="submit" class="text-xs px-3 py-1.5 rounded-lg border font-medium bg-emerald-50 text-emerald-700 border-emerald-300 hover:bg-emerald-100 transition">
                            Marcar Resuelto / Devolver a Bot
                        </button>
                    </form>
                </div>

                <div id="messages-container" class="flex-1 overflow-y-auto p-6 space-y-4">
                    <?php if (!empty($selectedConv->messages)): ?>
                        <?php foreach ($selectedConv->messages as$msg): ?>
                            <?php 
                                $content = $msg->text ?? $msg->body ?? '';
                                $sender =$msg->sender ?? 'user';
                                $isAgent = ($sender === 'agent');
                                $isSystem = ($sender === 'system');
                            ?>
                            <div data-msg-id="<?= $msg->id ?>" class="flex <?= $isAgent ? 'justify-end' : ($isSystem ? 'justify-center' : 'justify-start') ?>">
                                <div class="max-w-md rounded-xl p-3.5 shadow-sm text-sm <?= $isAgent ? 'bg-blue-600 text-white rounded-br-none' : ($isSystem ? 'bg-amber-100 text-amber-900 border border-amber-200 text-xs' : 'bg-white text-slate-800 border rounded-bl-none') ?>">
                                    
                                    <?php if (str_starts_with($content, '[IMG]')): ?>
                                        <?php $imgUrl = str_replace('[IMG]', '',$content); ?>
                                        <div class="space-y-1.5">
                                            <p class="font-semibold text-xs <?= $isAgent ? 'text-white' : 'text-blue-700' ?> uppercase tracking-wide">Comprobante Adjunto:</p>
                                            <a href="<?= asset($imgUrl) ?>" target="_blank" class="block group overflow-hidden rounded-lg border border-slate-200 bg-white">
                                                <img src="<?= asset($imgUrl) ?>" alt="Comprobante de Pago" class="max-h-64 w-auto object-contain mx-auto group-hover:scale-105 transition duration-200">
                                            </a>
                                            <span class="text-[10px] <?= $isAgent ? 'text-blue-200' : 'text-slate-400' ?> block">Clic para abrir en tamaño original ↗</span>
                                        </div>
                                    <?php else: ?>
                                        <p class="whitespace-pre-line"><?= htmlspecialchars($content) ?></p>
                                    <?php endif; ?>

                                    <span class="text-[10px] block mt-1 <?= $isAgent ? 'text-blue-200 text-right' : 'text-slate-400' ?>">
                                        <?= $msg->created_at ? $msg->created_at->format('H:i') : '' ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="p-4 bg-white border-t">
                    <form action="/panel/chats/<?= $selectedConv->id ?>/reply" method="POST" class="flex gap-3">
                        @csrf
                        <input type="text" name="message" placeholder="Escribe tu respuesta al usuario en Telegram..." required autocomplete="off"
                               class="flex-1 border border-slate-300 rounded-lg px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2 rounded-lg text-sm font-medium transition shadow-sm">
                            Enviar
                        </button>
                    </form>
                </div>
            <?php else: ?>
                <div class="flex-1 flex items-center justify-center text-slate-400 text-sm">
                    Selecciona una conversación del listado izquierdo para comenzar
                </div>
            <?php endif; ?>
        </main>
    </div>

    <script>
        const container = document.getElementById('messages-container');
        if (container) {
            container.scrollTop = container.scrollHeight;
        }

        // --- SISTEMA DE AUDIO (Web Audio API) ---
        let audioEnabled = false;
        let audioCtx = null;

        const toggleAudioBtn = document.getElementById('toggleAudioBtn');
        const audioIcon = document.getElementById('audioIcon');
        const audioStatusText = document.getElementById('audioStatusText');

        function playNotificationSound() {
            if (!audioEnabled) return;

            try {
                const AudioContext = window.AudioContext || window.webkitAudioContext;
                if (!audioCtx) {
                    audioCtx = new AudioContext();
                }
                if (audioCtx.state === 'suspended') {
                    audioCtx.resume();
                }

                const now = audioCtx.currentTime;

                // Tono 1 ("Ding")
                const osc1 = audioCtx.createOscillator();
                const gain1 = audioCtx.createGain();
                osc1.type = 'sine';
                osc1.frequency.setValueAtTime(659.25, now);
                gain1.gain.setValueAtTime(0.001, now);
                gain1.gain.exponentialRampToValueAtTime(0.25, now + 0.04);
                gain1.gain.exponentialRampToValueAtTime(0.001, now + 0.25);
                osc1.connect(gain1);
                gain1.connect(audioCtx.destination);
                osc1.start(now);
                osc1.stop(now + 0.25);

                // Tono 2 ("Dong")
                const osc2 = audioCtx.createOscillator();
                const gain2 = audioCtx.createGain();
                osc2.type = 'sine';
                osc2.frequency.setValueAtTime(880.00, now + 0.12);
                gain2.gain.setValueAtTime(0.001, now + 0.12);
                gain2.gain.exponentialRampToValueAtTime(0.3, now + 0.16);
                gain2.gain.exponentialRampToValueAtTime(0.001, now + 0.45);
                osc2.connect(gain2);
                gain2.connect(audioCtx.destination);
                osc2.start(now + 0.12);
                osc2.stop(now + 0.45);
            } catch (e) {
                console.warn("AudioContext bloqueado:", e);
            }
        }

        if (toggleAudioBtn) {
            toggleAudioBtn.addEventListener('click', () => {
                const AudioContext = window.AudioContext || window.webkitAudioContext;
                if (!audioCtx) {
                    audioCtx = new AudioContext();
                }
                if (audioCtx.state === 'suspended') {
                    audioCtx.resume();
                }

                audioEnabled = !audioEnabled;

                if (audioEnabled) {
                    audioIcon.innerText = '🔔';
                    audioStatusText.innerText = 'Sonido Activado';
                    toggleAudioBtn.className = 'flex items-center gap-1.5 text-xs font-semibold px-3 py-1.5 rounded-lg border border-emerald-300 bg-emerald-50 text-emerald-800 transition shadow-xs cursor-pointer';
                    playNotificationSound();
                } else {
                    audioIcon.innerText = '🔇';
                    audioStatusText.innerText = 'Activar Sonido';
                    toggleAudioBtn.className = 'flex items-center gap-1.5 text-xs font-semibold px-3 py-1.5 rounded-lg border border-slate-300 bg-slate-50 text-slate-700 hover:bg-slate-100 transition shadow-xs cursor-pointer';
                }
            });
        }

        // --- RENDERIZADO DE MENSAJES ---
        function renderIncomingMessage(msg) {
            if (!container) return;

            // Evitar duplicados en pantalla
            if (msg.id && document.querySelector(`[data-msg-id="${msg.id}"]`)) {
                return;
            }

            const isAgent = msg.sender === 'agent';
            const isSystem = msg.sender === 'system';
            const content = msg.text || '';

            if (!isAgent) {
                playNotificationSound();
            }

            let innerHtml = '';
            if (content.startsWith('[IMG]')) {
                const imgUrl = content.replace('[IMG]', '');
                innerHtml = `
                    <div class="space-y-1.5">
                        <p class="font-semibold text-xs ${isAgent ? 'text-white' : 'text-blue-700'} uppercase tracking-wide">Comprobante Adjunto:</p>
                        <a href="${imgUrl}" target="_blank" class="block group overflow-hidden rounded-lg border border-slate-200 bg-white">
                            <img src="${imgUrl}" alt="Comprobante de Pago" class="max-h-64 w-auto object-contain mx-auto group-hover:scale-105 transition duration-200">
                        </a>
                        <span class="text-[10px] ${isAgent ? 'text-blue-200' : 'text-slate-400'} block">Clic para abrir en tamaño original ↗</span>
                    </div>
                `;
            } else {
                innerHtml = `<p class="whitespace-pre-line">${content}</p>`;
            }

            const timeStr = msg.created_at || new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
            const wrapper = document.createElement('div');
            if (msg.id) wrapper.setAttribute('data-msg-id', msg.id);
            wrapper.className = `flex ${isAgent ? 'justify-end' : (isSystem ? 'justify-center' : 'justify-start')}`;

            const bubbleClass = isAgent 
                ? 'bg-blue-600 text-white rounded-br-none' 
                : (isSystem ? 'bg-amber-100 text-amber-900 border border-amber-200 text-xs' : 'bg-white text-slate-800 border rounded-bl-none');

            const timeClass = isAgent ? 'text-blue-200 text-right' : 'text-slate-400';

            wrapper.innerHTML = `
                <div class="max-w-md rounded-xl p-3.5 shadow-sm text-sm ${bubbleClass}">
                    ${innerHtml}
                    <span class="text-[10px] block mt-1 ${timeClass}">
                        ${timeStr}
                    </span>
                </div>
            `;

            container.appendChild(wrapper);
            container.scrollTop = container.scrollHeight;
        }

        <?php if ($selectedConv): ?>
            const currentChatId = <?= (int)$selectedConv->id ?>;
            let lastKnownId = <?= (int)$lastMessageId ?>;

            // --- SONDEO AUTOMÁTICO (Cada 2.5 segundos) ---
            setInterval(() => {
                fetch(`/panel/chats/${currentChatId}/messages-poll?after=${lastKnownId}`)
                    .then(res => res.json())
                    .then(data => {
                        if (data.messages && data.messages.length > 0) {
                            data.messages.forEach(m => {
                                renderIncomingMessage(m);
                                if (m.id > lastKnownId) {
                                    lastKnownId = m.id;
                                }
                            });
                        }
                    })
                    .catch(err => console.debug("Error verificando mensajes:", err));
            }, 2500);

            // --- INTENTO DE CONEXIÓN ECHO / WEBSOCKET ---
            try {
                window.Pusher = Pusher;
                window.Echo = new Echo({
                    broadcaster: 'pusher',
                    key: '{{ env("PUSHER_APP_KEY") }}',
                    cluster: '{{ env("PUSHER_APP_CLUSTER", "mt1") }}',
                    forceTLS: false,
                    disableStats: true
                });

                window.Echo.channel('chat.' + currentChatId)
                    .listen('MessageSent', (data) => {
                        const msg = data.message || data;
                        renderIncomingMessage(msg);
                        if (msg.id && msg.id > lastKnownId) {
                            lastKnownId = msg.id;
                        }
                    });
            } catch (e) {
                console.warn("Echo no disponible, operando en modo sondeo automático.");
            }
        <?php endif; ?>
    </script>
</body>
</html>