<?php
require 'config.php';

// Validaciones básicas
if (!isset($_GET['equipo_id']) || !isset($_GET['evaluador_id']) || !isset($_GET['instancia_id'])) {
    header('Location: index.php');
    exit;
}

$equipo_id = (int)$_GET['equipo_id'];
$evaluador_id = (int)$_GET['evaluador_id'];
$instancia_id = (int)$_GET['instancia_id'];

// Obtener información de la instancia
$stmt_instancia = $pdo->prepare("SELECT nombre, descripcion FROM instancias_evaluacion WHERE id_instancia = ? AND activa = TRUE");
$stmt_instancia->execute([$instancia_id]);
$instancia = $stmt_instancia->fetch(PDO::FETCH_ASSOC);

if (!$instancia) {
    die("Instancia de evaluación no válida o inactiva.");
}

// Obtener nombres de equipo y evaluador
$stmt_equipo = $pdo->prepare("SELECT nombre FROM equipos WHERE id_equipo = ?");
$stmt_equipo->execute([$equipo_id]);
$equipo = $stmt_equipo->fetch(PDO::FETCH_ASSOC);

$stmt_evaluador = $pdo->prepare("SELECT nombre FROM evaluadores WHERE id_evaluador = ?");
$stmt_evaluador->execute([$evaluador_id]);
$evaluador = $stmt_evaluador->fetch(PDO::FETCH_ASSOC);

// Obtener criterios asignados a esta instancia (ordenados)
$stmt_criterios = $pdo->prepare("
    SELECT 
        ce.id_criterio, 
        ce.nombre, 
        ce.descripcion,
        ce.puntaje_minimo,
        ce.puntaje_maximo,
        ic.peso_porcentual
    FROM instancia_criterios ic
    INNER JOIN criterios_evaluacion ce ON ic.id_criterio = ce.id_criterio
    WHERE ic.id_instancia = ?
    ORDER BY ic.orden, ce.nombre
");
$stmt_criterios->execute([$instancia_id]);
$criterios = $stmt_criterios->fetchAll(PDO::FETCH_ASSOC);

if (empty($criterios)) {
    die("No hay criterios definidos para esta instancia de evaluación.");
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Evaluación - <?= htmlspecialchars($instancia['nombre']) ?></title>
    <link rel="stylesheet" href="estilo.css">
</head>
<body>
    <div class="container">
        <h1>Evaluación: <?= htmlspecialchars($instancia['nombre']) ?></h1>
        
        <div class="info-box">
            <p><strong>Equipo:</strong> <?= htmlspecialchars($equipo['nombre']) ?></p>
            <p><strong>Evaluador:</strong> <?= htmlspecialchars($evaluador['nombre']) ?></p>
            <?php if ($instancia['descripcion']): ?>
                <p><strong>Descripción:</strong> <?= htmlspecialchars($instancia['descripcion']) ?></p>
            <?php endif; ?>
        </div>

        <form id="form-eval" method="POST" action="guardar.php">
            <input type="hidden" name="equipo_id" value="<?= $equipo_id ?>">
            <input type="hidden" name="evaluador_id" value="<?= $evaluador_id ?>">
            <input type="hidden" name="instancia_id" value="<?= $instancia_id ?>">

            <?php foreach ($criterios as $index => $criterio): ?>
                <div class="criterio">
                    <h4><?= ($index + 1) ?>. <?= htmlspecialchars($criterio['nombre']) ?></h4>
                    <?php if ($criterio['descripcion']): ?>
                        <p class="descripcion"><?= htmlspecialchars($criterio['descripcion']) ?></p>
                    <?php endif; ?>
                    
                    <div class="botones-nivel" 
                         data-criterio-id="<?= $criterio['id_criterio'] ?>"
                         data-min="<?= $criterio['puntaje_minimo'] ?>"
                         data-max="<?= $criterio['puntaje_maximo'] ?>">
                    </div>
                    
                    <input type="hidden" 
                           name="criterio[<?= $criterio['id_criterio'] ?>][puntaje]" 
                           value="0" 
                           class="puntaje-input">
                    
                    <textarea 
                        name="criterio[<?= $criterio['id_criterio'] ?>][comentario]" 
                        placeholder="Comentario opcional sobre este criterio" 
                        rows="2"></textarea>
                </div>
            <?php endforeach; ?>

            <label>Observaciones generales (opcional):</label>
            <textarea name="observaciones" rows="4"></textarea>

            <div class="total-container">
                <p><strong>Criterios evaluados: <span id="total-evaluados">0</span> / <?= count($criterios) ?></strong></p>
            </div>

            <button type="submit" id="guardar" disabled>Guardar evaluación</button>
        </form>
    </div>

    <script>
        // Niveles descriptivos (puedes personalizarlos)
        const nivelesDescriptivos = {
            1: 'Insuficiente',
            2: 'Suficiente',
            3: 'Bueno',
            4: 'Excelente'
        };

        let criteriosEvaluados = 0;
        const totalCriterios = <?= count($criterios) ?>;

        // Generar botones dinámicamente para cada criterio
        document.querySelectorAll('.botones-nivel').forEach(container => {
            const criterioId = container.dataset.criterioId;
            const min = parseInt(container.dataset.min);
            const max = parseInt(container.dataset.max);

            for (let i = min; i <= max; i++) {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'btn-nivel';
                btn.dataset.value = i;
                btn.textContent = `${i}${nivelesDescriptivos[i] ? ' (' + nivelesDescriptivos[i] + ')' : ''}`;
                
                btn.addEventListener('click', () => {
                    // Remover activo de todos los botones del mismo criterio
                    container.querySelectorAll('.btn-nivel').forEach(b => b.classList.remove('activo'));
                    btn.classList.add('activo');
                    
                    // Actualizar valor hidden
                    const inputPuntaje = document.querySelector(`input[name="criterio[${criterioId}][puntaje]"]`);
                    const valorAnterior = parseInt(inputPuntaje.value);
                    inputPuntaje.value = i;
                    
                    // Actualizar contador si es la primera vez que se evalúa este criterio
                    if (valorAnterior === 0 && i > 0) {
                        criteriosEvaluados++;
                        actualizarContador();
                    }
                });
                
                container.appendChild(btn);
            }
        });

        function actualizarContador() {
            document.getElementById('total-evaluados').textContent = criteriosEvaluados;
            document.getElementById('guardar').disabled = (criteriosEvaluados < totalCriterios);
        }
    </script>
</body>
</html>
