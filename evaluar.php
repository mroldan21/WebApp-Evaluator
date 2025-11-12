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

// Calcular peso total para normalizar
$peso_total = array_sum(array_column($criterios, 'peso_porcentual'));
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
                    <h4>
                        <?= ($index + 1) ?>. <?= htmlspecialchars($criterio['nombre']) ?>
                        <span style="color: #666; font-size: 0.9em;">
                            (Peso: <?= $criterio['peso_porcentual'] ?>%)
                        </span>
                    </h4>
                    <?php if ($criterio['descripcion']): ?>
                        <p class="descripcion"><?= htmlspecialchars($criterio['descripcion']) ?></p>
                    <?php endif; ?>
                    
                    <div class="botones-nivel" 
                         data-criterio-id="<?= $criterio['id_criterio'] ?>"
                         data-min="<?= $criterio['puntaje_minimo'] ?>"
                         data-max="<?= $criterio['puntaje_maximo'] ?>"
                         data-peso="<?= $criterio['peso_porcentual'] ?>">
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
                <p style="font-size: 1.2em; margin-top: 10px;">
                    <strong>Puntaje ponderado:</strong> 
                    <span id="puntaje-ponderado" style="font-size: 1.5em; color: #667eea;">0.00</span> / 100
                </p>
                <p style="font-size: 0.9em; color: #666; margin-top: 5px;">
                    <strong>Promedio simple:</strong> 
                    <span id="promedio-simple">0.00</span> / <?= $criterios[0]['puntaje_maximo'] ?? 4 ?>
                </p>
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
        const pesoTotal = <?= $peso_total ?>;

        // Estructura para almacenar los puntajes de cada criterio
        const criteriosData = {};

        // Generar botones dinámicamente para cada criterio
        document.querySelectorAll('.botones-nivel').forEach(container => {
            const criterioId = container.dataset.criterioId;
            const min = parseInt(container.dataset.min);
            const max = parseInt(container.dataset.max);
            const peso = parseFloat(container.dataset.peso);

            // Inicializar datos del criterio
            criteriosData[criterioId] = {
                puntaje: 0,
                min: min,
                max: max,
                peso: peso
            };

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
                    
                    // Actualizar datos del criterio
                    criteriosData[criterioId].puntaje = i;
                    
                    // Actualizar contador si es la primera vez que se evalúa este criterio
                    if (valorAnterior === 0 && i > 0) {
                        criteriosEvaluados++;
                    } else if (valorAnterior > 0 && i === 0) {
                        criteriosEvaluados--;
                    }
                    
                    actualizarTotales();
                });
                
                container.appendChild(btn);
            }
        });

        function actualizarTotales() {
            // Actualizar contador de criterios evaluados
            document.getElementById('total-evaluados').textContent = criteriosEvaluados;
            
            // Calcular puntaje ponderado y promedio simple
            let puntajePonderado = 0;
            let sumaPuntajes = 0;
            let cantidadEvaluados = 0;

            for (let criterioId in criteriosData) {
                const criterio = criteriosData[criterioId];
                if (criterio.puntaje > 0) {
                    // Normalizar el puntaje del criterio a escala 0-100
                    const puntajeNormalizado = ((criterio.puntaje - criterio.min) / (criterio.max - criterio.min)) * 100;
                    
                    // Aplicar el peso del criterio
                    const puntajePonderadoCriterio = (puntajeNormalizado * criterio.peso) / 100;
                    puntajePonderado += puntajePonderadoCriterio;
                    
                    // Para el promedio simple
                    sumaPuntajes += criterio.puntaje;
                    cantidadEvaluados++;
                }
            }

            // Normalizar el puntaje ponderado según el peso total
            if (pesoTotal > 0) {
                puntajePonderado = (puntajePonderado * 100) / pesoTotal;
            }

            // Calcular promedio simple
            const promedioSimple = cantidadEvaluados > 0 ? sumaPuntajes / cantidadEvaluados : 0;

            // Actualizar la interfaz
            document.getElementById('puntaje-ponderado').textContent = puntajePonderado.toFixed(2);
            document.getElementById('promedio-simple').textContent = promedioSimple.toFixed(2);

            // Habilitar/deshabilitar botón de guardar
            document.getElementById('guardar').disabled = (criteriosEvaluados < totalCriterios);
        }
    </script>
</body>
</html>
