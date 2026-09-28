<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\TrainingArea;
use App\Models\Course;
use App\Models\B2bQuote;
use App\Events\MessageSent;

class TelegramBotController extends Controller
{
    protected string $token;
    protected string $apiUrl;

    public function __construct()
    {
        $this->token = config('services.telegram.bot_token', env('TELEGRAM_BOT_TOKEN'));
        $this->apiUrl = "https://api.telegram.org/bot{$this->token}/";
    }

    public function handleWebhook(Request $request)
    {
        $update = $request->all();

        // 1. Manejo de botones Inline (Callback Queries)
        if (isset($update['callback_query'])) {
            $this->handleCallbackQuery($update['callback_query']);
            return response()->json(['status' => 'ok']);
        }

        // 2. Manejo de mensajes de texto y archivos/fotos
        if (isset($update['message'])) {
            $this->handleIncomingMessage($update['message']);
            return response()->json(['status' => 'ok']);
        }

        return response()->json(['status' => 'ignored']);
    }

    protected function handleIncomingMessage(array $message)
    {
        $chatId = $message['chat']['id'];
        $text = trim($message['text'] ?? $message['caption'] ?? '');
        $senderName = trim(($message['from']['first_name'] ?? '') . ' ' . ($message['from']['last_name'] ?? ''));

        // Obtener o registrar la conversación
        $conversation = Conversation::firstOrCreate(
            ['telegram_chat_id' => $chatId],
            [
                'user_name' => $senderName ?: 'Usuario Telegram',
                'status' => 'bot',
                'step' => 'main_menu',
            ]
        );

        $imageUrl = $this->extractAndDownloadFile($message);

        // 1. Recepción de comprobante de pago
        if ($conversation->step === 'waiting_payment_receipt' || ($imageUrl && $conversation->status !== 'human')) {
            $conversation->update([
                'step' => 'main_menu',
                'status' => 'human',
            ]);

            $bodyText = $imageUrl ? "[IMG]{$imageUrl}" : "💳 Comprobante enviado: " . ($text ?: '[Archivo adjunto]');
            if ($imageUrl && !empty($text)) {
                $bodyText .= "\n📝 Nota: " . $text;
            }

            $msg = Message::create([
                'conversation_id' => $conversation->id,
                'sender' => 'user',
                'text' => $bodyText,
            ]);

            $this->safeBroadcast($msg);

            $this->sendMessage(
                $chatId,
                "📤 *¡Comprobante recibido con éxito!*\n\nTu pago ha sido asignado a un asesor para su validación y formalización en el sistema.",
                [
                    [['text' => '🔙 Volver al Menú Principal', 'callback_data' => 'menu_principal']]
                ]
            );
            return;
        }

        // 2. Modo asesor humano activo
        if ($conversation->status === 'human') {
            $bodyContent = $imageUrl ? "[IMG]{$imageUrl}" : ($text ?: '[Archivo o Multimedia]');

            $msg = Message::create([
                'conversation_id' => $conversation->id,
                'sender' => 'user',
                'text' => $bodyContent,
            ]);

            $this->safeBroadcast($msg);
            return;
        }

        // 3. Comandos globales de reinicio
        if ($text === '/start' || $text === 'Menú Principal' || $text === '🔙 Menú Principal') {
            $conversation->update(['step' => 'main_menu', 'status' => 'bot']);
            $this->sendMainMenu($chatId);
            return;
        }

        // 4. Flujo paso a paso de cotización B2B
        switch ($conversation->step) {
            case 'b2b_empresa':
                if (empty($text)) {
                    $this->sendMessage($chatId, "⚠️ Por favor ingresa el nombre de la empresa o institución:");
                    return;
                }

                Message::create([
                    'conversation_id' => $conversation->id,
                    'sender' => 'user',
                    'text' => "🏢 Empresa: " . $text,
                ]);

                B2bQuote::create([
                    'telegram_chat_id' => $chatId,
                    'company_name'     => $text,
                    'rif'              => 'Pendiente',
                    'employees_count'  => 'Pendiente',
                    'area_interest'    => 'Pendiente',
                    'status'           => 'pending',
                ]);

                $conversation->update(['step' => 'b2b_rif']);
                $promptRif = "🏢 Empresa registrada: *{$text}*\n\nPor favor, ingresa el *RIF* de la empresa (Ejemplo: J-12345678-9):";
                
                $this->sendMessage($chatId, $promptRif);
                return;

            case 'b2b_rif':
                if (empty($text)) {
                    $this->sendMessage($chatId, "⚠️ Por favor indica el RIF de la empresa:");
                    return;
                }

                Message::create([
                    'conversation_id' => $conversation->id,
                    'sender' => 'user',
                    'text' => "📄 RIF: " . $text,
                ]);

                $quote = B2bQuote::where('telegram_chat_id', $chatId)->where('status', 'pending')->latest()->first();
                if ($quote) {
                    $quote->update(['rif' => $text]);
                }

                $conversation->update(['step' => 'b2b_empleados']);
                $promptEmpl = "👥 RIF: *{$text}*\n\n¿Para *cuántos participantes* estiman la capacitación? (Indica una cantidad aproximada):";
                
                $this->sendMessage($chatId, $promptEmpl);
                return;

            case 'b2b_empleados':
                if (empty($text)) {
                    $this->sendMessage($chatId, "⚠️ Por favor indica la cantidad aproximada de participantes:");
                    return;
                }

                Message::create([
                    'conversation_id' => $conversation->id,
                    'sender' => 'user',
                    'text' => "👥 Participantes: " . $text,
                ]);

                $quote = B2bQuote::where('telegram_chat_id', $chatId)->where('status', 'pending')->latest()->first();
                if ($quote) {
                    $quote->update(['employees_count' => $text]);
                }

                $conversation->update(['step' => 'b2b_area']);
                $this->sendB2bCourseSelection($chatId);
                return;

            default:
                $this->sendMainMenu($chatId);
                break;
        }
    }

