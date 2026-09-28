#!/bin/bash
echo "🚀 Iniciando entorno local para Telegram Bot..."

# Limpiar variables de proxy corporativo de la sesión
unset http_proxy https_proxy HTTP_PROXY HTTPS_PROXY ALL_PROXY all_proxy

# Iniciar localtunnel en segundo plano y guardar su ID de proceso
npx localtunnel --port 8000 --subdomain mi-bot-telegram &
TUNNEL_PID=$!

echo "🌐 Túnel iniciado en https://mi-bot-telegram.loca.lt"
echo "⚡ Levantando servidor de Laravel..."

# Iniciar el servidor de Laravel
php artisan serve --host=0.0.0.0 --port=8000

# Si presionas Ctrl+C para detener Laravel, también cierra localtunnel
kill $TUNNEL_PID

alias bot='cd ~/Proyectos/telegram-bot && ./iniciar.sh'