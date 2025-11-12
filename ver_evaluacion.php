<?php
require 'config.php';

if (!isset($_GET['id'])) {
    header('Location: index.php');
    exit;
}

$id_evaluacion = (int)$_GET['id'];

// Obtener información completa de la evaluación
$stmt = $pdo->prepare("
    SELECT 
        e.*,
        ie.nombre AS instancia_nombre,
        eq.nombre AS equipo_nombre,
        ev.nombre AS evaluador_nombre,
        e.fecha_eval
    FROM evaluaciones e
    INNER JOIN instancias_evaluacion ie ON e.id_instancia = ie.id_instancia
    INNER JOIN equipos eq ON e.id_equipo = eq.id_equipo
    INNER JOIN evaluadores ev ON e.id_evaluador = ev.id_evaluador
    WHERE e.id_evaluacion = ?
");
$stmt->execute([$id_evaluacion]);
$evaluacion = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$evaluacion) {
    die("Evaluación no encontrada.");
}

// Obtener criterios con sus pesos desde la instancia
$stmt = $pdo->prepare("
    SELECT 
        de.*,
        ce.nombre AS criterio_nombre,
        ce.descripcion AS criterio_descripcion,
        ce.puntaje_minimo,
        ce.puntaje_maximo,
        ic.peso_porcentual
    FROM detalles_evaluacion de
    INNER JOIN criterios_evaluacion ce ON de.id_criterio = ce.id_criterio
    INNER JOIN instancia_criterios ic ON de.id_criterio = ic.id_criterio
    INNER JOIN evaluaciones e ON de.id_evaluacion = e.id_evaluacion
    WHERE de.id_evaluacion = ? AND ic.id_instancia = e.id_instancia
    ORDER BY de.fecha_registro
");
$stmt->execute([$id_evaluacion]);
$detalles = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calcular puntaje ponderado
$puntaje_total = 0;
$puntaje_maximo_total = 0;

foreach ($detalles as $det) {
    // Porcentaje obtenido (0-100%)
    $porcentaje = (($det['puntaje'] - $det['puntaje_minimo']) / ($det['puntaje_maximo'] - $det['puntaje_minimo'])) * 100;
    
    // Aplicar peso del criterio
    $puntaje_obtenido = ($porcentaje / 100) * $det['peso_porcentual'];
    
    $puntaje_total += $puntaje_obtenido;
    $puntaje_maximo_total += $det['peso_porcentual'];
}

// Normalizar a 0-100
$puntaje_final = $puntaje_maximo_total > 0 ? ($puntaje_total / $puntaje_maximo_total) * 100 : 0;

// También sobre 10
$puntaje_sobre_10 = $puntaje_final / 10;

$total_criterios = count($detalles);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ver Evaluación #<?= $id_evaluacion ?></title>
    <link rel="stylesheet" href="estilo.css">
    <style>
        .info-box {
            background: #e8f4f8;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .detalle-item {
            border: 1px solid #ddd;
            padding: 15px;
            margin: 10px 0;
            border-radius: 5px;
            background: #f9f9f9;
        }
        .puntaje-badge {
            display: inline-block;
            padding: 8px 15px;
            border-radius: 5px;
            font-weight: bold;
            color: white;
            font-size: 1.1em;
        }
        .puntaje-1 { background: #dc3545; }
        .puntaje-2 { background: #ffc107; color: #000; }
        .puntaje-3 { background: #28a745; }
        .puntaje-4 { background: #007bff; }
        .puntaje-5 { background: #6f42c1; }
        .resumen-box {
            background: #d4edda;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            margin: 20px 0;
        }
        .observaciones-box {
            background: #fff3cd;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .estado-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 3px;
            font-weight: bold;
            font-size: 0.9em;
        }
        .estado-enviada { background: #28a745; color: white; }
        .estado-borrador { background: #6c757d; color: white; }
        .estado-revisada { background: #007bff; color: white; }
    </style>
</head>
<body>
    <div class="container">
        <h1>📊 Evaluación #<?= $id_evaluacion ?></h1>
        
        <p>
            <a href="index.php">← Volver al inicio</a> | 
            <a href="reportes.php">Ver reportes</a>
        </p>

        <div class="info-box">
            <h2><?= htmlspecialchars($evaluacion['instancia_nombre']) ?></h2>
            <p><strong>Equipo:</strong> <?= htmlspecialchars($evaluacion['equipo_nombre']) ?></p>
            <p><strong>Evaluador:</strong> <?= htmlspecialchars($evaluacion['evaluador_nombre']) ?></p>
            <p><strong>Fecha:</strong> <?= date('d/m/Y H:i', strtotime($evaluacion['fecha_eval'])) ?></p>
            <p>
                <strong>Estado:</strong> 
                <span class="estado-badge estado-<?= strtolower($evaluacion['estado']) ?>">
                    <?= strtoupper($evaluacion['estado']) ?>
                </span>
            </p>
        </div>

        <div class="resumen-box">
            <h2>Puntaje: <?= round($puntaje_final, 2) ?> / 100</h2>
            <p>Equivalente: <?= round($puntaje_sobre_10, 2) ?> / 10</p>
            <p>Total de criterios evaluados: <?= $total_criterios ?></p>
        </div>



        <h2>Detalles por Criterio</h2>
        
        <?php if (empty($detalles)): ?>
            <p>No hay detalles de criterios para esta evaluación.</p>
        <?php else: ?>
            <?php foreach ($detalles as $index => $det): ?>
                <div class="detalle-item">
                    <h3><?= ($index + 1) ?>. <?= htmlspecialchars($det['criterio_nombre']) ?></h3>
                    
                    <?php if ($det['criterio_descripcion']): ?>
                        <p style="color: #666;"><?= htmlspecialchars($det['criterio_descripcion']) ?></p>
                    <?php endif; ?>
                    
                    <p>
                        <strong>Puntaje asignado:</strong> 
                        <?php
                            // Determinar clase según el puntaje normalizado
                            $porcentaje = (($det['puntaje'] - $det['puntaje_minimo']) / ($det['puntaje_maximo'] - $det['puntaje_minimo'])) * 100;
                            $clase = 'puntaje-1';
                            if ($porcentaje >= 90) $clase = 'puntaje-5';
                            elseif ($porcentaje >= 75) $clase = 'puntaje-4';
                            elseif ($porcentaje >= 60) $clase = 'puntaje-3';
                            elseif ($porcentaje >= 40) $clase = 'puntaje-2';
                        ?>
                        <span class="puntaje-badge <?= $clase ?>">
                            <?= $det['puntaje'] ?> / <?= $det['puntaje_maximo'] ?>
                        </span>
                    </p>
                    
                    <?php if (!empty($det['comentario'])): ?>
                        <p><strong>Comentario:</strong> <?= nl2br(htmlspecialchars($det['comentario'])) ?></p>
                    <?php endif; ?>
                    
                    <p style="font-size: 0.85em; color: #999;">
                        Registrado: <?= date('d/m/Y H:i:s', strtotime($det['fecha_registro'])) ?>
                    </p>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php if (!empty($evaluacion['observaciones'])): ?>
            <div class="observaciones-box">
                <h3>📝 Observaciones Generales</h3>
                <p><?= nl2br(htmlspecialchars($evaluacion['observaciones'])) ?></p>
            </div>
        <?php endif; ?>

            <!-- Botón para generar prompt de IA -->
            <div style="background: #e3f2fd; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #2196f3;">
                <h3>🤖 Generar Devolución con IA</h3>
                <p style="color: #666; margin: 10px 0;">
                    Genera un prompt optimizado para obtener una devolución constructiva y personalizada de esta evaluación.
                </p>
                <button onclick="generarPrompt()" style="background: #2196f3; color: white; padding: 12px 24px; border: none; border-radius: 6px; cursor: pointer; font-size: 1em;">
                    📝 Generar Prompt para IA
                </button>
                <button onclick="copiarPrompt()" id="btn-copiar" style="background: #4caf50; color: white; padding: 12px 24px; border: none; border-radius: 6px; cursor: pointer; font-size: 1em; margin-left: 10px; display: none;">
                    📋 Copiar Prompt
                </button>
                <button onclick="enviarAIA()" id="btn-enviar-ia" style="background: #ff9800; color: white; padding: 12px 24px; border: none; border-radius: 6px; cursor: pointer; font-size: 1em; margin-left: 10px; display: none;">
                    🤖 Enviar a IA
                </button>

            </div>

            <!-- Área donde se mostrará el prompt generado -->
            <div id="prompt-container" style="display: none; background: #f5f5f5; padding: 20px; border-radius: 8px; margin: 20px 0;">
                <h3>Prompt Generado:</h3>
                <div style="background: white; padding: 15px; border-radius: 5px; border: 1px solid #ddd; max-height: 400px; overflow-y: auto;">
                    <pre id="prompt-text" style="white-space: pre-wrap; font-family: 'Courier New', monospace; font-size: 0.9em; margin: 0;"></pre>
                </div>
            </div>

            <script>
                const evaluacionData = {
                    id: <?= $id_evaluacion ?>,
                    instancia: <?= json_encode($evaluacion['instancia_nombre']) ?>,
                    equipo: <?= json_encode($evaluacion['equipo_nombre']) ?>,
                    evaluador: <?= json_encode($evaluacion['evaluador_nombre']) ?>,
                    fecha: <?= json_encode(date('d/m/Y H:i', strtotime($evaluacion['fecha_eval']))) ?>,
                    promedio: <?= $promedio ?>,
                    observaciones: <?= json_encode($evaluacion['observaciones'] ?? '') ?>,
                    criterios: [
                        <?php foreach ($detalles as $det): ?>
                        {
                            nombre: <?= json_encode($det['criterio_nombre']) ?>,
                            descripcion: <?= json_encode($det['criterio_descripcion']) ?>,
                            puntaje: <?= $det['puntaje'] ?>,
                            maximo: <?= $det['puntaje_maximo'] ?>,
                            porcentaje: <?= round((($det['puntaje'] - $det['puntaje_minimo']) / ($det['puntaje_maximo'] - $det['puntaje_minimo'])) * 100, 1) ?>,
                            comentario: <?= json_encode($det['comentario'] ?? '') ?>
                        },
                        <?php endforeach; ?>
                    ]
                };

                function generarPrompt() {
                    let prompt = `Actúa como un evaluador académico experimentado y genera una devolución constructiva, detallada y motivadora para el siguiente proyecto evaluado.

    ## CONTEXTO DE LA EVALUACIÓN

    **Instancia:** ${evaluacionData.instancia}
    **Equipo:** ${evaluacionData.equipo}
    **Evaluador:** ${evaluacionData.evaluador}
    **Fecha:** ${evaluacionData.fecha}
    **Promedio General:** ${evaluacionData.promedio.toFixed(2)} / 4.0

    ## CRITERIOS EVALUADOS

    `;

                    evaluacionData.criterios.forEach((criterio, index) => {
                        const nivel = criterio.porcentaje >= 80 ? "Excelente" : 
                                    criterio.porcentaje >= 60 ? "Bueno" : 
                                    criterio.porcentaje >= 40 ? "Aceptable" : "Necesita mejora";
                        
                        prompt += `### ${index + 1}. ${criterio.nombre}
    **Puntaje:** ${criterio.puntaje}/${criterio.maximo} (${criterio.porcentaje}% - ${nivel})
    **Descripción:** ${criterio.descripcion || 'Sin descripción'}
    ${criterio.comentario ? `**Comentario del evaluador:** ${criterio.comentario}` : ''}

    `;
                    });

                    if (evaluacionData.observaciones) {
                        prompt += `## OBSERVACIONES GENERALES DEL EVALUADOR

    ${evaluacionData.observaciones}

    `;
                    }

                    prompt += `## INSTRUCCIONES PARA LA DEVOLUCIÓN

    Por favor, genera una devolución que incluya:

    1. **Resumen ejecutivo**: Breve análisis del desempeño general del equipo
    2. **Fortalezas identificadas**: Aspectos destacados según los criterios evaluados
    3. **Áreas de mejora**: Puntos específicos que requieren atención, con sugerencias concretas
    4. **Recomendaciones prácticas**: Pasos accionables para el equipo
    5. **Mensaje motivacional**: Cierre positivo y alentador

    **Tono:** Constructivo, profesional y motivador
    **Extensión:** 400-600 palabras
    **Formato:** Estructura clara con títulos y bullets points cuando sea apropiado`;

                    document.getElementById('prompt-text').textContent = prompt;
                    document.getElementById('prompt-container').style.display = 'block';
                    document.getElementById('btn-copiar').style.display = 'inline-block';
                    document.getElementById('btn-enviar-ia').style.display = 'inline-block';
                    
                    // Scroll suave hasta el prompt
                    document.getElementById('prompt-container').scrollIntoView({ behavior: 'smooth' });
                }

                function copiarPrompt() {
                    const promptText = document.getElementById('prompt-text').textContent;
                    navigator.clipboard.writeText(promptText).then(() => {
                        const btn = document.getElementById('btn-copiar');
                        const textoOriginal = btn.textContent;
                        btn.textContent = '✅ ¡Copiado!';
                        btn.style.background = '#4caf50';
                        setTimeout(() => {
                            btn.textContent = textoOriginal;
                            btn.style.background = '#2196f3';
                        }, 2000);
                    }).catch(err => {
                        alert('Error al copiar: ' + err);
                    });
                }

                function enviarAIA() {
                    const prompt = document.getElementById('prompt-text').textContent;
                    const btnEnviar = document.getElementById('btn-enviar-ia');
                    const btnCopiar = document.getElementById('btn-copiar');
                    
                    btnEnviar.disabled = true;
                    btnEnviar.textContent = '⏳ Consultando IA...';
                    btnCopiar.disabled = true;
                    
                    fetch('api_ia.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            id_evaluacion: evaluacionData.id,
                            prompt: prompt,
                            usuario_id: 1 // Hardcodeado por ahora
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Redirigir a la página de visualización
                            window.location.href = `ver_devolucion.php?id=${data.id_devolucion}`;
                        } else {
                            alert('Error: ' + data.error);
                            btnEnviar.disabled = false;
                            btnEnviar.textContent = '🤖 Enviar a IA';
                            btnCopiar.disabled = false;
                        }
                    })
                    .catch(error => {
                        alert('Error de conexión: ' + error);
                        btnEnviar.disabled = false;
                        btnEnviar.textContent = '🤖 Enviar a IA';
                        btnCopiar.disabled = false;
                    });
                }
            </script>

    </div>
</body>
</html>
