<?php
require 'config.php';

if (!isset($_GET['id'])) {
    header('Location: admin_instancias.php');
    exit;
}

$id_instancia = (int)$_GET['id'];

// Obtener información de la instancia
$stmt = $pdo->prepare("
    SELECT 
        ie.*,
        ev.nombre AS creador_nombre
    FROM instancias_evaluacion ie
    LEFT JOIN evaluadores ev ON ie.creado_por = ev.id_evaluador
    WHERE ie.id_instancia = ?
");
$stmt->execute([$id_instancia]);
$instancia = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$instancia) {
    die("Instancia no encontrada.");
}

// Obtener criterios asignados a esta instancia
$stmt = $pdo->prepare("
    SELECT 
        ic.*,
        ce.nombre AS criterio_nombre,
        ce.descripcion AS criterio_descripcion,
        ce.puntaje_minimo,
        ce.puntaje_maximo,
        ce.activo
    FROM instancia_criterios ic
    INNER JOIN criterios_evaluacion ce ON ic.id_criterio = ce.id_criterio
    WHERE ic.id_instancia = ?
    ORDER BY ic.orden, ce.nombre
");
$stmt->execute([$id_instancia]);
$criterios = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener evaluaciones realizadas en esta instancia
$stmt = $pdo->prepare("
    SELECT 
        e.id_evaluacion,
        eq.nombre AS equipo,
        ev.nombre AS evaluador,
        e.fecha_eval,
        e.estado,
        COUNT(de.id_detalle) AS criterios_evaluados,
        AVG(de.puntaje) AS promedio
    FROM evaluaciones e
    INNER JOIN equipos eq ON e.id_equipo = eq.id_equipo
    INNER JOIN evaluadores ev ON e.id_evaluador = ev.id_evaluador
    LEFT JOIN detalles_evaluacion de ON e.id_evaluacion = de.id_evaluacion
    WHERE e.id_instancia = ?
    GROUP BY e.id_evaluacion
    ORDER BY e.fecha_eval DESC
");
$stmt->execute([$id_instancia]);
$evaluaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Detalles de Instancia</title>
    <link rel="stylesheet" href="estilo.css">
    <style>
        .info-box {
            background: #e8f4f8;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .criterio-item {
            border: 1px solid #ddd;
            padding: 15px;
            margin: 10px 0;
            border-radius: 5px;
            background: #f9f9f9;
        }
        .criterio-item.inactivo {
            opacity: 0.6;
            background: #ffe6e6;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            background: white;
        }
        table th, table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        table th {
            background: #007bff;
            color: white;
        }
        table tr:hover {
            background: #f5f5f5;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📋 Detalles de Instancia</h1>
        
        <p>
            <a href="admin_instancias.php">← Volver a instancias</a> | 
            <a href="index.php">Inicio</a>
        </p>

        <div class="info-box">
            <h2><?= htmlspecialchars($instancia['nombre']) ?></h2>
            
            <?php if ($instancia['descripcion']): ?>
                <p><?= htmlspecialchars($instancia['descripcion']) ?></p>
            <?php endif; ?>
            
            <p>
                <strong>Estado:</strong> <?= $instancia['activa'] ? '✅ Activa' : '⛔ Inactiva' ?> |
                <strong>Fecha de inicio:</strong> <?= date('d/m/Y H:i', strtotime($instancia['fecha_inicio'])) ?>
                <?php if ($instancia['fecha_fin']): ?>
                    | <strong>Fecha fin:</strong> <?= date('d/m/Y H:i', strtotime($instancia['fecha_fin'])) ?>
                <?php endif; ?>
            </p>
            
            <?php if ($instancia['creador_nombre']): ?>
                <p><strong>Creado por:</strong> <?= htmlspecialchars($instancia['creador_nombre']) ?></p>
            <?php endif; ?>
        </div>

        <h2>Criterios Asignados (<?= count($criterios) ?>)</h2>
        
        <?php if (empty($criterios)): ?>
            <p>No hay criterios asignados a esta instancia.</p>
        <?php else: ?>
            <?php foreach ($criterios as $index => $crit): ?>
                <div class="criterio-item <?= $crit['activo'] ? '' : 'inactivo' ?>">
                    <h3><?= ($index + 1) ?>. <?= htmlspecialchars($crit['criterio_nombre']) ?></h3>
                    
                    <?php if ($crit['criterio_descripcion']): ?>
                        <p><?= htmlspecialchars($crit['criterio_descripcion']) ?></p>
                    <?php endif; ?>
                    
                    <p>
                        <strong>Rango:</strong> <?= $crit['puntaje_minimo'] ?> - <?= $crit['puntaje_maximo'] ?> pts |
                        <strong>Peso:</strong> <?= $crit['peso_porcentual'] ?>% |
                        <strong>Orden:</strong> <?= $crit['orden'] ?> |
                        <strong>Estado del criterio:</strong> <?= $crit['activo'] ? '✅ Activo' : '⛔ Inactivo' ?>
                    </p>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <h2>Evaluaciones Realizadas (<?= count($evaluaciones) ?>)</h2>
        
        <?php if (empty($evaluaciones)): ?>
            <p>No hay evaluaciones realizadas en esta instancia aún.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Equipo</th>
                        <th>Evaluador</th>
                        <th>Fecha</th>
                        <th>Criterios</th>
                        <th>Promedio</th>
                        <th>Estado</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($evaluaciones as $eval): ?>
                        <tr>
                            <td><?= $eval['id_evaluacion'] ?></td>
                            <td><?= htmlspecialchars($eval['equipo']) ?></td>
                            <td><?= htmlspecialchars($eval['evaluador']) ?></td>
                            <td><?= date('d/m/Y H:i', strtotime($eval['fecha_eval'])) ?></td>
                            <td><?= $eval['criterios_evaluados'] ?></td>
                            <td><?= $eval['promedio'] ? round($eval['promedio'], 2) : 'N/A' ?></td>
                            <td><?= strtoupper($eval['estado']) ?></td>
                            <td>
                                <a href="ver_evaluacion.php?id=<?= $eval['id_evaluacion'] ?>">Ver</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</body>
</html>
