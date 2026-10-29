CREATE TABLE IF NOT EXISTS usuarios (
    id         SERIAL PRIMARY KEY,
    nombre     VARCHAR(80)  NOT NULL,
    apellidos  VARCHAR(120) NOT NULL,
    correo     VARCHAR(150) NOT NULL UNIQUE,
    password   VARCHAR(255) NOT NULL,
    rol        VARCHAR(10)  NOT NULL DEFAULT 'alumno',
    estado     VARCHAR(10)  NOT NULL DEFAULT 'activo',
    creado_en  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS estado VARCHAR(10) NOT NULL DEFAULT 'activo';

ALTER TABLE usuarios DROP CONSTRAINT IF EXISTS usuarios_rol_check;
ALTER TABLE usuarios ADD CONSTRAINT usuarios_rol_check
    CHECK (rol IN ('alumno', 'ponente', 'director', 'admin'));

ALTER TABLE usuarios DROP CONSTRAINT IF EXISTS usuarios_estado_check;
ALTER TABLE usuarios ADD CONSTRAINT usuarios_estado_check
    CHECK (estado IN ('activo', 'pendiente', 'rechazado'));

CREATE TABLE IF NOT EXISTS cursos (
    id           SERIAL PRIMARY KEY,
    titulo       VARCHAR(150) NOT NULL,
    descripcion  TEXT,
    categoria    VARCHAR(40)  NOT NULL,
    ponente_id   INTEGER REFERENCES usuarios(id) ON DELETE SET NULL,
    creado_en    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS inscripciones (
    alumno_id    INTEGER NOT NULL REFERENCES usuarios(id) ON DELETE CASCADE,
    curso_id     INTEGER NOT NULL REFERENCES cursos(id)   ON DELETE CASCADE,
    inscrito_en  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (alumno_id, curso_id)
);

CREATE TABLE IF NOT EXISTS cambios (
    id           SERIAL PRIMARY KEY,
    usuario_id   INTEGER REFERENCES usuarios(id) ON DELETE SET NULL,
    autor        VARCHAR(200) NOT NULL,
    rol          VARCHAR(10)  NOT NULL,
    descripcion  TEXT NOT NULL,
    creado_en    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

ALTER TABLE cursos ADD COLUMN IF NOT EXISTS eliminado_en  TIMESTAMP;
ALTER TABLE cursos ADD COLUMN IF NOT EXISTS eliminado_por VARCHAR(200);

ALTER TABLE cambios ADD COLUMN IF NOT EXISTS curso_id       INTEGER REFERENCES cursos(id) ON DELETE SET NULL;
ALTER TABLE cambios ADD COLUMN IF NOT EXISTS valor_anterior TEXT;
ALTER TABLE cambios ADD COLUMN IF NOT EXISTS restaurado     BOOLEAN NOT NULL DEFAULT false;

INSERT INTO usuarios (nombre, apellidos, correo, password, rol, estado)
VALUES ('Super', 'Admin', 'admin@gmail.com',
        '$2y$12$iBYRNwJX2ZaLIMqlpp9n3uUkQqqxbAPl3RzImvIHkBhDrAnw4A9Qu',
        'admin', 'activo')
ON CONFLICT (correo) DO UPDATE
    SET password = EXCLUDED.password, rol = 'admin', estado = 'activo';

CREATE TABLE IF NOT EXISTS bloques (
    id         SERIAL PRIMARY KEY,
    curso_id   INTEGER NOT NULL REFERENCES cursos(id) ON DELETE CASCADE,
    titulo     VARCHAR(150) NOT NULL,
    orden      INTEGER NOT NULL DEFAULT 0,
    creado_en  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS tareas (
    id            SERIAL PRIMARY KEY,
    bloque_id     INTEGER NOT NULL REFERENCES bloques(id) ON DELETE CASCADE,
    titulo        VARCHAR(150) NOT NULL,
    instrucciones TEXT,
    creado_en     TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
ALTER TABLE tareas DROP COLUMN IF EXISTS archivo;
ALTER TABLE tareas DROP COLUMN IF EXISTS archivo_nombre;

CREATE TABLE IF NOT EXISTS entregas (
    alumno_id     INTEGER NOT NULL REFERENCES usuarios(id) ON DELETE CASCADE,
    tarea_id      INTEGER NOT NULL REFERENCES tareas(id)   ON DELETE CASCADE,
    respuesta     TEXT NOT NULL,
    entregado_en  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (alumno_id, tarea_id)
);

CREATE TABLE IF NOT EXISTS examenes (
    curso_id           INTEGER PRIMARY KEY REFERENCES cursos(id) ON DELETE CASCADE,
    calificacion_minima INTEGER NOT NULL DEFAULT 70 CHECK (calificacion_minima BETWEEN 1 AND 100)
);

CREATE TABLE IF NOT EXISTS preguntas (
    id        SERIAL PRIMARY KEY,
    curso_id  INTEGER NOT NULL REFERENCES examenes(curso_id) ON DELETE CASCADE,
    texto     TEXT NOT NULL,
    opcion_a  VARCHAR(255) NOT NULL,
    opcion_b  VARCHAR(255) NOT NULL,
    opcion_c  VARCHAR(255),
    opcion_d  VARCHAR(255),
    correcta  CHAR(1) NOT NULL CHECK (correcta IN ('a', 'b', 'c', 'd'))
);

CREATE TABLE IF NOT EXISTS intentos_examen (
    id           SERIAL PRIMARY KEY,
    alumno_id    INTEGER NOT NULL REFERENCES usuarios(id) ON DELETE CASCADE,
    curso_id     INTEGER NOT NULL REFERENCES cursos(id)   ON DELETE CASCADE,
    calificacion INTEGER NOT NULL,
    aprobado     BOOLEAN NOT NULL,
    creado_en    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

ALTER TABLE inscripciones ADD COLUMN IF NOT EXISTS completado_en TIMESTAMP;

CREATE TABLE IF NOT EXISTS comentarios_curso (
    id         SERIAL PRIMARY KEY,
    curso_id   INTEGER NOT NULL REFERENCES cursos(id) ON DELETE CASCADE,
    autor_id   INTEGER REFERENCES usuarios(id) ON DELETE SET NULL,
    autor      VARCHAR(200) NOT NULL,
    rol        VARCHAR(10)  NOT NULL,
    texto      TEXT NOT NULL,
    atendido   BOOLEAN NOT NULL DEFAULT false,
    creado_en  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
