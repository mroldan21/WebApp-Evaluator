<?php
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Método no permitido');
}

try {
    // Validar datos principales
    $equipo_id = (int)$_POST['equipo_id'];
    $evaluador_id = (int)$_POST['evaluador_id'];
    $instancia_id = (int)$_POST['instancia_id'];
    $observaciones = $_POST['observaciones'] ?? '';
    $criterios = $_POST['criterio'] ?? [];

    if (empty($criterios)) {
        throw new Exception("No se recibieron criterios evaluados.");
    }

    // Iniciar transacción
    $pdo->beginTransaction();

    // 1. Insertar evaluación principal
    $stmt_eval = $pdo->prepare("
        INSERT INTO evaluaciones (id_instancia, id_equipo, id_evaluador, observaciones, estado)
        VALUES (?, ?, ?, ?, 'enviada')
    ");
    $stmt_eval->execute([$instancia_id, $equipo_id, $evaluador_id, $observaciones]);
    
    $id_evaluacion = $pdo->lastInsertId();

    // 2. Insertar detalles por cada criterio
    $stmt_detalle = $pdo->prepare("
        INSERT INTO detalles_evaluacion (id_evaluacion, id_criterio, puntaje, comentario)
        VALUES (?, ?, ?, ?)
    ");

    foreach ($criterios as $id_criterio => $datos) {
        $puntaje = (int)$datos['puntaje'];
        $comentario = $datos['comentario'] ?? '';
        
        if ($puntaje > 0) { // Solo guardar si fue evaluado
            $stmt_detalle->execute([
                $id_evaluacion,
                $id_criterio,
                $puntaje,
                $comentario
            ]);
        }
    }

    // Confirmar transacción
    $pdo->commit();

    // Respuesta exitosa
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Éxito</title>';
    echo '<link rel="stylesheet" href="estilo.css"></head><body>';
    echo '<div class="container"><h2>✅ Evaluación guardada correctamente</h2>';
    echo '<p><strong>ID de evaluación:</strong> ' . $id_evaluacion . '</p>';
    echo '<p><a href="index.php" class="btn-link">Evaluar otro equipo</a></p>';
    echo '<p><a href="ver_evaluacion.php?id=' . $id_evaluacion . '" class="btn-link">Ver esta evaluación</a></p>';
    echo '</div></body></html>';

} catch (Exception $e) {
    // Revertir en caso de error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    http_response_code(500);
    echo "Error al guardar: " . htmlspecialchars($e->getMessage());
}
?>
