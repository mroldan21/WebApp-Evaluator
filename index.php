<?php
require 'config.php';

// Cargar equipos
$stmt = $pdo->query("SELECT id_equipo, nombre FROM equipos ORDER BY nombre");
$equipos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Cargar evaluadores
$stmt = $pdo->query("SELECT id_evaluador, nombre FROM evaluadores ORDER BY nombre");
$evaluadores = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Cargar instancias activas
$stmt = $pdo->query("SELECT id_instancia, nombre, descripcion FROM instancias_evaluacion WHERE activa = TRUE ORDER BY fecha_inicio DESC");
$instancias = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Evaluación de Proyectos</title>
    <link rel="stylesheet" href="estilo.css">
</head>
<body>
    <div class="container">
        <h1>Sistema de Evaluación Flexible</h1>
        
        <div class="menu-admin">
            <a href="admin_criterios.php" class="btn-admin">📝 Gestionar Criterios</a>
            <a href="admin_instancias.php" class="btn-admin">📋 Gestionar Instancias</a>
            <a href="reportes.php" class="btn-admin">📊 Ver Reportes</a>
        </div>

        <h2>Iniciar Nueva Evaluación</h2>
        
        <form action="evaluar.php" method="get">
            <label for="instancia">Instancia de Evaluación:</label>
            <select name="instancia_id" required>
                <option value="">-- Seleccione instancia --</option>
                <?php foreach ($instancias as $inst): ?>
                    <option value="<?= $inst['id_instancia'] ?>">
                        <?= htmlspecialchars($inst['nombre']) ?>
                        <?php if ($inst['descripcion']): ?>
                            - <?= htmlspecialchars(substr($inst['descripcion'], 0, 50)) ?>
                        <?php endif; ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="equipo">Equipo:</label>
            <select name="equipo_id" required>
                <option value="">-- Seleccione equipo --</option>
                <?php foreach ($equipos as $eq): ?>
                    <option value="<?= $eq['id_equipo'] ?>">
                        <?= htmlspecialchars($eq['nombre']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="evaluador">Evaluador:</label>
            <select name="evaluador_id" required>
                <option value="">-- Seleccione evaluador --</option>
                <?php foreach ($evaluadores as $ev): ?>
                    <option value="<?= $ev['id_evaluador'] ?>">
                        <?= htmlspecialchars($ev['nombre']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <button type="submit">Iniciar evaluación</button>
        </form>
    </div>
</body>
</html>
