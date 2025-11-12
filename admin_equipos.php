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
                INSERT INTO equipos (nombre, integrantes)
                VALUES (?, ?)
            ");
            $stmt->execute([
                $_POST['nombre'],
                $_POST['integrantes']
            ]);
            $mensaje = "✅ Equipo creado exitosamente.";
            
        } elseif ($accion === 'editar') {
            $stmt = $pdo->prepare("
                UPDATE equipos 
                SET nombre = ?, integrantes = ?
                WHERE id_equipo = ?
            ");
            $stmt->execute([
                $_POST['nombre'],
                $_POST['integrantes'],
                (int)$_POST['id_equipo']
            ]);
            $mensaje = "✅ Equipo actualizado exitosamente.";
            
        } elseif ($accion === 'eliminar') {
            // Verificar si tiene evaluaciones asociadas
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM evaluaciones WHERE id_equipo = ?");
            $stmt->execute([(int)$_POST['id_equipo']]);
            $tiene_evaluaciones = $stmt->fetchColumn();
            
            if ($tiene_evaluaciones > 0) {
                $error = "❌ No se puede eliminar el equipo porque tiene {$tiene_evaluaciones} evaluaciones asociadas.";
            } else {
                $stmt = $pdo->prepare("DELETE FROM equipos WHERE id_equipo = ?");
                $stmt->execute([(int)$_POST['id_equipo']]);
                $mensaje = "✅ Equipo eliminado exitosamente.";
            }
        }
    } catch (Exception $e) {
        $error = "❌ Error: " . $e->getMessage();
    }
}

// Cargar todos los equipos con conteo de evaluaciones
$stmt = $pdo->query("
    SELECT 
        e.*,
        COUNT(ev.id_evaluacion) AS num_evaluaciones
    FROM equipos e
    LEFT JOIN evaluaciones ev ON e.id_equipo = ev.id_equipo
    GROUP BY e.id_equipo
    ORDER BY e.nombre
");
$equipos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Si hay un ID en GET, cargar ese equipo para editar
$equipo_editar = null;
if (isset($_GET['editar'])) {
    $stmt = $pdo->prepare("SELECT * FROM equipos WHERE id_equipo = ?");
    $stmt->execute([(int)$_GET['editar']]);
    $equipo_editar = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Equipos</title>
    <link rel="stylesheet" href="estilo.css">
    <style>
        .equipo-item {
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
        .form-equipo {
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
        .integrantes-list {
            font-size: 0.9em;
            color: #666;
            margin: 10px 0;
            line-height: 1.6;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>👥 Gestión de Equipos</h1>
        
        <p><a href="index.php">← Volver al inicio</a></p>

        <?php if ($mensaje): ?>
            <div class="mensaje exito"><?= htmlspecialchars($mensaje) ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="mensaje error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- Formulario de Crear/Editar -->
        <div class="form-equipo">
            <h2><?= $equipo_editar ? 'Editar Equipo' : 'Crear Nuevo Equipo' ?></h2>
            <form method="POST">
                <input type="hidden" name="accion" value="<?= $equipo_editar ? 'editar' : 'crear' ?>">
                <?php if ($equipo_editar): ?>
                    <input type="hidden" name="id_equipo" value="<?= $equipo_editar['id_equipo'] ?>">
                <?php endif; ?>

                <label>Nombre del equipo: *</label>
                <input type="text" name="nombre" required 
                       value="<?= $equipo_editar ? htmlspecialchars($equipo_editar['nombre']) : '' ?>"
                       placeholder="Ej: Equipo Alpha">

                <label>Integrantes:</label>
                <textarea name="integrantes" rows="5" 
                          placeholder="Lista de integrantes (uno por línea o separados por comas)"><?= $equipo_editar ? htmlspecialchars($equipo_editar['integrantes']) : '' ?></textarea>
                <small style="color: #666;">Puedes listar los nombres de los integrantes, sus roles, etc.</small>

                <button type="submit"><?= $equipo_editar ? 'Actualizar Equipo' : 'Crear Equipo' ?></button>
                <?php if ($equipo_editar): ?>
                    <a href="admin_equipos.php" class="btn-small" style="display: inline-block; margin-left: 10px;">Cancelar</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Lista de Equipos -->
        <h2>Equipos Registrados (<?= count($equipos) ?>)</h2>
        
        <?php if (empty($equipos)): ?>
            <p>No hay equipos registrados. Crea el primero usando el formulario arriba.</p>
        <?php else: ?>
            <?php foreach ($equipos as $eq): ?>
                <div class="equipo-item">
                    <h3><?= htmlspecialchars($eq['nombre']) ?></h3>
                    
                    <?php if ($eq['integrantes']): ?>
                        <div class="integrantes-list">
                            <strong>Integrantes:</strong><br>
                            <?= nl2br(htmlspecialchars($eq['integrantes'])) ?>
                        </div>
                    <?php endif; ?>
                    
                    <p>
                        <strong>ID:</strong> <?= $eq['id_equipo'] ?> |
                        <strong>Evaluaciones:</strong> <?= $eq['num_evaluaciones'] ?>
                    </p>
                    
                    <div>
                        <a href="admin_equipos.php?editar=<?= $eq['id_equipo'] ?>" class="btn-small">✏️ Editar</a>
                        
                        <?php if ($eq['num_evaluaciones'] == 0): ?>
                            <form method="POST" style="display: inline;" 
                                  onsubmit="return confirm('¿Estás seguro de eliminar este equipo?');">
                                <input type="hidden" name="accion" value="eliminar">
                                <input type="hidden" name="id_equipo" value="<?= $eq['id_equipo'] ?>">
                                <button type="submit" class="btn-small" style="background: #dc3545;">🗑️ Eliminar</button>
                            </form>
                        <?php else: ?>
                            <span class="btn-small" style="background: #ccc; cursor: not-allowed;" 
                                  title="No se puede eliminar porque tiene evaluaciones">🔒 Bloqueado</span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>
