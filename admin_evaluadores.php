<?php
require 'config.php';

$mensaje = '';
$error = '';

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    
    try {
        if ($accion === 'crear') {
            $stmt = $pdo->prepare("
                INSERT INTO evaluadores (nombre)
                VALUES (?)
            ");
            $stmt->execute([
                $_POST['nombre']
            ]);
            $mensaje = "✅ Evaluador creado exitosamente.";
            
        } elseif ($accion === 'editar') {
            $stmt = $pdo->prepare("
                UPDATE evaluadores 
                SET nombre = ?
                WHERE id_evaluador = ?
            ");
            $stmt->execute([
                $_POST['nombre'],
                (int)$_POST['id_evaluador']
            ]);
            $mensaje = "✅ Evaluador actualizado exitosamente.";
            
        } elseif ($accion === 'eliminar') {
            // Verificar si tiene evaluaciones asociadas
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM evaluaciones WHERE id_evaluador = ?");
            $stmt->execute([(int)$_POST['id_evaluador']]);
            $tiene_evaluaciones = $stmt->fetchColumn();
            
            // Verificar si es creador de instancias
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM instancias_evaluacion WHERE creado_por = ?");
            $stmt->execute([(int)$_POST['id_evaluador']]);
            $tiene_instancias = $stmt->fetchColumn();
            
            if ($tiene_evaluaciones > 0 || $tiene_instancias > 0) {
                $error = "❌ No se puede eliminar el evaluador porque tiene {$tiene_evaluaciones} evaluaciones y {$tiene_instancias} instancias asociadas.";
            } else {
                $stmt = $pdo->prepare("DELETE FROM evaluadores WHERE id_evaluador = ?");
                $stmt->execute([(int)$_POST['id_evaluador']]);
                $mensaje = "✅ Evaluador eliminado exitosamente.";
            }
        }
    } catch (Exception $e) {
        $error = "❌ Error: " . $e->getMessage();
    }
}

// Cargar todos los evaluadores con estadísticas
$stmt = $pdo->query("
    SELECT 
        e.*,
        COUNT(DISTINCT ev.id_evaluacion) AS num_evaluaciones,
        COUNT(DISTINCT ie.id_instancia) AS num_instancias_creadas
    FROM evaluadores e
    LEFT JOIN evaluaciones ev ON e.id_evaluador = ev.id_evaluador
    LEFT JOIN instancias_evaluacion ie ON e.id_evaluador = ie.creado_por
    GROUP BY e.id_evaluador
    ORDER BY e.nombre
");
$evaluadores = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Si hay un ID en GET, cargar ese evaluador para editar
$evaluador_editar = null;
if (isset($_GET['editar'])) {
    $stmt = $pdo->prepare("SELECT * FROM evaluadores WHERE id_evaluador = ?");
    $stmt->execute([(int)$_GET['editar']]);
    $evaluador_editar = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Evaluadores</title>
    <link rel="stylesheet" href="estilo.css">
    <style>
        .evaluador-item {
            border: 1px solid #ddd;
            padding: 15px;
            margin: 10px 0;
            border-radius: 5px;
            background: #f9f9f9;
        }
        .btn-small {
            padding: 5px 10px;
            font-size: 0.9em;
            margin-right: 5px;
        }
        .form-evaluador {
            background: #e8f4f8;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .mensaje {
            padding: 10px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .mensaje.exito {
            background: #d4edda;
            color: #155724;
        }
        .mensaje.error {
            background: #f8d7da;
            color: #721c24;
        }
        .stats {
            display: flex;
            gap: 15px;
            margin: 10px 0;
        }
        .stat-badge {
            background: #007bff;
            color: white;
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 0.9em;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>👨‍🏫 Gestión de Evaluadores</h1>
        
        <p><a href="index.php">← Volver al inicio</a></p>

        <?php if ($mensaje): ?>
            <div class="mensaje exito"><?= htmlspecialchars($mensaje) ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="mensaje error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- Formulario de Crear/Editar -->
        <div class="form-evaluador">
            <h2><?= $evaluador_editar ? 'Editar Evaluador' : 'Crear Nuevo Evaluador' ?></h2>
            <form method="POST">
                <input type="hidden" name="accion" value="<?= $evaluador_editar ? 'editar' : 'crear' ?>">
                <?php if ($evaluador_editar): ?>
                    <input type="hidden" name="id_evaluador" value="<?= $evaluador_editar['id_evaluador'] ?>">
                <?php endif; ?>

                <label>Nombre completo del evaluador: *</label>
                <input type="text" name="nombre" required 
                       value="<?= $evaluador_editar ? htmlspecialchars($evaluador_editar['nombre']) : '' ?>"
                       placeholder="Ej: Dr. Juan Pérez">

                <button type="submit"><?= $evaluador_editar ? 'Actualizar Evaluador' : 'Crear Evaluador' ?></button>
                <?php if ($evaluador_editar): ?>
                    <a href="admin_evaluadores.php" class="btn-small" style="display: inline-block; margin-left: 10px;">Cancelar</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Lista de Evaluadores -->
        <h2>Evaluadores Registrados (<?= count($evaluadores) ?>)</h2>
        
        <?php if (empty($evaluadores)): ?>
            <p>No hay evaluadores registrados. Crea el primero usando el formulario arriba.</p>
        <?php else: ?>
            <?php foreach ($evaluadores as $ev): ?>
                <div class="evaluador-item">
                    <h3><?= htmlspecialchars($ev['nombre']) ?></h3>
                    
                    <div class="stats">
                        <span class="stat-badge">
                            📊 <?= $ev['num_evaluaciones'] ?> evaluaciones realizadas
                        </span>
                        <span class="stat-badge" style="background: #28a745;">
                            📋 <?= $ev['num_instancias_creadas'] ?> instancias creadas
                        </span>
                    </div>
                    
                    <p>
                        <strong>ID:</strong> <?= $ev['id_evaluador'] ?>
                    </p>
                    
                    <div>
                        <a href="admin_evaluadores.php?editar=<?= $ev['id_evaluador'] ?>" class="btn-small">✏️ Editar</a>
                        
                        <?php if ($ev['num_evaluaciones'] == 0 && $ev['num_instancias_creadas'] == 0): ?>
                            <form method="POST" style="display: inline;" 
                                  onsubmit="return confirm('¿Estás seguro de eliminar este evaluador?');">
                                <input type="hidden" name="accion" value="eliminar">
                                <input type="hidden" name="id_evaluador" value="<?= $ev['id_evaluador'] ?>">
                                <button type="submit" class="btn-small" style="background: #dc3545;">🗑️ Eliminar</button>
                            </form>
                        <?php else: ?>
                            <span class="btn-small" style="background: #ccc; cursor: not-allowed;" 
                                  title="No se puede eliminar porque tiene evaluaciones o instancias asociadas">🔒 Bloqueado</span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>