    protected function sendB2bCourseSelection($chatId)
    {
        $courses = Course::all();
        $keyboard = [];

        if ($courses->isNotEmpty()) {
            foreach ($courses as $c) {
                $btnText = mb_substr($c->title, 0, 35);
                $keyboard[] = [['text' => "🎓 " . $btnText, 'callback_data' => "b2bcourse_" . $c->id]];
            }
        } else {
            $areas = TrainingArea::all();
            foreach ($areas as $a) {
                $keyboard[] = [['text' => "{$a->icon} {$a->name}", 'callback_data' => "b2barea_" . $a->slug]];
            }
        }

        $keyboard[] = [['text' => '🌐 Otra Formación / A Medida', 'callback_data' => 'b2bcourse_otro']];
        $keyboard[] = [['text' => '🔙 Cancelar y Volver', 'callback_data' => 'menu_principal']];

        $prompt = "🎯 Selecciona el *curso o programa técnico* en el que tienen interés para la cotización institucional:";
        $this->sendMessage($chatId, $prompt, $keyboard);
    }

    protected function handleCallbackQuery(array $callback)
    {
        $chatId = $callback['message']['chat']['id'];
        $messageId = $callback['message']['message_id'] ?? null;
        $data = $callback['data'];
        $callbackId = $callback['id'];

        // Confirmar callback inmediatamente a Telegram para frenar reintentos por timeout
        $this->telegramPost('answerCallbackQuery', ['callback_query_id' => $callbackId]);

        $conversation = Conversation::firstOrCreate(
            ['telegram_chat_id' => $chatId],
            ['user_name' => 'Usuario Telegram', 'status' => 'bot', 'step' => 'main_menu']
        );

        if ($data === 'menu_principal') {
            $conversation->update(['step' => 'main_menu', 'status' => 'bot']);
            $this->sendMainMenu($chatId);
            return;
        }

        if ($data === 'menu_catalogo') {
            $this->sendCatalogMenu($chatId);
            return;
        }

        if (str_starts_with($data, 'area_')) {
            $slug = str_replace('area_', '', $data);
            $this->sendAreaCourses($chatId, $slug);
            return;
        }

        // Selección interactiva de curso B2B mediante botones
        if (str_starts_with($data, 'b2bcourse_') || str_starts_with($data, 'b2barea_')) {
            // Protección contra reintentos: si ya no está en el paso b2b_area, ignoramos reintentos duplicados
            if ($conversation->step !== 'b2b_area') {
                return;
            }

            // Buscar la cotización en progreso
            $quote = B2bQuote::where('telegram_chat_id', $chatId)
                ->where('status', 'pending')
                ->latest()
                ->first();

            // Si ya no hay cotización pendiente (ya se procesó), salimos
            if (!$quote) {
                return;
            }

            // Bloqueamos inmediatamente el step para que cualquier reintento concurrente sea descartado
            $conversation->update([
                'step'   => 'main_menu',
                'status' => 'human',
            ]);

            $selectedTitle = 'Programa a medida';
            if (str_starts_with($data, 'b2bcourse_')) {
                $courseId = str_replace('b2bcourse_', '', $data);
                if ($courseId === 'otro') {
                    $selectedTitle = 'Formación Personalizada / A Medida';
                } else {
                    $c = Course::find($courseId);
                    $selectedTitle = $c ? $c->title : 'Curso Especializado';
                }
            } elseif (str_starts_with($data, 'b2barea_')) {
                $areaSlug = str_replace('b2barea_', '', $data);
                $a = TrainingArea::where('slug', $areaSlug)->first();
                $selectedTitle = $a ? $a->name : 'Área Técnica';
            }

            $companyName = $quote->company_name ?? 'No especificada';
            $rif = $quote->rif ?? 'No especificado';
            $employees = $quote->employees_count ?? 'No especificado';

            // Marcamos como completada
            $quote->update([
                'area_interest' => $selectedTitle,
                'status'        => 'completed',
            ]);

            // Guardar selección del curso en messages
            Message::create([
                'conversation_id' => $conversation->id,
                'sender' => 'user',
                'text' => "🎯 Curso seleccionado: " . $selectedTitle,
            ]);

            // Mensaje de cierre al usuario
            $this->sendMessage(
                $chatId,
                "✅ *¡Solicitud Corporativa Recibida!*\n\nHemos registrado la solicitud para el programa:\n*{$selectedTitle}*.\n\nUn asesor de la Gerencia de Formación revisará el requerimiento para emitir la propuesta formal.",
                [
                    [['text' => '🔙 Volver al Menú Principal', 'callback_data' => 'menu_principal']]
                ]
            );

            // Notificación al panel de asesores
            $notif = Message::create([
                'conversation_id' => $conversation->id,
                'sender'          => 'system',
                'text'            => "📋 *Nueva Solicitud B2B:*\n• Empresa: {$companyName}\n• RIF: {$rif}\n• Participantes: {$employees}\n• Curso / Área: {$selectedTitle}",
            ]);

            $this->safeBroadcast($notif);
            return;
        }

        switch ($data) {
            case 'menu_b2b':
                $conversation->update(['step' => 'main_menu']);
                $this->sendB2bMenu($chatId);
                break;

            case 'b2b_iniciar':
                $conversation->update(['step' => 'b2b_empresa']);
                $this->sendMessage($chatId, "🏢 *Solicitud de Formación Corporativa (B2B)*\n\nPor favor, ingresa el *Nombre de tu Empresa o Institución*:");
                break;

            case 'menu_requisitos':
                $this->sendRequirementsMenu($chatId);
                break;

            case 'menu_pagos':
                $this->sendPaymentsMenu($chatId);
                break;

            case 'menu_faq':
                $this->sendFaqMenu($chatId);
                break;

            case 'upload_receipt':
                $conversation->update(['step' => 'waiting_payment_receipt']);
                $this->sendMessage($chatId, "📤 *Carga de Comprobante*\n\nPor favor, adjunta la *foto o captura* de tu comprobante de pago por este mismo chat:");
                break;

            case 'hablar_asesor':
                if ($conversation->status === 'human') {
                    $this->sendMessage($chatId, "⏳ Ya tu caso está asignado a un asesor. Por favor escribe tu consulta directamente por aquí y te responderemos a la brevedad.");
                    return;
                }

                $conversation->update(['status' => 'human']);

                $this->sendMessage(
                    $chatId,
                    "👨‍💻 *Conectando con un Asesor...*\n\nTu conversación ha sido transferida a nuestro equipo de atención. Puedes escribir tu consulta por aquí y en breve te responderemos.",
                    [
                        [['text' => '🔙 Salir al Menú Principal', 'callback_data' => 'menu_principal']]
                    ]
                );

                $msg = Message::create([
                    'conversation_id' => $conversation->id,
                    'sender' => 'system',
                    'text' => '🔔 El usuario ha solicitado hablar con un asesor.',
                ]);
                $this->safeBroadcast($msg);
                break;
        }
    }

