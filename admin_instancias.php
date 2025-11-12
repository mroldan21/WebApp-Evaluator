<?php
require 'config.php';

$mensaje = '';
$error = '';

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    
    try {
        if ($accion === 'crear_instancia') {
            $pdo->beginTransaction();
            
            // Crear instancia
            $stmt = $pdo->prepare("
                INSERT INTO instancias_evaluacion (nombre, descripcion, creado_por)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([
                $_POST['nombre'],
                $_POST['descripcion'],
                $_POST['creado_por'] ?: null
            ]);
            
            $id_instancia = $pdo->lastInsertId();
            
            // Asignar criterios
            if (!empty($_POST['criterios'])) {
                $stmt_criterio = $pdo->prepare("
                    INSERT INTO instancia_criterios (id_instancia, id_criterio, peso_porcentual, orden)
                    VALUES (?, ?, ?, ?)
                ");
                
                $orden = 1;
                foreach ($_POST['criterios'] as $id_criterio) {
                    $peso = $_POST['peso'][$id_criterio] ?? 100;
                    $stmt_criterio->execute([$id_instancia, $id_criterio, $peso, $orden]);
                    $orden++;
                }
            }
            
            $pdo->commit();
            $mensaje = "✅ Instancia de evaluación creada exitosamente.";
            
        } elseif ($accion === 'toggle_activa') {
            $stmt = $pdo->prepare("
                UPDATE instancias_evaluacion 
                SET activa = NOT activa 
                WHERE id_instancia = ?
            ");
            $stmt->execute([(int)$_POST['id_instancia']]);
            $mensaje = "✅ Estado de la instancia actualizado.";
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = "❌ Error: " . $e->getMessage();
    }
}

// Cargar criterios activos para el formulario
$stmt = $pdo->query("
    SELECT * FROM criterios_evaluacion 
    WHERE activo = TRUE 
    ORDER BY nombre
");
$criterios_disponibles = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Cargar evaluadores para el formulario
$stmt = $pdo->query("SELECT * FROM evaluadores ORDER BY nombre");
$evaluadores = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Cargar instancias existentes con conteo de criterios
$stmt = $pdo->query("
    SELECT 
        ie.*,
        ev.nombre AS creador_nombre,
        COUNT(ic.id_criterio) AS num_criterios
    FROM instancias_evaluacion ie
    LEFT JOIN evaluadores ev ON ie.creado_por = ev.id_evaluador
    LEFT JOIN instancia_criterios ic ON ie.id_instancia = ic.id_instancia
    GROUP BY ie.id_instancia
    ORDER BY ie.fecha_inicio DESC
");
$instancias = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Instancias</title>
    <link rel="stylesheet" href="estilo.css">
    <style>
        .form-instancia {
            background: #e8f4f8;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .instancia-item {
            border: 1px solid #ddd;
            padding: 15px;
            margin: 10px 0;
            border-radius: 5px;
            background: #f9f9f9;
        }
        .instancia-item.inactiva {
            opacity: 0.6;
            background: #f0f0f0;
        }
        .criterios-checklist {
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #ddd;
            padding: 10px;
            background: white;
        }
        .criterio-check {
            margin: 10px 0;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 3px;
        }
        .btn-small {
            padding: 5px 10px;
            font-size: 0.9em;
            margin-right: 5px;
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
        <h1>📋 Gestión de Instancias de Evaluación</h1>
        
        <p><a href="index.php">← Volver al inicio</a></p>

        <?php if ($mensaje): ?>
            <div class="mensaje exito"><?= htmlspecialchars($mensaje) ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="mensaje error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- Formulario de Crear Instancia -->
        <div class="form-instancia">
            <h2>Crear Nueva Instancia de Evaluación</h2>
            <form method="POST">
                <input type="hidden" name="accion" value="crear_instancia">

                <label>Nombre de la instancia: *</label>
                <input type="text" name="nombre" required placeholder="Ej: Evaluación Final 2025">

                <label>Descripción:</label>
                <textarea name="descripcion" rows="3" placeholder="Descripción opcional de esta evaluación"></textarea>

                <label>Creado por (opcional):</label>
                <select name="creado_por">
                    <option value="">-- Sin asignar --</option>
                    <?php foreach ($evaluadores as $ev): ?>
                        <option value="<?= $ev['id_evaluador'] ?>">
                            <?= htmlspecialchars($ev['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <h3>Seleccionar Criterios de Evaluación:</h3>
                
                <?php if (empty($criterios_disponibles)): ?>
                    <p style="color: red;">⚠️ No hay criterios activos disponibles. <a href="admin_criterios.php">Crear criterios primero</a>.</p>
                <?php else: ?>
                    <div class="criterios-checklist">
                        <?php foreach ($criterios_disponibles as $crit): ?>
                            <div class="criterio-check">
                                <label>
                                    <input type="checkbox" name="criterios[]" value="<?= $crit['id_criterio'] ?>">
                                    <strong><?= htmlspecialchars($crit['nombre']) ?></strong>
                                </label>
                                <p style="font-size: 0.9em; margin: 5px 0 5px 25px;">
                                    Rango: <?= $crit['puntaje_minimo'] ?>-<?= $crit['puntaje_maximo'] ?> pts
                                    <?php if ($crit['descripcion']): ?>
                                        <br><?= htmlspecialchars($crit['descripcion']) ?>
                                    <?php endif; ?>
                                </p>
                                <div style="margin-left: 25px;">
                                    <label style="font-size: 0.9em;">
                                        Puntaje máximo del criterio:
                                        <input type="number" name="puntaje_max[<?= $crit['id_criterio'] ?>]" 
                                            value="100" min="1" max="1000" step="0.01" 
                                            style="width: 100px;">
                                    </label>
                                    <small style="display: block; color: #666; margin-top: 3px;">
                                        Ej: Si este criterio vale 40 puntos y otro 10, el peso se calculará automáticamente (40/50 = 80%)
                                    </small>
                                </div>

                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <button type="submit" style="margin-top: 15px;">Crear Instancia</button>
                <?php endif; ?>
            </form>
        </div>

        <!-- Lista de Instancias -->
        <h2>Instancias Existentes (<?= count($instancias) ?>)</h2>
        
        <?php if (empty($instancias)): ?>
            <p>No hay instancias creadas.</p>
        <?php else: ?>
            <?php foreach ($instancias as $inst): ?>
                <div class="instancia-item <?= $inst['activa'] ? '' : 'inactiva' ?>">
                    <h3><?= htmlspecialchars($inst['nombre']) ?></h3>
                    
                    <?php if ($inst['descripcion']): ?>
                        <p><?= htmlspecialchars($inst['descripcion']) ?></p>
                    <?php endif; ?>
                    
                    <p>
                        <strong>Estado:</strong> <?= $inst['activa'] ? '✅ Activa' : '⛔ Inactiva' ?> |
                        <strong>Criterios:</strong> <?= $inst['num_criterios'] ?> |
                        <strong>Creada:</strong> <?= date('d/m/Y H:i', strtotime($inst['fecha_inicio'])) ?>
                        <?php if ($inst['creador_nombre']): ?>
                            | <strong>Por:</strong> <?= htmlspecialchars($inst['creador_nombre']) ?>
                        <?php endif; ?>
                    </p>
                    
                    <div>
                        <a href="ver_instancia.php?id=<?= $inst['id_instancia'] ?>" class="btn-small">👁️ Ver detalles</a>
                        
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="accion" value="toggle_activa">
                            <input type="hidden" name="id_instancia" value="<?= $inst['id_instancia'] ?>">
                            <button type="submit" class="btn-small">
                                <?= $inst['activa'] ? '🔒 Desactivar' : '🔓 Activar' ?>
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>
