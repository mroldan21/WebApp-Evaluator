<?php
if (!isset($_GET['equipo_id']) || !isset($_GET['evaluador_id'])) {
    header('Location: index.php');
    exit;
}
$equipo_id = (int)$_GET['equipo_id'];
$evaluador_id = (int)$_GET['evaluador_id'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Evaluación - Checklist</title>
  <link rel="stylesheet" href="estilo.css">
</head>
<body>
  <div class="container">
    <h1>Checklist de Evaluación</h1>
    <p><strong>Equipo ID:</strong> <?= $equipo_id ?> | <strong>Evaluador ID:</strong> <?= $evaluador_id ?></p>

    <form id="form-eval" method="POST" action="guardar.php">
      <input type="hidden" name="equipo_id" value="<?= $equipo_id ?>">
      <input type="hidden" name="evaluador_id" value="<?= $evaluador_id ?>">

      <!-- Criterio 1 -->
      <div class="criterio">
        <h4>1. Claridad del problema y valor propuesto (10 pts)</h4>
        <div class="botones-nivel" data-field="problema_valor"></div>
        <input type="hidden" name="problema_valor" value="0">
      </div>

      <!-- Criterio 2 -->
      <div class="criterio">
        <h4>2. Integración de contenidos del módulo (20 pts)</h4>
        <div class="botones-nivel" data-field="integracion_contenidos"></div>
        <input type="hidden" name="integracion_contenidos" value="0">
      </div>

      <!-- Criterio 3 -->
      <div class="criterio">
        <h4>3. Funcionalidad y calidad técnica (25 pts)</h4>
        <div class="botones-nivel" data-field="funcionalidad"></div>
        <input type="hidden" name="funcionalidad" value="0">
      </div>

      <!-- Criterio 4 -->
      <div class="criterio">
        <h4>4. Demostración efectiva en 15 min (20 pts)</h4>
        <div class="botones-nivel" data-field="demostracion"></div>
        <input type="hidden" name="demostracion" value="0">
      </div>

      <!-- Criterio 5 -->
      <div class="criterio">
        <h4>5. Trabajo en equipo y distribución de roles (15 pts)</h4>
        <div class="botones-nivel" data-field="trabajo_equipo"></div>
        <input type="hidden" name="trabajo_equipo" value="0">
      </div>

      <!-- Criterio 6 -->
      <div class="criterio">
        <h4>6. Documentación y guía del proyecto (10 pts)</h4>
        <div class="botones-nivel" data-field="documentacion"></div>
        <input type="hidden" name="documentacion" value="0">
      </div>

      <!-- Criterio 7: NUEVO -->
      <div class="criterio">
        <h4>7. Calidad de las respuestas al jurado (10 pts)</h4>
        <div class="botones-nivel" data-field="respuestas_jurado"></div>
        <input type="hidden" name="respuestas_jurado" value="0">
      </div>

      <label>Observaciones (opcional):</label>
      <textarea name="observaciones" rows="3"></textarea>

      <div id="panel-puntaje">
        <h3>Puntaje total: <span id="total">0</span> / 100</h3>
      </div>

      <button type="submit" id="guardar" disabled>Guardar evaluación</button>
    </form>
  </div>

  <script>
    const niveles = ['Insuficiente', 'Suficiente', 'Bueno', 'Excelente'];
    const factores = {
      problema_valor: 2.5,
      integracion_contenidos: 5,
      funcionalidad: 6.25,
      demostracion: 5,
      trabajo_equipo: 3.75,
      documentacion: 2.5,
      respuestas_jurado: 2.5
    };

    let puntajes = {
      problema_valor: 0,
      integracion_contenidos: 0,
      funcionalidad: 0,
      demostracion: 0,
      trabajo_equipo: 0,
      documentacion: 0,
      respuestas_jurado: 0
    };

    // Generar botones dinámicamente
    document.querySelectorAll('.botones-nivel').forEach(container => {
      const field = container.dataset.field;
      for (let i = 1; i <= 4; i++) {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn-nivel';
        btn.dataset.value = i;
        btn.textContent = `${i} (${niveles[i-1]})`;
        btn.addEventListener('click', () => {
          document.querySelectorAll(`[data-field="${field}"] .btn-nivel`).forEach(b => b.classList.remove('activo'));
          btn.classList.add('activo');
          document.querySelector(`input[name="${field}"]`).value = i;
          puntajes[field] = i;
          calcularTotal();
          verificarListo();
        });
        container.appendChild(btn);
      }
    });

    function calcularTotal() {
      let total = 0;
      for (let key in puntajes) {
        total += puntajes[key] * factores[key];
      }
      document.getElementById('total').textContent = total.toFixed(1);
    }

    function verificarListo() {
      const todos = Object.values(puntajes).every(v => v > 0);
      document.getElementById('guardar').disabled = !todos;
    }
  </script>
</body>
</html>