<?php
require 'config.php';
require 'config_ia.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$id_evaluacion = (int)($input['id_evaluacion'] ?? 0);
$prompt = $input['prompt'] ?? '';
$usuario_id = (int)($input['usuario_id'] ?? 1); // Por ahora hardcodeado

if (!$id_evaluacion || !$prompt) {
    http_response_code(400);
    echo json_encode(['error' => 'Faltan parámetros requeridos']);
    exit;
}

try {
    // Registrar solicitud en BD
    $stmt = $pdo->prepare("
        INSERT INTO devoluciones_ia (id_evaluacion, prompt_enviado, estado, creado_por)
        VALUES (?, ?, 'procesando', ?)
    ");
    $stmt->execute([$id_evaluacion, $prompt, $usuario_id]);
    $id_devolucion = $pdo->lastInsertId();

    // Obtener configuración de IA
    $config = getIaConfig();
    
    if (empty($config['api_key'])) {
        throw new Exception('API Key no configurada. Configure la clave en config_ia.php o variable de entorno IA_API_KEY');
    }

    // Preparar payload para la API
    $payload = [
        'model' => $config['model'],
        'messages' => [
            [
                'role' => 'user',
                'content' => $prompt
            ]
        ],
        'max_tokens' => $config['max_tokens'],
        'temperature' => $config['temperature']
    ];

    // Realizar llamada a la API de IA
    $inicio = microtime(true);
    
    $ch = curl_init($config['api_url']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $config['api_key']
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60); // Timeout de 60 segundos

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $tiempo_respuesta = round(microtime(true) - $inicio, 2);
    
    if (curl_errno($ch)) {
        throw new Exception('Error de conexión: ' . curl_error($ch));
    }
    
    curl_close($ch);

    if ($http_code !== 200) {
        throw new Exception('API respondió con código ' . $http_code . ': ' . $response);
    }

    $result = json_decode($response, true);
    
    if (!isset($result['choices'][0]['message']['content'])) {
        throw new Exception('Respuesta de API inválida: ' . $response);
    }

    $respuesta_ia = $result['choices'][0]['message']['content'];
    $tokens_usados = $result['usage']['total_tokens'] ?? 0;

    // Actualizar BD con resultado exitoso
    $stmt = $pdo->prepare("
        UPDATE devoluciones_ia 
        SET respuesta_ia = ?, 
            modelo_ia = ?, 
            tokens_usados = ?, 
            tiempo_respuesta = ?, 
            estado = 'completado',
            fecha_completado = NOW()
        WHERE id_devolucion = ?
    ");
    $stmt->execute([$respuesta_ia, $config['model'], $tokens_usados, $tiempo_respuesta, $id_devolucion]);

    // Responder con éxito
    echo json_encode([
        'success' => true,
        'id_devolucion' => $id_devolucion,
        'respuesta' => $respuesta_ia,
        'tokens_usados' => $tokens_usados,
        'tiempo_respuesta' => $tiempo_respuesta,
        'modelo' => $config['model']
    ]);

} catch (Exception $e) {
    // Actualizar BD con error
    if (isset($id_devolucion)) {
        $stmt = $pdo->prepare("
            UPDATE devoluciones_ia 
            SET estado = 'error', error_mensaje = ?
            WHERE id_devolucion = ?
        ");
        $stmt->execute([$e->getMessage(), $id_devolucion]);
    }

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
