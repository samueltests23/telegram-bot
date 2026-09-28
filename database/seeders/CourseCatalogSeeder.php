<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\TrainingArea;
use App\Models\Course;

class CourseCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $catalog = [
            [
                'slug' => 'ia',
                'name' => 'Inteligencia Artificial',
                'icon' => '🤖',
                'courses' => [
                    [
                        'title' => 'IA Aplicada a Organizaciones',
                        'duration' => '2 Días (16 horas académicas)',
                        'purpose' => 'Transformación de procesos organizacionales mediante herramientas y modelos de IA generativa y analítica.',
                    ],
                    [
                        'title' => 'Gobernanza de la IA y Derechos Humanos',
                        'duration' => '2 Días (16 horas académicas)',
                        'purpose' => 'Marcos regulatorios, ética aplicada, mitigación de sesgos y directrices internacionales (UNESCO/UIT).',
                    ],
                    [
                        'title' => 'Ciencia de Datos con Python para IA',
                        'duration' => '3 Días (24 horas académicas)',
                        'purpose' => 'Tratamiento masivo de datos, modelos predictivos y visualización analítica.',
                    ],
                    [
                        'title' => 'IA para Banca y Fintech',
                        'duration' => '2 Días (16 horas académicas)',
                        'purpose' => 'Detección de fraudes, scoring crediticio automatizado y atención inteligente al cliente.',
                    ],
                    [
                        'title' => 'IA Agéntica: Flujos Autónomos',
                        'duration' => '2 Días (16 horas académicas)',
                        'purpose' => 'Diseño de agentes autónomos interconectados con APIs y herramientas de automatización.',
                    ],
                ]
            ],
            [
                'slug' => 'comunicaciones',
                'name' => 'Comunicaciones',
                'icon' => '📢',
                'courses' => [
                    [
                        'title' => 'El Arte del Buen Hablar y Oratoria Técnica',
                        'duration' => '2 Días (16 horas académicas)',
                        'purpose' => 'Técnicas de expresión oral, manejo escénico y comunicación asertiva para líderes y voceros.',
                    ],
                    [
                        'title' => 'Producción de Podcast y Contenido Radiofónico',
                        'duration' => '2 Días (16 horas académicas)',
                        'purpose' => 'Diseño sonoro, grabación digital, edición y distribución en plataformas de streaming.',
                    ],
                    [
                        'title' => 'Estructura y Redacción de Guión',
                        'duration' => '2 Días (16 horas académicas)',
                        'purpose' => 'Desarrollo narrativo, escaletas y guiones técnicos para medios audiovisuales y digitales.',
                    ],
                    [
                        'title' => 'Combate a Fake News y Desinformación',
                        'duration' => '2 Días (16 horas académicas)',
                        'purpose' => 'Metodologías de verificación de datos (Fact-Checking), detección de Deepfakes y análisis de fuentes.',
                    ],
                    [
                        'title' => 'Fotografía e Iluminación Digital',
                        'duration' => '2 Días (16 horas académicas)',
                        'purpose' => 'Composición visual, esquemas de iluminación y tratamiento digital de imagen.',
                    ],
                ]
            ],
            [
                'slug' => '5g',
                'name' => 'Redes 5G, IPv6 e IXP',
                'icon' => '📡',
                'courses' => [
                    [
                        'title' => 'Fundamentos y Arquitectura 5G',
                        'duration' => '2 Días (16 horas académicas)',
                        'purpose' => 'Infraestructura de conectividad de ultra-alta velocidad y despliegue de red de nueva generación.',
                    ],
                    [
                        'title' => 'IPv6: Arquitectura, Implementación y Operación',
                        'duration' => '2 Días (16 horas académicas)',
                        'purpose' => 'Diseño, autoconfiguración (SLAAC/DHCPv6), enrutamiento y estrategias de transición IPv4/IPv6 conforme a las normativas de telecomunicaciones.',
                    ],
                    [
                        'title' => 'IXP: Puntos de Intercambio de Tráfico, Soberanía y Peering',
                        'duration' => '2 Días (16 horas académicas)',
                        'purpose' => 'Interconexión de redes autónomas (BGP Peering), mitigación de latencias y soberanía del tráfico nacional.',
                    ],
                ]
            ],
            [
                'slug' => 'ciberseguridad',
                'name' => 'Ciberseguridad',
                'icon' => '🔒',
                'courses' => [
                    [
                        'title' => 'Ethical Hacking y Pruebas de Intrusión',
                        'duration' => '3 Días (24 horas académicas)',
                        'purpose' => 'Identificación y explotación controlada de vulnerabilidades en infraestructura y aplicaciones web.',
                    ],
                    [
                        'title' => 'Análisis Forense Digital y Cadena de Custodia',
                        'duration' => '2 Días (16 horas académicas)',
                        'purpose' => 'Recolección, preservación y análisis de evidencia digital para incidentes informáticos y peritaje legal.',
                    ],
                    [
                        'title' => 'Seguridad en la Nube y Túneles VPN',
                        'duration' => '2 Días (16 horas académicas)',
                        'purpose' => 'Configuración de perímetros seguros, cifrado de enlaces y protección de activos en nubes híbridas.',
                    ],
                    [
                        'title' => 'Arquitectura de Centros de Operaciones de Seguridad (SOC)',
                        'duration' => '2 Días (16 horas académicas)',
                        'purpose' => 'Monitoreo continuo, correlación de eventos con SIEM y respuesta ante incidentes críticos.',
                    ],
                    [
                        'title' => 'Cibercrianza y Prevención de Delitos Informáticos',
                        'duration' => '1 Día (8 horas académicas)',
                        'purpose' => 'Sensibilización social, protección infantil en entornos digitales y marco legal contra el cibercrimen.',
                    ],
                ]
            ],
            [
                'slug' => 'fibra',
                'name' => 'Fibra Óptica y Robótica',
                'icon' => '🌐',
                'courses' => [
                    [
                        'title' => 'Planificación y Diseño de Redes FTTx / Fibra 360',
                        'duration' => '3 Días (24 horas académicas)',
                        'purpose' => 'Cálculo de enlaces ópticos, splitter óptico, tendido de cable de fibra y empalmes por fusión.',
                    ],
                    [
                        'title' => 'AutoCAD Aplicado al Diseño de Redes',
                        'duration' => '2 Días (16 horas académicas)',
                        'purpose' => 'Planificación y digitalización de rutas de telecomunicaciones y canalizaciones subterráneas y aéreas.',
                    ],
                    [
                        'title' => 'Robótica Embebida con ESP32 e IoT',
                        'duration' => '2 Días (16 horas académicas)',
                        'purpose' => 'Programación de microcontroladores, sensórica avanzada y conexión a plataformas IoT en tiempo real.',
                    ],
                ]
            ],
        ];

        foreach ($catalog as $areaData) {
            $area = TrainingArea::updateOrCreate(
                ['slug' => $areaData['slug']],
                ['name' => $areaData['name'], 'icon' => $areaData['icon']]
            );

            foreach ($areaData['courses'] as $course) {
                Course::updateOrCreate(
                    [
                        'training_area_id' => $area->id,
                        'title' => $course['title'],
                    ],
                    [
                        'duration' => $course['duration'],
                        'purpose' => $course['purpose'],
                        'certification' => 'Certificado de Participación avalado por CONATEL y entes universitarios.',
                    ]
                );
            }
        }
    }
}
