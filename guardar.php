<?php
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Método no permitido');
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO evaluaciones (
            id_equipo, id_evaluador,
            problema_valor, integracion_contenidos, funcionalidad,
            demostracion, trabajo_equipo, documentacion, respuestas_jurado,
            observaciones
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $_POST['equipo_id'],
        $_POST['evaluador_id'],
        $_POST['problema_valor'],
        $_POST['integracion_contenidos'],
        $_POST['funcionalidad'],
        $_POST['demostracion'],
        $_POST['trabajo_equipo'],
        $_POST['documentacion'],
        $_POST['respuestas_jurado'],
        $_POST['observaciones'] ?? ''
    ]);

    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Éxito</title></head><body>';
    echo '<div class="container"><h2>✅ Evaluación guardada correctamente</h2>';
    echo '<p><a href="index.php">Evaluar otro equipo</a></p></div>';
    echo '<style>.container{max-width:600px;margin:50px auto;text-align:center;}</style>';
    echo '</body></html>';

} catch (Exception $e) {
    http_response_code(500);
    echo "Error al guardar: " . htmlspecialchars($e->getMessage());
}
?>