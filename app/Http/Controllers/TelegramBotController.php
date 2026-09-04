<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Faq;
use App\Events\MessageSent;

class TelegramBotController extends Controller
{
    private $botToken;

    public function __construct()
    {
        $this->botToken = env('TELEGRAM_BOT_TOKEN');
    }

    public function handleWebhook(Request $request)
    {
        try {
            $update = $request->all();

            // 1. Manejo de clics en Botones Interactivos (Callback Queries)
            if (isset($update['callback_query'])) {
                $chatId = $update['callback_query']['message']['chat']['id'];
                $data = $update['callback_query']['data'];
                $userName = $update['callback_query']['from']['first_name'] ?? 'Usuario';

                $this->handleCallback($chatId, $data, $userName);
                return response()->json(['status' => 'success']);
            }

            // 2. Manejo de Mensajes de Texto del usuario
            if (!isset($update['message']['text'])) {
                return response()->json(['status' => 'success']);
            }

            $chatId = $update['message']['chat']['id'];
            $text = trim($update['message']['text']);
            $userName = $update['message']['from']['first_name'] ?? 'Usuario';

            // Obtener o crear conversación
            $conversation = Conversation::firstOrCreate(
                ['telegram_chat_id' => $chatId],
                ['user_name' => $userName, 'status' => 'bot']
            );

            // Guardar el mensaje del usuario y notificar al panel en tiempo real (Pusher)
            $userMsg = Message::create([
                'conversation_id' => $conversation->id,
                'sender' => 'user',
                'text' => $text,
            ]);

            try {
                broadcast(new MessageSent($userMsg))->toOthers();
            } catch (\Exception $e) {
                Log::error("Error Pusher: " . $e->getMessage());
            }

            $lowerText = mb_strtolower($text);

            // Si escribe inicio o menú, reiniciar conversación a 'bot'
            if (in_array($lowerText, ['/start', 'hola', 'menu', 'menú', 'inicio'])) {
                $conversation->update(['status' => 'bot']);
                $this->sendMainMenu($chatId, $userName);
                return response()->json(['status' => 'success']);
            }

            // Si la conversación ya está asignada a un agente ('human'), el bot no interfiere
            if ($conversation->status === 'human') {
                return response()->json(['status' => 'success']);
            }

            // Buscar en FAQ
            $this->searchFaq($conversation, $chatId, $text);

            return response()->json(['status' => 'success']);

        } catch (\Exception $e) {
            Log::error("Error en Webhook: " . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 200);
        }
    }

    // Menú Principal con Botones
    private function sendMainMenu($chatId, $userName)
    {
        $text = "🏛️ *Formación CONATEL*\n\n¡Hola, {$userName}! Bienvenido/a al asistente virtual. Selecciona una opción o escribe tu consulta directamente:";

        $keyboard = [
            'inline_keyboard' => [
                [['text' => '📍 Cursos Presenciales', 'callback_data' => 'cat_presencial']],
                [['text' => '💻 Campus Virtual / Cursos Online', 'callback_data' => 'cat_virtual']],
                [['text' => '💳 Pagos, Facturas y Contacto', 'callback_data' => 'cat_pagos']],
                [['text' => '👨‍💻 Hablar con un Asesor Comercial', 'callback_data' => 'transfer_agent']],
            ]
        ];

        $this->sendTelegramRequest('sendMessage', [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => json_encode($keyboard)
        ]);
    }

    // Control de Clics en Botones
    private function handleCallback($chatId, $data, $userName)
    {
        $conversation = Conversation::where('telegram_chat_id', $chatId)->first();

        if ($data === 'main_menu') {
            if ($conversation) {
                $conversation->update(['status' => 'bot']);
            }
            $this->sendMainMenu($chatId, $userName);
            return;
        }

        if ($data === 'transfer_agent') {
            if ($conversation) {
                $this->transferToAgent($conversation, $chatId);
            }
            return;
        }

        if (str_starts_with($data, 'cat_')) {
            $this->sendCategoryQuestions($chatId, $data);
            return;
        }

        if (str_starts_with($data, 'faq_')) {
            $faqId = str_replace('faq_', '', $data);
            $faq = Faq::find($faqId);

            if ($faq) {
                $replyText = "❓ *{$faq->question}*\n\n{$faq->answer}";

                $keyboard = [
                    'inline_keyboard' => [
                        [['text' => '⬅️ Volver al Menú Principal', 'callback_data' => 'main_menu']],
                        [['text' => '👨‍💻 Hablar con un Asesor Comercial', 'callback_data' => 'transfer_agent']]
                    ]
                ];

                $this->saveAndSendBotMessage($conversation, $chatId, $replyText, $keyboard);
            }
        }
    }

