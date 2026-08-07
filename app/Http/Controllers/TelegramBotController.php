<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Laravel\Facades\Telegram;
use App\Models\Faq;
use App\Models\Conversation;
use App\Events\MessageSent;

class TelegramBotController extends Controller
{
    public function handle(Request $request)
    {
        // Registrar en logs exactamente lo que llega de Telegram
        Log::info('Webhook Telegram recibido:', $request->all());

        try {
            $update = Telegram::getWebhookUpdate();
            
            if (!$update->has('message')) {
                Log::warning('Telegram update no tiene mensaje');
                return response()->json(['status' => 'no_message']);
            }

            $message = $update->getMessage();
            $chatId = $message->getChat()->getId();
            $text = trim($message->getText());
            $userName = $message->getFrom()->getFirstName() ?? 'Usuario';

            $conversation = Conversation::firstOrCreate(
                ['telegram_chat_id' => $chatId],
                ['status' => 'bot', 'user_name' => $userName]
            );

            // Guardar mensaje recibido
            $userMsg = $conversation->messages()->create([
                'sender' => 'user',
                'text' => $text,
            ]);
            
            broadcast(new MessageSent($userMsg));

            if ($conversation->status === 'human') {
                return response()->json(['status' => 'ok']);
            }

            $faqAnswer = $this->searchFaq($text);

            if ($faqAnswer) {
                Telegram::sendMessage([
                    'chat_id' => $chatId, 
                    'text' => $faqAnswer
                ]);

                $botMsg = $conversation->messages()->create([
                    'sender' => 'bot', 
                    'text' => $faqAnswer
                ]);
                
                broadcast(new MessageSent($botMsg));
            } else {
                $conversation->update(['status' => 'human']);
                $transferMessage = "No encontré una respuesta exacta. Te he transferido con un agente de nuestra gerencia.";

                Telegram::sendMessage([
                    'chat_id' => $chatId, 
                    'text' => $transferMessage
                ]);

                $botMsg = $conversation->messages()->create([
                    'sender' => 'bot', 
                    'text' => $transferMessage
                ]);

                broadcast(new MessageSent($botMsg));
            }

            return response()->json(['status' => 'ok']);

        } catch (\Exception $e) {
            Log::error('Error procesando Webhook de Telegram: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    private function searchFaq(string $text): ?string
    {
        $faqs = Faq::all();
        foreach ($faqs as $faq) {
            if (mb_stripos($text, $faq->question) !== false) {
                return $faq->answer;
            }
            if ($faq->keywords) {
                foreach (explode(',', $faq->keywords) as $keyword) {
                    if (mb_stripos($text, trim($keyword)) !== false) {
                        return $faq->answer;
                    }
                }
            }
        }
        return null;
    }
}