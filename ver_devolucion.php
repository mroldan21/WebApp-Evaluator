<?php
require 'config.php';

if (!isset($_GET['id'])) {
    header('Location: reportes.php');
    exit;
}

$id_devolucion = (int)$_GET['id'];

// Obtener devolución con información de la evaluación
$stmt = $pdo->prepare("
    SELECT 
        d.*,
        e.id_evaluacion,
        ie.nombre AS instancia_nombre,
        eq.nombre AS equipo_nombre,
        ev.nombre AS evaluador_nombre,
        e.fecha_eval,
        evl.nombre AS creador_nombre
    FROM devoluciones_ia d
    INNER JOIN evaluaciones e ON d.id_evaluacion = e.id_evaluacion
    INNER JOIN instancias_evaluacion ie ON e.id_instancia = ie.id_instancia
    INNER JOIN equipos eq ON e.id_equipo = eq.id_equipo
    INNER JOIN evaluadores ev ON e.id_evaluador = ev.id_evaluador
    LEFT JOIN evaluadores evl ON d.creado_por = evl.id_evaluador
    WHERE d.id_devolucion = ?
");
$stmt->execute([$id_devolucion]);
$devolucion = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$devolucion) {
    die("Devolución no encontrada.");
}

// Función para convertir Markdown a HTML básico
function markdownToHtml($text) {
    // Convertir títulos
    $text = preg_replace('/^### (.*?)$/m', '<h4>$1</h4>', $text);
    $text = preg_replace('/^## (.*?)$/m', '<h3>$1</h3>', $text);
    $text = preg_replace('/^# (.*?)$/m', '<h2>$1</h2>', $text);
    
    // Convertir negritas
    $text = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $text);
    
    // Convertir bullets
    $text = preg_replace('/^\- (.*?)$/m', '<li>$1</li>', $text);
    $text = preg_replace('/(<li>.*?<\/li>\s*)+/s', '<ul>$0</ul>', $text);
    
    // Convertir saltos de línea dobles en párrafos
    $text = preg_replace('/\n\n/', '</p><p>', $text);
    $text = '<p>' . $text . '</p>';
    
    // Limpiar párrafos vacíos
    $text = preg_replace('/<p>\s*<\/p>/', '', $text);
    
    return $text;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Devolución IA #<?= $id_devolucion ?></title>
    <link rel="stylesheet" href="estilo.css">
    <style>
        .estado-badge {
            display: inline-block;
            padding: 8px 15px;
            border-radius: 5px;
            font-weight: bold;
            color: white;
            font-size: 0.9em;
        }
        .estado-completado { background: #28a745; }
        .estado-procesando { background: #ff9800; }
        .estado-error { background: #dc3545; }
        .estado-pendiente { background: #6c757d; }
        
        .devolucion-content {
            background: white;
            padding: 30px;
            border-radius: 8px;
            margin: 20px 0;
            border-left: 4px solid #2196f3;
            line-height: 1.8;
            font-size: 1.05em;
        }
        
        .devolucion-content h2 {
            color: #2196f3;
            margin-top: 25px;
            margin-bottom: 15px;
            border-bottom: 2px solid #e3f2fd;
            padding-bottom: 10px;
        }
        
        .devolucion-content h3 {
            color: #555;
            margin-top: 20px;
            margin-bottom: 10px;
        }
        
        .devolucion-content h4 {
            color: #666;
            margin-top: 15px;
            margin-bottom: 8px;
        }
        
        .devolucion-content ul {
            margin-left: 20px;
            margin-bottom: 15px;
        }
        
        .devolucion-content li {
            margin-bottom: 8px;
        }
        
        .devolucion-content p {
            margin-bottom: 12px;
        }
        
        .info-tecnica {
            background: #f5f5f5;
            padding: 15px;
            border-radius: 5px;
            font-size: 0.9em;
            color: #666;
            margin: 20px 0;
        }
        
        .info-tecnica strong {
            color: #333;
        }
        
        .prompt-original {
            background: #fafafa;
            padding: 15px;
            border-radius: 5px;
            border: 1px solid #ddd;
            margin: 20px 0;
            max-height: 300px;
            overflow-y: auto;
        }
        
        .prompt-original pre {
            white-space: pre-wrap;
            font-family: 'Courier New', monospace;
            font-size: 0.85em;
            margin: 0;
        }
        
        .acciones-buttons {
            margin: 20px 0;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .btn-accion {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1em;
            color: white;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-copiar { background: #4caf50; }
        .btn-imprimir { background: #2196f3; }
        .btn-regenerar { background: #ff9800; }
        
        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            border-left: 4px solid #dc3545;
            margin: 20px 0;
        }
        
        @media print {
            .no-print {
                display: none !important;
            }
            .devolucion-content {
                border: none;
                box-shadow: none;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🤖 Devolución Generada por IA #<?= $id_devolucion ?></h1>
        
        <p class="no-print">
            <a href="ver_evaluacion.php?id=<?= $devolucion['id_evaluacion'] ?>">← Volver a la evaluación</a> | 
            <a href="reportes.php">Ver reportes</a>
        </p>

        <div class="info-box">
            <h2><?= htmlspecialchars($devolucion['instancia_nombre']) ?></h2>
            <p><strong>Equipo:</strong> <?= htmlspecialchars($devolucion['equipo_nombre']) ?></p>
            <p><strong>Evaluador original:</strong> <?= htmlspecialchars($devolucion['evaluador_nombre']) ?></p>
            <p><strong>Fecha de evaluación:</strong> <?= date('d/m/Y H:i', strtotime($devolucion['fecha_eval'])) ?></p>
            <p>
                <strong>Estado de generación:</strong> 
                <span class="estado-badge estado-<?= $devolucion['estado'] ?>">
                    <?= strtoupper($devolucion['estado']) ?>
                </span>
            </p>
            <?php if ($devolucion['creador_nombre']): ?>
                <p><strong>Solicitado por:</strong> <?= htmlspecialchars($devolucion['creador_nombre']) ?></p>
            <?php endif; ?>
        </div>

        <?php if ($devolucion['estado'] === 'completado'): ?>
            
            <div class="acciones-buttons no-print">
                <button onclick="copiarDevolucion()" class="btn-accion btn-copiar">
                    📋 Copiar Devolución
                </button>
                <button onclick="window.print()" class="btn-accion btn-imprimir">
                    🖨️ Imprimir/PDF
                </button>
                <a href="ver_evaluacion.php?id=<?= $devolucion['id_evaluacion'] ?>" class="btn-accion btn-regenerar">
                    🔄 Generar Nueva Devolución
                </a>
            </div>

            <div class="info-tecnica no-print">
                <strong>Información técnica:</strong><br>
                <strong>Modelo:</strong> <?= htmlspecialchars($devolucion['modelo_ia'] ?: 'N/A') ?> | 
                <strong>Tokens usados:</strong> <?= number_format($devolucion['tokens_usados']) ?> | 
                <strong>Tiempo de respuesta:</strong> <?= $devolucion['tiempo_respuesta'] ?> segundos |
                <strong>Generado:</strong> <?= date('d/m/Y H:i:s', strtotime($devolucion['fecha_solicitud'])) ?>
            </div>

            <div class="devolucion-content" id="devolucion-texto">
                <?= markdownToHtml($devolucion['respuesta_ia']) ?>
            </div>

            <details class="no-print" style="margin: 20px 0;">
                <summary style="cursor: pointer; padding: 10px; background: #f5f5f5; border-radius: 5px;">
                    <strong>Ver prompt original enviado a la IA</strong>
                </summary>
                <div class="prompt-original">
                    <pre><?= htmlspecialchars($devolucion['prompt_enviado']) ?></pre>
                </div>
            </details>

        <?php elseif ($devolucion['estado'] === 'error'): ?>
            
            <div class="error-message">
                <h3>❌ Error al generar la devolución</h3>
                <p><?= htmlspecialchars($devolucion['error_mensaje']) ?></p>
            </div>

            <div class="acciones-buttons">
                <a href="ver_evaluacion.php?id=<?= $devolucion['id_evaluacion'] ?>" class="btn-accion btn-regenerar">
                    🔄 Reintentar Generación
                </a>
            </div>

            <details style="margin: 20px 0;">
                <summary style="cursor: pointer; padding: 10px; background: #f5f5f5; border-radius: 5px;">
                    <strong>Ver prompt que se intentó enviar</strong>
                </summary>
                <div class="prompt-original">
                    <pre><?= htmlspecialchars($devolucion['prompt_enviado']) ?></pre>
                </div>
            </details>

        <?php elseif ($devolucion['estado'] === 'procesando'): ?>
            
            <div style="background: #fff3cd; padding: 20px; border-radius: 8px; text-align: center;">
                <h2>⏳ Procesando...</h2>
                <p>La IA está generando la devolución. Esto puede tomar unos segundos.</p>
                <p><button onclick="location.reload()" class="btn-accion btn-imprimir">🔄 Actualizar página</button></p>
            </div>

        <?php else: ?>
            
            <div style="background: #e0e0e0; padding: 20px; border-radius: 8px; text-align: center;">
                <h2>⏸️ Solicitud Pendiente</h2>
                <p>La solicitud está en cola de procesamiento.</p>
            </div>

        <?php endif; ?>

    </div>

    <script>
        function copiarDevolucion() {
            const texto = document.getElementById('devolucion-texto').innerText;
            navigator.clipboard.writeText(texto).then(() => {
                alert('✅ Devolución copiada al portapapeles');
            }).catch(err => {
                alert('❌ Error al copiar: ' + err);
            });
        }
    </script>
</body>
</html>