    // Cursos divididos por categoría
    private function sendCategoryQuestions($chatId, $category)
    {
        $faqs = Faq::all();
        $buttons = [];

        if ($category === 'cat_presencial') {
            $title = "📍 *Preguntas sobre Cursos Presenciales:*";
            $sliced = $faqs->slice(0, 9);
        } elseif ($category === 'cat_virtual') {
            $title = "💻 *Preguntas sobre Campus Virtual:*";
            $sliced = $faqs->slice(9, 10);
        } else {
            $title = "💳 *Pagos y Canales de Atención:*";
            $sliced = $faqs->slice(19, 5);
        }

        foreach ($sliced as $faq) {
            $buttons[] = [['text' => "🔹 " . $faq->question, 'callback_data' => "faq_{$faq->id}"]];
        }

        $buttons[] = [['text' => '⬅️ Volver al Menú Principal', 'callback_data' => 'main_menu']];

        $keyboard = ['inline_keyboard' => $buttons];

        $this->sendTelegramRequest('sendMessage', [
            'chat_id' => $chatId,
            'text' => $title,
            'parse_mode' => 'Markdown',
            'reply_markup' => json_encode($keyboard)
        ]);
    }

    // Búsqueda en FAQ y Enlaces si no coincide
    private function searchFaq($conversation, $chatId, $text)
    {
        $faq = Faq::where('question', 'LIKE', "%{$text}%")->first();

        if (!$faq) {
            $words = explode(' ', $text);
            foreach ($words as $word) {
                if (mb_strlen($word) > 3) {
                    $faq = Faq::where('question', 'LIKE', "%{$word}%")->first();
                    if ($faq) break;
                }
            }
        }

        if ($faq) {
            $replyText = "❓ *{$faq->question}*\n\n{$faq->answer}";
            $keyboard = [
                'inline_keyboard' => [
                    [['text' => '📋 Ver Menú Principal', 'callback_data' => 'main_menu']]
                ]
            ];
        } else {
            $replyText = "🔍 No encontré una respuesta exacta en las preguntas frecuentes para:\n_\"{$text}\"_\n\nPuedes consultar directamente en nuestros canales oficiales o solicitar atención personalizada:";

            // Enlace con la consulta adjunta hacia WhatsApp
            $whatsappMsg = urlencode("Hola, tengo la siguiente consulta: " . $text);
            $whatsappUrl = "https://wa.me/584266261146?text={$whatsappMsg}";

            // Enlace al Canal Oficial de Telegram de CONATEL
            $telegramChannelUrl = "https://t.me/conateloficial"; 

            $keyboard = [
                'inline_keyboard' => [
                    [['text' => '💬 Consultar por WhatsApp', 'url' => $whatsappUrl]],
                    [['text' => '📢 Canal Oficial de Telegram', 'url' => $telegramChannelUrl]],
                    [['text' => '👨‍💻 Solicitar Asesor Comercial', 'callback_data' => 'transfer_agent']],
                    [['text' => '📋 Volver al Menú Principal', 'callback_data' => 'main_menu']]
                ]
            ];
        }

        $this->saveAndSendBotMessage($conversation, $chatId, $replyText, $keyboard);
    }

    // Transferir al Panel de Agentes de Laravel
    private function transferToAgent($conversation, $chatId)
    {
        // Cambiar estado a 'human'
        $conversation->update(['status' => 'human']);

        $replyText = "👨‍💻 *Transferido a un Asesor Comercial*\n\nHemos canalizado tu chat con nuestro equipo de atención. Un asesor se pondrá en contacto contigo a la brevedad por esta misma vía.\n\nEscribe cualquier detalle adicional a continuación.";

        $keyboard = [
            'inline_keyboard' => [
                [['text' => '📋 Volver al Menú Principal', 'callback_data' => 'main_menu']]
            ]
        ];

        $this->saveAndSendBotMessage($conversation, $chatId, $replyText, $keyboard);
    }

    // Guardar mensaje enviado por el bot en BD y transmitirlo por Pusher
    private function saveAndSendBotMessage($conversation, $chatId, $replyText, $keyboard = null)
    {
        if ($conversation) {
            $botMsg = Message::create([
                'conversation_id' => $conversation->id,
                'sender' => 'bot',
                'text' => $replyText,
            ]);

            try {
                broadcast(new MessageSent($botMsg))->toOthers();
            } catch (\Exception $e) {
                Log::error("Error Pusher Bot Msg: " . $e->getMessage());
            }
        }

        $params = [
            'chat_id' => $chatId,
            'text' => $replyText,
            'parse_mode' => 'Markdown',
        ];

        if ($keyboard) {
            $params['reply_markup'] = json_encode($keyboard);
        }

        $this->sendTelegramRequest('sendMessage', $params);
    }

    private function sendTelegramRequest($method, $params)
    {
        Http::post("https://api.telegram.org/bot{$this->botToken}/{$method}", $params);
    }
}