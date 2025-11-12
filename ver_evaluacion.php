<?php
require 'config.php';

if (!isset($_GET['id'])) {
    header('Location: index.php');
    exit;
}

$id_evaluacion = (int)$_GET['id'];

// Obtener información completa de la evaluación
$stmt = $pdo->prepare("
    SELECT 
        e.*,
        ie.nombre AS instancia_nombre,
        eq.nombre AS equipo_nombre,
        ev.nombre AS evaluador_nombre,
        e.fecha_eval
    FROM evaluaciones e
    INNER JOIN instancias_evaluacion ie ON e.id_instancia = ie.id_instancia
    INNER JOIN equipos eq ON e.id_equipo = eq.id_equipo
    INNER JOIN evaluadores ev ON e.id_evaluador = ev.id_evaluador
    WHERE e.id_evaluacion = ?
");
$stmt->execute([$id_evaluacion]);
$evaluacion = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$evaluacion) {
    die("Evaluación no encontrada.");
}

// Obtener detalles de los criterios evaluados
$stmt = $pdo->prepare("
    SELECT 
        de.*,
        ce.nombre AS criterio_nombre,
        ce.descripcion AS criterio_descripcion,
        ce.puntaje_minimo,
        ce.puntaje_maximo
    FROM detalles_evaluacion de
    INNER JOIN criterios_evaluacion ce ON de.id_criterio = ce.id_criterio
    WHERE de.id_evaluacion = ?
    ORDER BY de.fecha_registro
");
$stmt->execute([$id_evaluacion]);
$detalles = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calcular promedio
$total_puntaje = 0;
$total_criterios = count($detalles);
foreach ($detalles as $det) {
    $total_puntaje += $det['puntaje'];
}
$promedio = $total_criterios > 0 ? round($total_puntaje / $total_criterios, 2) : 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ver Evaluación #<?= $id_evaluacion ?></title>
    <link rel="stylesheet" href="estilo.css">
    <style>
        .info-box {
            background: #e8f4f8;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .detalle-item {
            border: 1px solid #ddd;
            padding: 15px;
            margin: 10px 0;
            border-radius: 5px;
            background: #f9f9f9;
        }
        .puntaje-badge {
            display: inline-block;
            padding: 8px 15px;
            border-radius: 5px;
            font-weight: bold;
            color: white;
            font-size: 1.1em;
        }
        .puntaje-1 { background: #dc3545; }
        .puntaje-2 { background: #ffc107; color: #000; }
        .puntaje-3 { background: #28a745; }
        .puntaje-4 { background: #007bff; }
        .puntaje-5 { background: #6f42c1; }
        .resumen-box {
            background: #d4edda;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            margin: 20px 0;
        }
        .observaciones-box {
            background: #fff3cd;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .estado-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 3px;
            font-weight: bold;
            font-size: 0.9em;
        }
        .estado-enviada { background: #28a745; color: white; }
        .estado-borrador { background: #6c757d; color: white; }
        .estado-revisada { background: #007bff; color: white; }
    </style>
</head>
<body>
    <div class="container">
        <h1>📊 Evaluación #<?= $id_evaluacion ?></h1>
        
        <p>
            <a href="index.php">← Volver al inicio</a> | 
            <a href="reportes.php">Ver reportes</a>
        </p>

        <div class="info-box">
            <h2><?= htmlspecialchars($evaluacion['instancia_nombre']) ?></h2>
            <p><strong>Equipo:</strong> <?= htmlspecialchars($evaluacion['equipo_nombre']) ?></p>
            <p><strong>Evaluador:</strong> <?= htmlspecialchars($evaluacion['evaluador_nombre']) ?></p>
            <p><strong>Fecha:</strong> <?= date('d/m/Y H:i', strtotime($evaluacion['fecha_eval'])) ?></p>
            <p>
                <strong>Estado:</strong> 
                <span class="estado-badge estado-<?= strtolower($evaluacion['estado']) ?>">
                    <?= strtoupper($evaluacion['estado']) ?>
                </span>
            </p>
        </div>

        <div class="resumen-box">
            <h2>Promedio: <?= $promedio ?> / 4.0</h2>
            <p>Total de criterios evaluados: <?= $total_criterios ?></p>
        </div>

        <h2>Detalles por Criterio</h2>
        
        <?php if (empty($detalles)): ?>
            <p>No hay detalles de criterios para esta evaluación.</p>
        <?php else: ?>
            <?php foreach ($detalles as $index => $det): ?>
                <div class="detalle-item">
                    <h3><?= ($index + 1) ?>. <?= htmlspecialchars($det['criterio_nombre']) ?></h3>
                    
                    <?php if ($det['criterio_descripcion']): ?>
                        <p style="color: #666;"><?= htmlspecialchars($det['criterio_descripcion']) ?></p>
                    <?php endif; ?>
                    
                    <p>
                        <strong>Puntaje asignado:</strong> 
                        <?php
                            // Determinar clase según el puntaje normalizado
                            $porcentaje = (($det['puntaje'] - $det['puntaje_minimo']) / ($det['puntaje_maximo'] - $det['puntaje_minimo'])) * 100;
                            $clase = 'puntaje-1';
                            if ($porcentaje >= 90) $clase = 'puntaje-5';
                            elseif ($porcentaje >= 75) $clase = 'puntaje-4';
                            elseif ($porcentaje >= 60) $clase = 'puntaje-3';
                            elseif ($porcentaje >= 40) $clase = 'puntaje-2';
                        ?>
                        <span class="puntaje-badge <?= $clase ?>">
                            <?= $det['puntaje'] ?> / <?= $det['puntaje_maximo'] ?>
                        </span>
                    </p>
                    
                    <?php if (!empty($det['comentario'])): ?>
                        <p><strong>Comentario:</strong> <?= nl2br(htmlspecialchars($det['comentario'])) ?></p>
                    <?php endif; ?>
                    
                    <p style="font-size: 0.85em; color: #999;">
                        Registrado: <?= date('d/m/Y H:i:s', strtotime($det['fecha_registro'])) ?>
                    </p>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php if (!empty($evaluacion['observaciones'])): ?>
            <div class="observaciones-box">
                <h3>📝 Observaciones Generales</h3>
                <p><?= nl2br(htmlspecialchars($evaluacion['observaciones'])) ?></p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
