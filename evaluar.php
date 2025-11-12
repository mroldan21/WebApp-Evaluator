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
    <style>
        .estrellas-container {
            display: flex;
            gap: 8px;
            margin: 15px 0;
            font-size: 2em;
        }
        
        .estrella {
            cursor: pointer;
            color: #ddd;
            transition: all 0.2s ease;
            user-select: none;
        }
        
        .estrella:hover,
        .estrella.hover {
            color: #ffc107;
            transform: scale(1.2);
        }
        
        .estrella.seleccionada {
            color: #ffc107;
        }
        
        .criterio {
            background: #f8f9fa;
            padding: 20px;
            margin: 20px 0;
            border-radius: 8px;
            border-left: 4px solid #667eea;
        }
        
        .puntaje-seleccionado {
            font-size: 0.9em;
            color: #666;
            margin-top: 5px;
            min-height: 20px;
        }
        
        .info-box {
            background: #e8f4f8;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            border-left: 4px solid #17a2b8;
        }
        
        .total-container {
            background: #fff3cd;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            margin: 20px 0;
            border: 2px solid #ffc107;
        }
    </style>
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
                    
                    <div class="estrellas-container" 
                         data-criterio-id="<?= $criterio['id_criterio'] ?>"
                         data-min="<?= $criterio['puntaje_minimo'] ?>"
                         data-max="<?= $criterio['puntaje_maximo'] ?>"
                         data-peso="<?= $criterio['peso_porcentual'] ?>">
                        <!-- Las estrellas se generan dinámicamente con JavaScript -->
                    </div>
                    
                    <div class="puntaje-seleccionado" id="puntaje-texto-<?= $criterio['id_criterio'] ?>">
                        Selecciona una calificación
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
        // Descripciones por nivel
        const descripciones = {
            1: 'Insuficiente',
            2: 'Regular',
            3: 'Aceptable',
            4: 'Bueno',
            5: 'Excelente'
        };

        let criteriosEvaluados = 0;
        const totalCriterios = <?= count($criterios) ?>;
        const pesoTotal = <?= $peso_total ?>;

        // Estructura para almacenar los puntajes de cada criterio
        const criteriosData = {};

        // Generar estrellas para cada criterio
        document.querySelectorAll('.estrellas-container').forEach(container => {
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

            // Crear 5 estrellas siempre (mapearemos al rango real del criterio)
            for (let i = 1; i <= 5; i++) {
                const estrella = document.createElement('span');
                estrella.className = 'estrella';
                estrella.innerHTML = '★';
                estrella.dataset.valor = i;
                
                // Efecto hover: iluminar hasta la estrella actual
                estrella.addEventListener('mouseenter', () => {
                    const estrellas = container.querySelectorAll('.estrella');
                    estrellas.forEach((e, idx) => {
                        if (idx < i) {
                            e.classList.add('hover');
                        } else {
                            e.classList.remove('hover');
                        }
                    });
                });
                
                // Quitar hover
                estrella.addEventListener('mouseleave', () => {
                    container.querySelectorAll('.estrella').forEach(e => {
                        e.classList.remove('hover');
                    });
                });
                
                // Click: seleccionar calificación
                estrella.addEventListener('click', () => {
                    const valorEstrella = parseInt(estrella.dataset.valor);
                    
                    // Mapear de escala 1-5 a la escala real del criterio (min-max)
                    const valorReal = Math.round(min + ((valorEstrella - 1) / 4) * (max - min));
                    
                    // Actualizar estrellas visuales
                    const estrellas = container.querySelectorAll('.estrella');
                    estrellas.forEach((e, idx) => {
                        if (idx < valorEstrella) {
                            e.classList.add('seleccionada');
                        } else {
                            e.classList.remove('seleccionada');
                        }
                    });
                    
                    // Actualizar input hidden
                    const inputPuntaje = document.querySelector(`input[name="criterio[${criterioId}][puntaje]"]`);
                    const valorAnterior = parseInt(inputPuntaje.value);
                    inputPuntaje.value = valorReal;
                    
                    // Actualizar texto descriptivo
                    const descripcion = descripciones[valorEstrella] || '';
                    document.getElementById(`puntaje-texto-${criterioId}`).textContent = 
                        `${valorReal} puntos - ${descripcion}`;
                    
                    // Actualizar datos del criterio
                    criteriosData[criterioId].puntaje = valorReal;
                    
                    // Actualizar contador si es la primera vez
                    if (valorAnterior === 0 && valorReal > 0) {
                        criteriosEvaluados++;
                    }
                    
                    actualizarTotales();
                });
                
                container.appendChild(estrella);
            }
        });

        function actualizarTotales() {
            // Actualizar contador
            document.getElementById('total-evaluados').textContent = criteriosEvaluados;
            
            // Calcular puntajes
            let puntajePonderado = 0;
            let sumaPorcentajes = 0;
            let cantidadEvaluados = 0;

            for (let criterioId in criteriosData) {
                const criterio = criteriosData[criterioId];
                if (criterio.puntaje > 0) {
                    // Normalizar a porcentaje
                    const porcentaje = ((criterio.puntaje - criterio.min) / (criterio.max - criterio.min)) * 100;
                    
                    // Aplicar peso
                    const puntajePonderadoCriterio = (porcentaje * criterio.peso) / 100;
                    puntajePonderado += puntajePonderadoCriterio;
                    
                    // Acumular para promedio simple
                    sumaPorcentajes += porcentaje;
                    cantidadEvaluados++;
                }
            }

            // Normalizar puntaje ponderado
            if (pesoTotal > 0) {
                puntajePonderado = (puntajePonderado * 100) / pesoTotal;
            }

            // Calcular promedio simple (en escala del máximo común)
            const promedioSimplePorcentaje = cantidadEvaluados > 0 ? sumaPorcentajes / cantidadEvaluados : 0;
            const maxComun = <?= $criterios[0]['puntaje_maximo'] ?? 4 ?>;
            const promedioSimple = (promedioSimplePorcentaje / 100) * maxComun;

            // Actualizar interfaz
            document.getElementById('puntaje-ponderado').textContent = puntajePonderado.toFixed(2);
            document.getElementById('promedio-simple').textContent = promedioSimple.toFixed(2);

            // Habilitar/deshabilitar botón
            document.getElementById('guardar').disabled = (criteriosEvaluados < totalCriterios);
        }
    </script>
</body>
</html>
