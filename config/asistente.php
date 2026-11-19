<?php

/*
 * Asistente de IA.
 *
 * Todos los proveedores usan la API "chat completions" compatible con OpenAI, así que
 * cambiar de proveedor o de entorno es solo configuración (.env), sin tocar código.
 *
 * ASISTENTE_PROVEEDORES define el orden de uso separado por comas. Si el primero falla
 * (límite de uso, caída, tiempo agotado) se intenta el siguiente. Un proveedor sin clave
 * o sin modelo se omite. Ejemplos:
 *   producción:  ASISTENTE_PROVEEDORES=groq,openrouter
 *   local:       ASISTENTE_PROVEEDORES=ollama
 *   sin IA:      ASISTENTE_PROVEEDORES=   (solo respuestas por palabras clave)
 */

return [
    'proveedores' => array_values(array_filter(array_map('trim', explode(',', (string) env('ASISTENTE_PROVEEDORES', 'groq'))))),

    'conexiones' => [
        'groq' => [
            'url' => env('GROQ_API_URL') ?: 'https://api.groq.com/openai/v1/chat/completions',
            'token' => env('GROQ_API_KEY'),
            'modelo' => env('GROQ_MODEL', 'openai/gpt-oss-20b'),
            // Solo aplica a modelos de razonamiento (gpt-oss): low | medium | high.
            'razonamiento' => env('GROQ_REASONING_EFFORT', 'low'),
        ],
        'openai' => [
            'url' => env('OPENAI_API_URL') ?: 'https://api.openai.com/v1/chat/completions',
            'token' => env('OPENAI_API_KEY'),
            'modelo' => env('OPENAI_MODEL'),
            'razonamiento' => env('OPENAI_REASONING_EFFORT'),
        ],
        'gemini' => [
            'url' => env('GEMINI_API_URL') ?: 'https://generativelanguage.googleapis.com/v1beta/openai/chat/completions',
            'token' => env('GEMINI_API_KEY'),
            'modelo' => env('GEMINI_MODEL'),
        ],
        'openrouter' => [
            'url' => env('OPENROUTER_API_URL') ?: 'https://openrouter.ai/api/v1/chat/completions',
            'token' => env('OPENROUTER_API_KEY'),
            'modelo' => env('OPENROUTER_MODEL'),
        ],
        // Modelo local (Ollama/LM Studio): no necesita clave real.
        'ollama' => [
            'url' => env('OLLAMA_API_URL') ?: 'http://127.0.0.1:11434/v1/chat/completions',
            'token' => env('OLLAMA_API_KEY', 'ollama'),
            'modelo' => env('OLLAMA_MODEL'),
            'requiere_token' => false,
        ],
        // Cualquier otro servicio compatible con OpenAI (DeepSeek, Mistral, Azure, vLLM...).
        'personalizado' => [
            'url' => env('ASISTENTE_CUSTOM_URL'),
            'token' => env('ASISTENTE_CUSTOM_KEY'),
            'modelo' => env('ASISTENTE_CUSTOM_MODEL'),
        ],
    ],

    // Segundos máximos por llamada al proveedor.
    'timeout' => (int) env('ASISTENTE_TIMEOUT', 25),
    // Rondas de consulta de datos antes de forzar la respuesta final.
    'max_rondas' => (int) env('ASISTENTE_MAX_RONDAS', 4),
    // Mensajes previos de la conversación que se envían como contexto.
    'historial' => (int) env('ASISTENTE_HISTORIAL', 8),
    'temperatura' => (float) env('ASISTENTE_TEMPERATURA', 0.1),
    'max_tokens' => (int) env('ASISTENTE_MAX_TOKENS', 900),
    // Desactívalo si el hosting acumula la salida y el texto no aparece progresivamente.
    'streaming' => (bool) env('ASISTENTE_STREAMING', true),
];
