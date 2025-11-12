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
        ic.puntaje_maximo_criterio

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
$peso_total = array_sum(array_column($criterios, 'puntaje_maximo_criterio'));
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
                            (Peso: <?= $criterio['puntaje_maximo_criterio'] ?>%)
                        </span>
                    </h4>
                    <?php if ($criterio['descripcion']): ?>
                        <p class="descripcion"><?= htmlspecialchars($criterio['descripcion']) ?></p>
                    <?php endif; ?>
                    
                    <div class="estrellas-container" 
                         data-criterio-id="<?= $criterio['id_criterio'] ?>"
                         data-min="<?= $criterio['puntaje_minimo'] ?>"
                         data-max="<?= $criterio['puntaje_maximo'] ?>"
                         data-peso="<?= $criterio['puntaje_maximo_criterio'] ?>">
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
                    <strong>Equivalente:</strong> 
                    <span id="promedio-simple">0.00 / 10</span>
                </p>
            </div>


            <button type="submit" id="guardar" disabled>Guardar evaluación</button>
        </form>
    </div>

    <script>
      const descripciones = {
          0: 'Sin calificación',
          1: 'Muy deficiente',
          2: 'Insuficiente',
          3: 'Aceptable',
          4: 'Bueno',
          5: 'Excelente'
      };

      let criteriosEvaluados = 0;
      const totalCriterios = <?= count($criterios) ?>;
      const pesoTotal = <?= $peso_total ?>;
      const criteriosData = {};

      // Generar estrellas para cada criterio
      document.querySelectorAll('.estrellas-container').forEach(container => {
          const criterioId = container.dataset.criterioId;
          const min = parseInt(container.dataset.min);
          const max = parseInt(container.dataset.max);
          const peso = parseFloat(container.dataset.peso);

          criteriosData[criterioId] = {
              puntaje: 0,
              min: min,
              max: max,
              peso: peso
          };

          // Crear 5 estrellas
          for (let i = 1; i <= 5; i++) {
              const estrella = document.createElement('span');
              estrella.className = 'estrella';
              estrella.innerHTML = '★';
              estrella.dataset.valor = i;
              
              // Efecto hover
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
              
              estrella.addEventListener('mouseleave', () => {
                  container.querySelectorAll('.estrella').forEach(e => {
                      e.classList.remove('hover');
                  });
              });
              
              // Click: seleccionar o desmarcar
              estrella.addEventListener('click', () => {
                  const valorEstrella = parseInt(estrella.dataset.valor);
                  const inputPuntaje = document.querySelector(`input[name="criterio[${criterioId}][puntaje]"]`);
                  const valorAnterior = parseInt(inputPuntaje.value);
                  
                  // Mapear de 1-5 a min-max del criterio
                  const valorReal = Math.round(min + ((valorEstrella - 1) / 4) * (max - min));
                  
                  // Si se clickea la misma estrella, desmarcar (poner en 0)
                  const estaSeleccionada = criteriosData[criterioId].puntaje > 0 && 
                      Math.round(min + ((valorEstrella - 1) / 4) * (max - min)) === criteriosData[criterioId].puntaje;
                  
                  if (estaSeleccionada) {
                      // Desmarcar todas
                      container.querySelectorAll('.estrella').forEach(e => {
                          e.classList.remove('seleccionada');
                      });
                      inputPuntaje.value = 0;
                      criteriosData[criterioId].puntaje = 0;
                      document.getElementById(`puntaje-texto-${criterioId}`).textContent = 'Sin calificación';
                      
                      if (valorAnterior > 0) {
                          criteriosEvaluados--;
                      }
                  } else {
                      // Seleccionar estrellas
                      const estrellas = container.querySelectorAll('.estrella');
                      estrellas.forEach((e, idx) => {
                          if (idx < valorEstrella) {
                              e.classList.add('seleccionada');
                          } else {
                              e.classList.remove('seleccionada');
                          }
                      });
                      
                      inputPuntaje.value = valorReal;
                      criteriosData[criterioId].puntaje = valorReal;
                      
                      const descripcion = descripciones[valorEstrella] || '';
                      document.getElementById(`puntaje-texto-${criterioId}`).textContent = 
                          `${valorReal} puntos - ${descripcion}`;
                      
                      if (valorAnterior === 0) {
                          criteriosEvaluados++;
                      }
                  }
                  
                  actualizarTotales();
              });
              
              container.appendChild(estrella);
          }
      });

      function actualizarTotales() {
          document.getElementById('total-evaluados').textContent = criteriosEvaluados;
          
          // Calcular peso total (suma de puntajes máximos)
          let pesoTotal = 0;
          for (let criterioId in criteriosData) {
              pesoTotal += criteriosData[criterioId].puntaje_max_criterio;
          }
          
          let puntajeTotal = 0;
          
          for (let criterioId in criteriosData) {
              const criterio = criteriosData[criterioId];
              
              if (criterio.puntaje > 0) {
                  // Porcentaje obtenido en el criterio (0-100%)
                  const porcentaje = ((criterio.puntaje - criterio.min) / (criterio.max - criterio.min)) * 100;
                  
                  // Puntaje obtenido = porcentaje × puntaje máximo del criterio
                  const puntajeObtenido = (porcentaje / 100) * criterio.puntaje_max_criterio;
                  
                  puntajeTotal += puntajeObtenido;
              }
          }
          
          // Normalizar a 0-100
          const puntajeFinal = pesoTotal > 0 ? (puntajeTotal / pesoTotal) * 100 : 0;
          const puntajeSobre10 = puntajeFinal / 10;
          
          document.getElementById('puntaje-ponderado').textContent = puntajeFinal.toFixed(2);
          document.getElementById('promedio-simple').textContent = puntajeSobre10.toFixed(2) + ' / 10';
          document.getElementById('guardar').disabled = (criteriosEvaluados < totalCriterios);
      }


  </script>

</body>
</html>
