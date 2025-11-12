<?php
require 'config.php';

// Obtener todas las evaluaciones con información resumida
$stmt = $pdo->query("
    SELECT 
        e.id_evaluacion,
        ie.nombre AS instancia,
        eq.nombre AS equipo,
        ev.nombre AS evaluador,
        e.fecha_eval,
        e.estado,
        COUNT(de.id_detalle) AS criterios_evaluados,
        AVG(de.puntaje) AS promedio
    FROM evaluaciones e
    INNER JOIN instancias_evaluacion ie ON e.id_instancia = ie.id_instancia
    INNER JOIN equipos eq ON e.id_equipo = eq.id_equipo
    INNER JOIN evaluadores ev ON e.id_evaluador = ev.id_evaluador
    LEFT JOIN detalles_evaluacion de ON e.id_evaluacion = de.id_evaluacion
    GROUP BY e.id_evaluacion
    ORDER BY e.fecha_eval DESC
");
$evaluaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener estadísticas por instancia
$stmt = $pdo->query("
    SELECT 
        ie.id_instancia,
        ie.nombre AS instancia,
        COUNT(DISTINCT e.id_evaluacion) AS total_evaluaciones,
        COUNT(DISTINCT e.id_equipo) AS equipos_evaluados,
        COUNT(DISTINCT e.id_evaluador) AS evaluadores_participantes,
        AVG(de.puntaje) AS promedio_general
    FROM instancias_evaluacion ie
    LEFT JOIN evaluaciones e ON ie.id_instancia = e.id_instancia
    LEFT JOIN detalles_evaluacion de ON e.id_evaluacion = de.id_evaluacion
    WHERE ie.activa = TRUE
    GROUP BY ie.id_instancia
    ORDER BY ie.fecha_inicio DESC
");
$estadisticas = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reportes de Evaluaciones</title>
    <link rel="stylesheet" href="estilo.css">
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        .stat-card {
            background: #e8f4f8;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
        }
        .stat-card h3 {
            margin: 0 0 10px 0;
            color: #333;
        }
        .stat-card .numero {
            font-size: 2em;
            font-weight: bold;
            color: #007bff;
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
        .promedio-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 3px;
            font-weight: bold;
            color: white;
        }
        .promedio-bajo { background: #dc3545; }
        .promedio-medio { background: #ffc107; color: #000; }
        .promedio-alto { background: #28a745; }
        .promedio-excelente { background: #007bff; }
    </style>
</head>
<body>
    <div class="container">
        <h1>📊 Reportes y Estadísticas</h1>
        
        <p><a href="index.php">← Volver al inicio</a></p>

        <h2>Resumen por Instancia</h2>
        
        <?php if (empty($estadisticas)): ?>
            <p>No hay estadísticas disponibles.</p>
        <?php else: ?>
            <div class="stats-grid">
                <?php foreach ($estadisticas as $stat): ?>
                    <div class="stat-card">
                        <h3><?= htmlspecialchars($stat['instancia']) ?></h3>
                        <div class="numero"><?= $stat['total_evaluaciones'] ?></div>
                        <p>Evaluaciones</p>
                        <p style="font-size: 0.9em; margin: 5px 0;">
                            <?= $stat['equipos_evaluados'] ?> equipos | 
                            <?= $stat['evaluadores_participantes'] ?> evaluadores
                        </p>
                        <?php if ($stat['promedio_general']): ?>
                            <p><strong>Promedio:</strong> <?= round($stat['promedio_general'], 2) ?> / 4.0</p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <h2>Todas las Evaluaciones (<?= count($evaluaciones) ?>)</h2>
        
        <?php if (empty($evaluaciones)): ?>
            <p>No hay evaluaciones registradas.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Instancia</th>
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
                        <?php
                            $promedio = $eval['promedio'] ? round($eval['promedio'], 2) : 0;
                            $clase_promedio = 'promedio-bajo';
                            if ($promedio >= 3.5) $clase_promedio = 'promedio-excelente';
                            elseif ($promedio >= 3) $clase_promedio = 'promedio-alto';
                            elseif ($promedio >= 2) $clase_promedio = 'promedio-medio';
                        ?>
                        <tr>
                            <td><?= $eval['id_evaluacion'] ?></td>
                            <td><?= htmlspecialchars($eval['instancia']) ?></td>
                            <td><?= htmlspecialchars($eval['equipo']) ?></td>
                            <td><?= htmlspecialchars($eval['evaluador']) ?></td>
                            <td><?= date('d/m/Y H:i', strtotime($eval['fecha_eval'])) ?></td>
                            <td><?= $eval['criterios_evaluados'] ?></td>
                            <td>
                                <span class="promedio-badge <?= $clase_promedio ?>">
                                    <?= $promedio ?>
                                </span>
                            </td>
                            <td><?= strtoupper($eval['estado']) ?></td>
                            <td>
                                <a href="ver_evaluacion.php?id=<?= $eval['id_evaluacion'] ?>">Ver detalles</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</body>
</html>
