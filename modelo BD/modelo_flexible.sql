-- ==================================================================
-- MODELO FLEXIBLE DE EVALUACIONES
-- WebApp-Evaluator v2.0
-- Fecha: 2025-11-12
-- ==================================================================

-- Se mantienen las tablas originales con pequeñas modificaciones
-- Tabla: equipos (sin cambios)
CREATE TABLE IF NOT EXISTS equipos (
    id_equipo INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    integrantes TEXT
);

-- Tabla: evaluadores (sin cambios)
CREATE TABLE IF NOT EXISTS evaluadores (
    id_evaluador INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL
);

-- ==================================================================
-- NUEVAS TABLAS PARA FLEXIBILIDAD
-- ==================================================================

-- Tabla: criterios_evaluacion
-- Almacena los criterios dinámicos que se pueden usar en evaluaciones
CREATE TABLE criterios_evaluacion (
    id_criterio INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    descripcion TEXT,
    puntaje_minimo INT NOT NULL DEFAULT 1,
    puntaje_maximo INT NOT NULL DEFAULT 4,
    activo BOOLEAN DEFAULT TRUE,
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_puntajes CHECK (puntaje_minimo < puntaje_maximo)
);

-- Tabla: instancias_evaluacion
-- Representa una sesión o evento de evaluación con criterios específicos
CREATE TABLE instancias_evaluacion (
    id_instancia INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL,
    descripcion TEXT,
    fecha_inicio DATETIME DEFAULT CURRENT_TIMESTAMP,
    fecha_fin DATETIME NULL,
    activa BOOLEAN DEFAULT TRUE,
    creado_por INT NULL,
    FOREIGN KEY (creado_por) REFERENCES evaluadores(id_evaluador)
);

-- Tabla: instancia_criterios
-- Relaciona qué criterios se usan en cada instancia de evaluación
CREATE TABLE instancia_criterios (
    id_instancia_criterio INT AUTO_INCREMENT PRIMARY KEY,
    id_instancia INT NOT NULL,
    id_criterio INT NOT NULL,
    peso_porcentual DECIMAL(5,2) DEFAULT 100.00,
    orden INT DEFAULT 0,
    FOREIGN KEY (id_instancia) REFERENCES instancias_evaluacion(id_instancia) ON DELETE CASCADE,
    FOREIGN KEY (id_criterio) REFERENCES criterios_evaluacion(id_criterio),
    UNIQUE KEY uq_instancia_criterio (id_instancia, id_criterio)
);

-- Tabla: evaluaciones (REFACTORIZADA)
-- Ya no contiene los puntajes hardcodeados, solo la relación principal
CREATE TABLE evaluaciones (
    id_evaluacion INT AUTO_INCREMENT PRIMARY KEY,
    id_instancia INT NOT NULL,
    id_equipo INT NOT NULL,
    id_evaluador INT NOT NULL,
    fecha_eval DATETIME DEFAULT CURRENT_TIMESTAMP,
    observaciones TEXT,
    estado ENUM('borrador', 'enviada', 'revisada') DEFAULT 'borrador',
    FOREIGN KEY (id_instancia) REFERENCES instancias_evaluacion(id_instancia),
    FOREIGN KEY (id_equipo) REFERENCES equipos(id_equipo),
    FOREIGN KEY (id_evaluador) REFERENCES evaluadores(id_evaluador),
    UNIQUE KEY uq_evaluacion_unica (id_instancia, id_equipo, id_evaluador)
);

-- Tabla: detalles_evaluacion
-- Almacena los puntajes asignados para cada criterio en cada evaluación
CREATE TABLE detalles_evaluacion (
    id_detalle INT AUTO_INCREMENT PRIMARY KEY,
    id_evaluacion INT NOT NULL,
    id_criterio INT NOT NULL,
    puntaje INT NOT NULL,
    comentario TEXT,
    fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_evaluacion) REFERENCES evaluaciones(id_evaluacion) ON DELETE CASCADE,
    FOREIGN KEY (id_criterio) REFERENCES criterios_evaluacion(id_criterio),
    UNIQUE KEY uq_evaluacion_criterio (id_evaluacion, id_criterio)
);

-- ==================================================================
-- ÍNDICES PARA OPTIMIZACIÓN
-- ==================================================================

CREATE INDEX idx_evaluaciones_fecha ON evaluaciones(fecha_eval);
CREATE INDEX idx_evaluaciones_estado ON evaluaciones(estado);
CREATE INDEX idx_instancias_activa ON instancias_evaluacion(activa);
CREATE INDEX idx_detalles_fecha ON detalles_evaluacion(fecha_registro);

-- ==================================================================
-- DATOS DE EJEMPLO (MIGRACIÓN DESDE MODELO ANTERIOR)
-- ==================================================================

-- Insertar criterios que existían en el modelo original
INSERT INTO criterios_evaluacion (nombre, descripcion, puntaje_minimo, puntaje_maximo) VALUES
('Problema/Valor', 'Claridad del problema y valor de la solución propuesta', 1, 4),
('Integración de contenidos', 'Nivel de integración de los contenidos aprendidos', 1, 4),
('Funcionalidad', 'Funcionalidad y completitud de la solución', 1, 4),
('Demostración', 'Calidad de la demostración del proyecto', 1, 4),
('Trabajo en equipo', 'Evidencia de trabajo colaborativo', 1, 4),
('Documentación', 'Calidad de la documentación entregada', 1, 4),
('Respuestas al jurado', 'Calidad de respuestas a preguntas del jurado', 1, 4);

-- ==================================================================
-- VISTAS ÚTILES
-- ==================================================================

-- Vista: resumen de evaluaciones por equipo
CREATE OR REPLACE VIEW v_resumen_evaluaciones AS
SELECT 
    e.id_evaluacion,
    ie.nombre AS instancia,
    eq.nombre AS equipo,
    ev.nombre AS evaluador,
    e.fecha_eval,
    e.estado,
    COUNT(de.id_detalle) AS criterios_evaluados,
    AVG(de.puntaje) AS promedio_puntajes
FROM evaluaciones e
INNER JOIN instancias_evaluacion ie ON e.id_instancia = ie.id_instancia
INNER JOIN equipos eq ON e.id_equipo = eq.id_equipo
INNER JOIN evaluadores ev ON e.id_evaluador = ev.id_evaluador
LEFT JOIN detalles_evaluacion de ON e.id_evaluacion = de.id_evaluacion
GROUP BY e.id_evaluacion;

-- Vista: detalle completo de evaluación (para auditoría histórica)
CREATE OR REPLACE VIEW v_detalle_historico AS
SELECT 
    e.id_evaluacion,
    ie.nombre AS instancia,
    eq.nombre AS equipo,
    ev.nombre AS evaluador,
    e.fecha_eval,
    ce.nombre AS criterio,
    de.puntaje,
    de.comentario,
    de.fecha_registro,
    e.observaciones AS observaciones_generales
FROM evaluaciones e
INNER JOIN instancias_evaluacion ie ON e.id_instancia = ie.id_instancia
INNER JOIN equipos eq ON e.id_equipo = eq.id_equipo
INNER JOIN evaluadores ev ON e.id_evaluador = ev.id_evaluador
INNER JOIN detalles_evaluacion de ON e.id_evaluacion = de.id_evaluacion
INNER JOIN criterios_evaluacion ce ON de.id_criterio = ce.id_criterio
ORDER BY e.fecha_eval DESC, ce.nombre;

-- ==================================================================
-- FIN DEL SCRIPT
-- ==================================================================
