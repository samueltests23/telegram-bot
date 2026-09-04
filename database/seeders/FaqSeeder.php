<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Faq;
use Illuminate\Support\Facades\Schema;

class FaqSeeder extends Seeder
{
    public function run(): void
    {
        // Limpiar la tabla de FAQs previas de forma compatible con SQLite y MySQL
        Schema::disableForeignKeyConstraints();
        Faq::truncate();
        Schema::enableForeignKeyConstraints();

        $faqs = [
            // BLOQUE I: PRESENCIAL
            [
                'question' => '¿Dónde me inscribo para un curso presencial?',
                'answer' => "📍 Inscripción Presencial:\n• Acudiendo a nuestras oficinas de lunes a viernes, de 8:00 a.m. a 4:30 p.m.\n• Vía Correo/WhatsApp: Enviando tus datos y comprobante de pago a gestiondecursos@conatel.gob.ve o al WhatsApp: 0426-6261146 (Horario: Lun-Vie 8:00am a 4:30pm)."
            ],
            [
                'question' => '¿Cuáles son los requisitos para inscribirme?',
                'answer' => "📋 Requisitos de inscripción:\n1. Fotocopia de la cédula de identidad.\n2. Comprobante de pago del curso.\n3. Número de contacto."
            ],
            [
                'question' => '¿Puedo inscribir a otra persona?',
                'answer' => "👥 Sí, puedes inscribir a un tercero proporcionando la información personal completa del estudiante y el comprobante de pago a su nombre. Para más información contacta a Comercialización: 0212-909-0420 / 0212-909-0596 / 0212-909-0347."
            ],
            [
                'question' => '¿Dónde se dictan los cursos presenciales?',
                'answer' => "🏛️ Se dictan en las instalaciones de CONATEL y espacios habilitados. Recibirás la ubicación exacta en la confirmación de tu inscripción."
            ],
            [
                'question' => '¿Hay cupos limitados?',
                'answer' => "⚠️ Sí, contamos con cupos limitados por la capacidad de nuestras instalaciones. Te recomendamos completar tu pago e inscripción lo antes posible."
            ],
            [
                'question' => '¿Cómo sé si mi curso fue confirmado?',
                'answer' => "✉️ Recibirás una notificación formal vía correo electrónico. También puedes consultar el estatus por WhatsApp al 0426-6261146."
            ],
            [
                'question' => '¿Qué pasa si no puedo asistir a una clase presencial?',
                'answer' => "📌 Debes notificar y justificar tu inasistencia. Según la Ley Orgánica de Educación, es indispensable cumplir con mínimo el 75% de asistencia para optar al certificado."
            ],
            [
                'question' => '¿Qué certificado entregan en la modalidad presencial?',
                'answer' => "🎓 Se emite un certificado oficial de PARTICIPACIÓN respaldado institucionalmente por CONATEL."
            ],
            [
                'question' => '¿Qué pasa si el curso se suspende por fuerza mayor?',
                'answer' => "🔄 La actividad será reprogramada o podrás transferir el monto pagado a otra oferta formativa. No se realizan reembolsos en dinero."
            ],

            // BLOQUE II: CAMPUS VIRTUAL
            [
                'question' => '¿Para quién están diseñados los cursos virtuales?',
                'answer' => "🎯 Dirigidos a estudiantes, técnicos y profesionales que deseen actualizarse o iniciar desde cero en telecomunicaciones. Modalidad flexible adaptada a tu disponibilidad."
            ],
            [
                'question' => '¿Necesito conocimientos previos?',
                'answer' => "📚 Varía según el programa. Puedes verificar los requisitos en www.conatel.gob.ve, en Instagram @conatelvzla o vía WhatsApp al 0426-6261146."
            ],
            [
                'question' => '¿Cuáles son los requisitos de ingreso al Campus Virtual?',
                'answer' => "✅ Cumplir con los requisitos académicos específicos exigidos en el programa correspondiente."
            ],
            [
                'question' => '¿Hay límite de edad?',
                'answer' => "🔞 Varía según la oferta formativa. Puedes consultar las especificaciones en la carta descriptiva de cada curso."
            ],
            [
                'question' => '¿Puedo solicitar el temario detallado?',
                'answer' => "📑 Puedes solicitarlo enviando un correo a gestiondecursos@conatel.gob.ve, escribiendo al WhatsApp 0426-6261146 o descargándolo directamente en la web al activarse la oferta."
            ],
            [
                'question' => '¿Dónde consulto las fechas de inicio?',
                'answer' => "📅 En www.conatel.gob.ve o en redes sociales oficiales:\n• Instagram: @conatelvzla\n• X (Twitter): @conatel\n• WhatsApp: 0426-6261146\n• Correo: gestiondecursos@conatel.gob.ve"
            ],
            [
                'question' => '¿Dónde me dirijo si tengo una falla técnica en el aula?',
                'answer' => "💻 Notifica cualquier fallo en la plataforma haciendo clic en el botón 'Soporte técnico' o enviando un formulario de incidencia."
            ],
            [
                'question' => '¿Dónde puedo ver mis cursos matriculados?',
                'answer' => "🖥️ Al iniciar sesión, ingresa al menú principal y haz clic en 'Mis Cursos' o 'Área Personal'."
            ],
            [
                'question' => '¿Cómo subo una tarea a la plataforma?',
                'answer' => "📤 Pasos:\n1. Ingresa a la actividad y haz clic en la 'Tarea'.\n2. Presiona 'Añadir entrega'.\n3. Adjunta o arrastra el archivo.\n4. Haz clic en 'Guardar cambios'."
            ],
            [
                'question' => '¿Qué requisitos técnicos necesito para el Campus Virtual?',
                'answer' => "💻 Requerimientos:\n• PC con Windows XP o superior (o equivalente macOS/Linux).\n• Conexión estable a internet de banda ancha.\n• Soporte para audio y video.\n• Software específico según el curso (indicado en la carta descriptiva)."
            ],

            // BLOQUE III: PAGOS Y ATENCIÓN
            [
                'question' => '¿Cuál es la inversión total y qué incluye?',
                'answer' => "💳 El costo varía según el curso e incluye:\n• Acceso 24/7 al Campus Virtual.\n• Materiales digitales y recursos multimedia.\n• Acompañamiento de facilitadores expertos.\n• Evaluaciones y certificado digital oficial."
            ],
            [
                'question' => '¿Se puede pagar en partes o cuotas?',
                'answer' => "🔢 Dependiendo del programa, existe la opción de dividir el pago en cuotas. Consulta la disponibilidad al pedir información del curso."
            ],
            [
                'question' => '¿Emiten factura fiscal a nombre de empresas?',
                'answer' => "🧾 Sí, emitimos factura fiscal oficial. Si financia tu empresa, solo debes proporcionar el RIF e información fiscal al registrar el pago."
            ],
            [
                'question' => 'Pagué mi inscripción pero aún no me activan, ¿qué hago?',
                'answer' => "⏳ La activación toma de 24 a 48 horas hábiles. Si excede ese tiempo, envía tu comprobante, nombre y C.I. a gestiondecursos@conatel.gob.ve o al WhatsApp 0426-6261146."
            ],
            [
                'question' => '¿Cuáles son los canales de atención oficiales y horarios?',
                'answer' => "📞 Horario: Lunes a Viernes de 8:00 a.m. a 4:30 p.m.\n\nContactos:\n• Teléfonos Comercialización: 0212-909-0420 / 0212-909-0596 / 0212-909-0347\n• WhatsApp: 0426-6261146\n• Correo: gestiondecursos@conatel.gob.ve\n• Web: www.conatel.gob.ve\n• Instagram: @conatelvzla\n• X: @conatel"
            ],
        ];

        foreach ($faqs as $faq) {
            Faq::create($faq);
        }
    }
}