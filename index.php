<?php
require 'config.php';

// Cargar equipos y evaluadores
$stmt = $pdo->query("SELECT id_equipo, nombre FROM equipos ORDER BY nombre");
$equipos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->query("SELECT id_evaluador, nombre FROM evaluadores ORDER BY nombre");
$evaluadores = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Evaluación Proyecto Integrador</title>
  <link rel="stylesheet" href="estilo.css">
</head>
<body>
  <div class="container">
    <h1>Seleccionar Equipo y Evaluador</h1>
    <form action="evaluar.php" method="get">
      <label for="equipo">Equipo:</label>
      <select name="equipo_id" required>
        <option value="">-- Elija un equipo --</option>
        <?php foreach ($equipos as $eq): ?>
          <option value="<?= $eq['id_equipo'] ?>"><?= htmlspecialchars($eq['nombre']) ?></option>
        <?php endforeach; ?>
      </select>

      <label for="evaluador">Evaluador:</label>
      <select name="evaluador_id" required>
        <option value="">-- Elija un evaluador --</option>
        <?php foreach ($evaluadores as $ev): ?>
          <option value="<?= $ev['id_evaluador'] ?>"><?= htmlspecialchars($ev['nombre']) ?></option>
        <?php endforeach; ?>
      </select>

      <button type="submit">Iniciar evaluación</button>
    </form>
  </div>
</body>
</html>