-- Tabla: equipos
CREATE TABLE equipos (
    id_equipo INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    integrantes TEXT -- o relación 1:N con tabla 'estudiantes' si se requiere
);

-- Tabla: evaluadores
CREATE TABLE evaluadores (
    id_evaluador INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL
);

-- Tabla: evaluaciones
CREATE TABLE evaluaciones (
    id_evaluacion INT AUTO_INCREMENT PRIMARY KEY,
    id_equipo INT NOT NULL,
    id_evaluador INT NOT NULL,
    fecha_eval DATETIME DEFAULT CURRENT_TIMESTAMP,
    problema_valor TINYINT CHECK (problema_valor BETWEEN 1 AND 4),
    integracion_contenidos TINYINT CHECK (integracion_contenidos BETWEEN 1 AND 4),
    funcionalidad TINYINT CHECK (funcionalidad BETWEEN 1 AND 4),
    demostracion TINYINT CHECK (demostracion BETWEEN 1 AND 4),
    trabajo_equipo TINYINT CHECK (trabajo_equipo BETWEEN 1 AND 4),
    documentacion TINYINT CHECK (documentacion BETWEEN 1 AND 4),
    respuestas_jurado TINYINT CHECK (respuestas_jurado BETWEEN 1 AND 4),
    observaciones TEXT,
    FOREIGN KEY (id_equipo) REFERENCES equipos(id_equipo),
    FOREIGN KEY (id_evaluador) REFERENCES evaluadores(id_evaluador)
);