    public function sendMainMenu($chatId)
    {
        $text = "¡Hola! 👋 Bienvenid@ al Bot Oficial de la *Gerencia de Formación de CONATEL*.\n\n"
              . "Desarrollamos talento técnico en telecomunicaciones y tecnologías emergentes bajo estándares globales.\n\n"
              . "🎓 *Certificaciones:* Emitidas con respaldo institucional de CONATEL y avaladas por instituciones universitarias del sector.\n\n"
              . "¿Cómo podemos orientarte hoy? Selecciona una opción 👇";

        $keyboard = [
            [['text' => '📚 Oferta Académica', 'callback_data' => 'menu_catalogo']],
            [['text' => '🏢 Formación Corporativa / In-Company (B2B)', 'callback_data' => 'menu_b2b']],
            [['text' => '📝 Requisitos y Registro', 'callback_data' => 'menu_requisitos']],
            [['text' => '💳 Pagos y Facturación Fiscal', 'callback_data' => 'menu_pagos']],
            [['text' => '❓ Preguntas Frecuentes', 'callback_data' => 'menu_faq']],
            [['text' => '💬 Hablar con un Asesor', 'callback_data' => 'hablar_asesor']],
        ];

        $this->sendMessage($chatId, $text, $keyboard);
    }

    public function sendCatalogMenu($chatId)
    {
        $text = "Explora nuestras *5 áreas de formación especializada*.\n\n"
              . "Nuestras formaciones se dictan en modalidad *Presencial Teórico-Práctico* en la sede principal de CONATEL (Las Mercedes, Caracas):";

        $areas = TrainingArea::all();
        $keyboard = [];

        foreach ($areas as $area) {
            $keyboard[] = [['text' => "{$area->icon} {$area->name}", 'callback_data' => "area_{$area->slug}"]];
        }

        $keyboard[] = [['text' => '🔙 Menú Principal', 'callback_data' => 'menu_principal']];

        $this->sendMessage($chatId, $text, $keyboard);
    }

