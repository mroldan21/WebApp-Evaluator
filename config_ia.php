<?php
// Configuración de la API de IA
define('IA_API_URL', 'https://openrouter.ai/api/v1/chat/completions'); // Cambiar según tu API
define('IA_API_KEY', ''); // Dejar vacío y configurar desde la interfaz
define('IA_MODEL', 'deepseek/deepseek-chat'); // Modelo por defecto

// Obtener configuración desde BD o archivo
function getIaConfig() {
    // Por ahora devolvemos valores por defecto
    // Luego puedes guardar esto en una tabla de configuración
    return [
        'api_url' => IA_API_URL,
        'api_key' => getenv('IA_API_KEY') ?: IA_API_KEY, // Primero intenta variable de entorno
        'model' => IA_MODEL,
        'max_tokens' => 2000,
        'temperature' => 0.7
    ];
}
?>
