<?php
require 'config.php';

$mensaje = '';
$error = '';

// Procesar acciones (crear, editar, desactivar)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    
    try {
        if ($accion === 'crear') {
            $stmt = $pdo->prepare("
                INSERT INTO criterios_evaluacion (nombre, descripcion, puntaje_minimo, puntaje_maximo)
                VALUES (?, ?, 0, ?)
            ");
            $stmt->execute([
                $_POST['nombre'],
                $_POST['descripcion'],
                (int)$_POST['puntaje_maximo']
            ]);
            $mensaje = "✅ Criterio creado exitosamente.";
            
        } elseif ($accion === 'editar') {
            $stmt = $pdo->prepare("
                UPDATE criterios_evaluacion 
                SET nombre = ?, descripcion = ?, puntaje_maximo = ?
                WHERE id_criterio = ?
            ");
            $stmt->execute([
                $_POST['nombre'],
                $_POST['descripcion'],
                (int)$_POST['puntaje_maximo'],
                (int)$_POST['id_criterio']
            ]);
            $mensaje = "✅ Criterio actualizado exitosamente.";
            
        } elseif ($accion === 'toggle_activo') {
            $stmt = $pdo->prepare("
                UPDATE criterios_evaluacion 
                SET activo = NOT activo 
                WHERE id_criterio = ?
            ");
            $stmt->execute([(int)$_POST['id_criterio']]);
            $mensaje = "✅ Estado del criterio actualizado.";
        }
    } catch (Exception $e) {
        $error = "❌ Error: " . $e->getMessage();
    }
}

// Cargar todos los criterios
$stmt = $pdo->query("
    SELECT * FROM criterios_evaluacion 
    ORDER BY activo DESC, nombre ASC
");
$criterios = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Si hay un ID en GET, cargar ese criterio para editar
$criterio_editar = null;
if (isset($_GET['editar'])) {
    $stmt = $pdo->prepare("SELECT * FROM criterios_evaluacion WHERE id_criterio = ?");
    $stmt->execute([(int)$_GET['editar']]);
    $criterio_editar = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Criterios</title>
    <link rel="stylesheet" href="estilo.css">
    <style>
        .criterio-item {
            border: 1px solid #ddd;
            padding: 15px;
            margin: 10px 0;
            border-radius: 5px;
            background: #f9f9f9;
        }
        .criterio-item.inactivo {
            opacity: 0.6;
            background: #f0f0f0;
        }
        .btn-small {
            padding: 5px 10px;
            font-size: 0.9em;
            margin-right: 5px;
        }
        .form-criterio {
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
    </style>
</head>
<body>
    <div class="container">
        <h1>📝 Gestión de Criterios de Evaluación</h1>
        
        <p><a href="index.php">← Volver al inicio</a></p>

        <?php if ($mensaje): ?>
            <div class="mensaje exito"><?= htmlspecialchars($mensaje) ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="mensaje error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- Formulario de Crear/Editar -->
        <div class="form-criterio">
            <h2><?= $criterio_editar ? 'Editar Criterio' : 'Crear Nuevo Criterio' ?></h2>
            <form method="POST">
                <input type="hidden" name="accion" value="<?= $criterio_editar ? 'editar' : 'crear' ?>">
                <?php if ($criterio_editar): ?>
                    <input type="hidden" name="id_criterio" value="<?= $criterio_editar['id_criterio'] ?>">
                <?php endif; ?>

                <label>Nombre del criterio: *</label>
                <input type="text" name="nombre" required 
                       value="<?= $criterio_editar ? htmlspecialchars($criterio_editar['nombre']) : '' ?>"
                       placeholder="Ej: Calidad de la presentación">

                <label>Descripción:</label>
                <textarea name="descripcion" rows="3" 
                          placeholder="Descripción opcional del criterio"><?= $criterio_editar ? htmlspecialchars($criterio_editar['descripcion']) : '' ?></textarea>

                <label>Puntaje máximo del criterio: *</label>
                <input type="number" name="puntaje_maximo" required min="1" max="100"
                       value="<?= $criterio_editar ? $criterio_editar['puntaje_maximo'] : '5' ?>"
                       placeholder="Ej: 5, 10, 20, 100">
                <small style="color: #666; display: block; margin-top: 5px;">
                    Define la escala máxima de este criterio. Ejemplos: 5 (estrellas), 10 (nota sobre 10), 100 (porcentaje).
                </small>

                <p style="color: #666; font-size: 0.9em; margin-top: 15px; background: #fff3cd; padding: 10px; border-radius: 5px;">
                    ℹ️ <strong>Sistema de evaluación:</strong> Los criterios se evalúan con 5 estrellas que se mapean automáticamente 
                    al rango 0 - [puntaje máximo]. El evaluador puede dejar en 0 (sin estrellas) cualquier criterio.
                </p>

                <button type="submit"><?= $criterio_editar ? 'Actualizar Criterio' : 'Crear Criterio' ?></button>
                <?php if ($criterio_editar): ?>
                    <a href="admin_criterios.php" class="btn-small" style="display: inline-block; margin-left: 10px;">Cancelar</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Lista de Criterios -->
        <h2>Criterios Existentes (<?= count($criterios) ?>)</h2>
        
        <?php if (empty($criterios)): ?>
            <p>No hay criterios registrados. Crea el primero usando el formulario arriba.</p>
        <?php else: ?>
            <?php foreach ($criterios as $crit): ?>
                <div class="criterio-item <?= $crit['activo'] ? '' : 'inactivo' ?>">
                    <h3><?= htmlspecialchars($crit['nombre']) ?></h3>
                    
                    <?php if ($crit['descripcion']): ?>
                        <p><?= htmlspecialchars($crit['descripcion']) ?></p>
                    <?php endif; ?>
                    
                    <p>
                        <strong>Escala:</strong> 0 - <?= $crit['puntaje_maximo'] ?> puntos |
                        <strong>Estado:</strong> <?= $crit['activo'] ? '✅ Activo' : '⛔ Inactivo' ?> |
                        <strong>Creado:</strong> <?= date('d/m/Y', strtotime($crit['fecha_creacion'])) ?>
                    </p>
                    
                    <div>
                        <a href="admin_criterios.php?editar=<?= $crit['id_criterio'] ?>" class="btn-small">✏️ Editar</a>
                        
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="accion" value="toggle_activo">
                            <input type="hidden" name="id_criterio" value="<?= $crit['id_criterio'] ?>">
                            <button type="submit" class="btn-small">
                                <?= $crit['activo'] ? '🔒 Desactivar' : '🔓 Activar' ?>
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>