    public function sendAreaCourses($chatId, $slug)
    {
        $area = TrainingArea::where('slug', $slug)->with('courses')->first();

        if (!$area) {
            $this->sendCatalogMenu($chatId);
            return;
        }

        $text = "{$area->icon} *Área: {$area->name}*\n\n";

        foreach ($area->courses as $course) {
            $text .= "🔹 *{$course->title}*\n";
            $text .= "⏱️ *Duración:* {$course->duration}\n";
            $text .= "🎯 *Propósito:* {$course->purpose}\n";
            $text .= "🎖️ *Certificación:* {$course->certification}\n\n";
        }

        $keyboard = [
            [['text' => '📝 Inscribirme en un curso', 'callback_data' => 'menu_requisitos']],
            [['text' => '💬 Consultar Fechas con un Asesor', 'callback_data' => 'hablar_asesor']],
            [['text' => '🔙 Volver a Áreas', 'callback_data' => 'menu_catalogo']],
        ];

        $this->sendMessage($chatId, $text, $keyboard);
    }

    public function sendB2bMenu($chatId)
    {
        $text = "🏢 *Programas de Capacitación Corporativa (In-Company)*\n\n"
              . "Diseñamos planes adaptados a las exigencias operativas de empresas, ISPs e instituciones en Ciberseguridad, Redes 5G, Fibra Óptica e IA.\n\n"
              . "✅ Emisión de *Factura Fiscal Oficial* a nombre de tu empresa.\n"
              . "✅ Formación en sede CONATEL o en tus instalaciones técnicas.";

        $keyboard = [
            [['text' => '📄 Solicitar Cotización B2B', 'callback_data' => 'b2b_iniciar']],
            [['text' => '👤 Hablar con un Asesor B2B', 'callback_data' => 'hablar_asesor']],
            [['text' => '🔙 Menú Principal', 'callback_data' => 'menu_principal']],
        ];

        $this->sendMessage($chatId, $text, $keyboard);
    }

    public function sendRequirementsMenu($chatId)
    {
        $text = "📝 *Requisitos para formalizar tu inscripción:*\n\n"
              . "• Fotocopia de la Cédula de Identidad.\n"
              . "• Comprobante de pago original o transferencia bancaria.\n"
              . "• Teléfono de contacto y correo electrónico activo.\n\n"
              . "⚠️ *Asistencia requerida:*\n"
              . "Por normativa académica, se requiere cumplir con un mínimo del *75% de asistencia presencial* para recibir el certificado avalado por CONATEL.\n\n"
              . "📌 *Nota:* Puedes formalizar la inscripción de un tercero consignando sus datos completos y comprobante.";

        $keyboard = [
            [['text' => '💳 Consultar Datos Bancarios', 'callback_data' => 'menu_pagos']],
            [['text' => '📤 Adjuntar Comprobante de Pago', 'callback_data' => 'upload_receipt']],
            [['text' => '🔙 Menú Principal', 'callback_data' => 'menu_principal']],
        ];

        $this->sendMessage($chatId, $text, $keyboard);
    }

