<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Faq;
use App\Events\MessageSent;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramBotController extends Controller
{
    public function handleWebhook(Request $request)
    {
        try {
            $data = $request->all();
            
            if (!isset($data['message']['text'])) {
                return response()->json(['status' => 'success']);
            }

            $chatId = $data['message']['chat']['id'];
            $text = trim($data['message']['text']);
            $userName = $data['message']['from']['first_name'] ?? 'Usuario';

            // 1. Obtener o crear conversación
            $conversation = Conversation::firstOrCreate(
                ['telegram_chat_id' => $chatId],
                ['user_name' => $userName, 'status' => 'bot']
            );

            // 2. Guardar mensaje del usuario en BD y notificar por Pusher al panel
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

            // 3. Reiniciar a modo 'bot' al escribir menú o comandos iniciales
            if (in_array($lowerText, ['/start', 'hola', 'menu', 'menú', 'inicio'])) {
                $conversation->update(['status' => 'bot']);
                $replyText = $this->getMenuText($userName);
                $this->saveAndSendBotMessage($conversation, $chatId, $replyText);
                return response()->json(['status' => 'success']);
            }

            // 4. Si la conversación está asignada a un agente ('human'), el bot NO responde
            if ($conversation->status === 'human') {
                return response()->json(['status' => 'success']);
            }

            // 5. Búsqueda en Faq (por número de opción o por coincidencia de texto)
            $faq = null;
            $faqs = Faq::all();

            if (is_numeric($text)) {
                $index = ((int)$text) - 1;
                if (isset($faqs[$index])) {
                    $faq = $faqs[$index];
                }
            } else {
                $faq = Faq::where('question', 'LIKE', "%{$text}%")->first();
            }

            if ($faq) {
                $replyText = $faq->answer;
            } else {
                // Transferir a agente usando el valor 'human'
                $conversation->update(['status' => 'human']);
                $replyText = "👨‍💻 No encontré esa opción. Te he derivado con un agente humano y te responderemos a la brevedad.";
            }

            $this->saveAndSendBotMessage($conversation, $chatId, $replyText);

            return response()->json(['status' => 'success']);

        } catch (\Exception $e) {
            Log::error("Error crítico en Telegram Webhook: " . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 200);
        }
    }

    private function getMenuText($userName)
    {
        $faqs = Faq::all();

        $menu = "👋 ¡Hola, {$userName}! Bienvenido a nuestro servicio de atención.\n\n";
        $menu .= "Escribe el número de la opción que deseas consultar:\n\n";

        if ($faqs->count() > 0) {
            foreach ($faqs as $index => $faq) {
                $number = $index + 1;
                $menu .= "{$number}. {$faq->question}\n";
            }
        } else {
            $menu .= "No hay preguntas frecuentes registradas.\n";
        }

        $menu .= "\n💬 O escribe tu duda directamente para hablar con un agente.";

        return $menu;
    }

    private function saveAndSendBotMessage($conversation, $chatId, $replyText)
    {
        $botMsg = Message::create([
            'conversation_id' => $conversation->id,
            'sender' => 'bot',
            'text' => $replyText,
        ]);

        try {
            broadcast(new MessageSent($botMsg))->toOthers();
        } catch (\Exception $e) {}

        $token = env('TELEGRAM_BOT_TOKEN');
        Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
            'chat_id' => $chatId,
            'text' => $replyText,
        ]);
    }
}