    public function sendPaymentsMenu($chatId)
    {
        $text = "💳 *Información de Pagos y Atención:*\n\n"
              . "🏢 *Facturación Fiscal:* Disponible notificando el RIF de la empresa.\n"
              . "📍 *Atención Presencial:* Sede Principal CONATEL, Av. Veracruz con Calle Cali, Las Mercedes, Caracas.\n"
              . "🕒 *Horario:* Lunes a Viernes de 8:00 a. m. a 4:30 p. m.\n"
              . "📞 *Central Telefónica:* 0212-9090420 / 0212-9090596 / 0212-9090347\n"
              . "✉️ *Correo Electrónico:* gestiondecursos@conatel.gob.ve\n\n"
              . "⚠️ *Condiciones:* En caso de fuerza mayor la actividad será reprogramada o el monto transferido a otra formación. No se realizan reintegros en efectivo.";

        $keyboard = [
            [['text' => '💬 Notificar Pago por WhatsApp (0426-6261146)', 'url' => 'https://wa.me/584266261146?text=Hola,%20deseo%20notificar%20un%20pago%20de%20inscripción']],
            [['text' => '📤 Cargar Comprobante por aquí', 'callback_data' => 'upload_receipt']],
            [['text' => '👨‍💻 Hablar con un Asesor', 'callback_data' => 'hablar_asesor']],
            [['text' => '🔙 Menú Principal', 'callback_data' => 'menu_principal']],
        ];

        $this->sendMessage($chatId, $text, $keyboard);
    }

    public function sendFaqMenu($chatId)
    {
        $text = "❓ *Preguntas Frecuentes*\n\n"
              . "• *¿Dónde se cursan las clases?* En las aulas y laboratorios de CONATEL, Las Mercedes, Caracas.\n"
              . "• *¿Cómo obtengo el certificado?* Asistiendo como mínimo al 75% de las sesiones.\n"
              . "• *¿Ofrecen atención corporativa?* Sí, cotizamos cursos In-Company con factura fiscal.\n\n"
              . "Si tienes alguna consulta específica, comunícate directamente con un asesor:";

        $keyboard = [
            [['text' => '💬 Hablar con un Asesor', 'callback_data' => 'hablar_asesor']],
            [['text' => '🔙 Menú Principal', 'callback_data' => 'menu_principal']],
        ];

        $this->sendMessage($chatId, $text, $keyboard);
    }

    /**
     * Extrae y descarga el archivo (foto comprimida o documento sin comprimir) desde Telegram
     */
    protected function extractAndDownloadFile(array $message): ?string
    {
        try {
            $fileId = null;

            if (isset($message['photo'])) {
                $lastPhoto = end($message['photo']);
                $fileId = $lastPhoto['file_id'];
            } elseif (isset($message['document'])) {
                $fileId = $message['document']['file_id'];
            }

            if (!$fileId) {
                return null;
            }

            $fileInfo = Http::withoutVerifying()->get($this->apiUrl . "getFile?file_id={$fileId}");
            
            if ($fileInfo->successful() && isset($fileInfo->json()['result']['file_path'])) {
                $filePath = $fileInfo->json()['result']['file_path'];
                $downloadUrl = "https://api.telegram.org/file/bot{$this->token}/{$filePath}";

                $contents = Http::withoutVerifying()->get($downloadUrl)->body();
                $filename = 'receipts/' . uniqid() . '_' . basename($filePath);
                
                Storage::disk('public')->put($filename, $contents);

                return Storage::url($filename);
            }
        } catch (\Throwable $e) {
            Log::error("Error descargando archivo de Telegram: " . $e->getMessage());
        }

        return null;
    }

    protected function safeBroadcast($eventModel): void
    {
        try {
            broadcast(new MessageSent($eventModel))->toOthers();
        } catch (\Throwable $e) {
            Log::warning("Broadcast ignorado: " . $e->getMessage());
        }
    }

    protected function sendMessage($chatId, string $text, array $inlineKeyboard = [])
    {
        $params = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
        ];

        if (!empty($inlineKeyboard)) {
            $params['reply_markup'] = json_encode(['inline_keyboard' => $inlineKeyboard]);
        }

        return $this->telegramPost('sendMessage', $params);
    }

    protected function telegramPost(string $method, array $params)
    {
        return Http::withoutVerifying()->post($this->apiUrl . $method, $params);
    }
}