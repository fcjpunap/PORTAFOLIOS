<?php
// generar_ejemplos.php (Versión con Líderes de Grupo)
require_once 'config/conexion.php';

try {
    // 1. AUTO-REPARACIÓN
    try { $pdo->exec("ALTER TABLE envios_fichas ADD COLUMN fecha_envio DATETIME DEFAULT CURRENT_TIMESTAMP"); } catch (PDOException $e) {}

    // 2. LIMPIEZA DE DATOS FANTASMA
    $pdo->exec("DELETE FROM envio_integrantes");
    $pdo->exec("DELETE FROM envios_fichas");
    $pdo->exec("DELETE FROM actividades_fichas");

    // 3. OBTENER O CREAR UN DOCENTE
    $stmtDocente = $pdo->query("SELECT id FROM usuarios WHERE rol = 'docente' LIMIT 1");
    $docenteRow = $stmtDocente->fetch(PDO::FETCH_ASSOC);

    if ($docenteRow) {
        $docente_id = $docenteRow['id'];
    } else {
        $hash_docente = password_hash('profesor2026', PASSWORD_DEFAULT);
        $pdo->exec("INSERT INTO usuarios (rol, nombres, apellidos, email, password, estado) VALUES ('docente', 'Carlos', 'Cáceres', 'ccaceres@unap.edu.pe', '$hash_docente', 1)");
        $docente_id = $pdo->lastInsertId();
    }

    // 4. OBTENER O CREAR EL CURSO PADRE
    $stmtCurso = $pdo->query("SELECT id FROM cursos LIMIT 1");
    $cursoRow = $stmtCurso->fetch(PDO::FETCH_ASSOC);

    if ($cursoRow) {
        $curso_id = $cursoRow['id'];
    } else {
        try {
            $pdo->exec("INSERT INTO cursos (nombre_curso, nombre, semestre, docente_id) VALUES ('Derecho Penal Especial III', 'Derecho Penal Especial III', '2026-1', $docente_id)");
        } catch (PDOException $e) {
            $pdo->exec("INSERT INTO cursos (nombre_curso, semestre, docente_id) VALUES ('Derecho Penal Especial III', '2026-1', $docente_id)");
        }
        $curso_id = $pdo->lastInsertId();
    }

    // 5. CREACIÓN DE ALUMNOS
    $estudiantes = [
        ['Juan', 'Pérez Mamani', '123456', 'jperez@unap.edu.pe'],
        ['Ana', 'Gómez Quispe', '123457', 'agomez@unap.edu.pe'],
        ['Luis', 'Condori Huanca', '123458', 'lcondori@unap.edu.pe']
    ];
    $hash_alumno = password_hash('estudiante2026', PASSWORD_DEFAULT);
    $stmtUser = $pdo->prepare("INSERT IGNORE INTO usuarios (rol, nombres, apellidos, codigo_estudiante, email, password, estado) VALUES ('estudiante', ?, ?, ?, ?, ?, 1)");
    foreach ($estudiantes as $e) { $stmtUser->execute([$e[0], $e[1], $e[2], $e[3], $hash_alumno]); }

    // ¡NUEVO PASO! Capturamos los IDs reales de los alumnos para asignarlos como líderes
    $stmtIds = $pdo->query("SELECT id, email FROM usuarios WHERE email IN ('jperez@unap.edu.pe', 'lcondori@unap.edu.pe')");
    $ids_alumnos = [];
    while ($row = $stmtIds->fetch(PDO::FETCH_ASSOC)) {
        $ids_alumnos[$row['email']] = $row['id'];
    }
    // Asignamos a Juan y Luis como líderes
    $lider1_id = $ids_alumnos['jperez@unap.edu.pe'] ?? 1; 
    $lider2_id = $ids_alumnos['lcondori@unap.edu.pe'] ?? 2;

    // 6. CREAR ACTIVIDADES
    $stmtAct = $pdo->prepare("INSERT INTO actividades_fichas (curso_id, titulo_caso, descripcion, fecha_inicio, fecha_limite, fecha_cierre) VALUES (?, ?, ?, ?, ?, ?)");
    $stmtAct->execute([$curso_id, 'Semana 1: Delito de Rebelión (Art. 346)', 'Análisis del caso Asonada en el sur', '2026-03-10 08:00:00', '2026-03-20 23:59:00', '2026-03-20 23:59:00']);
    $act1_id = $pdo->lastInsertId(); 

    $stmtAct->execute([$curso_id, 'Semana 2: Sedición y Motín', 'Caso hipotético: Toma de instalaciones estratégicas', '2026-03-15 08:00:00', '2026-03-27 23:59:00', '2026-03-27 23:59:00']);
    $act2_id = $pdo->lastInsertId(); 

    // 7. CREAR ENVÍOS (¡AHORA CON lider_id INCLUIDO!)
    // Añadimos lider_id a la consulta (ahora son 9 signos de interrogación)
    $stmtEnvio = $pdo->prepare("INSERT INTO envios_fichas (actividad_id, lider_id, factum, tipicidad, dogmatica, jurisprudencia, fallo, estado, calificacion) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    $texto_factum = "El día 15 de marzo, un grupo de ciudadanos organizados tomó control de las vías principales...";
    $texto_tipicidad = "El comportamiento se subsume en el tipo penal de Rebelión (Art. 346 CP)...";
    $texto_dogmatica = "Existe coautoría funcional. Según la teoría del dominio del hecho (Roxin)...";
    $texto_jurisprudencia = "Se aplica el criterio vinculante de la Casación N° 123-2022/Puno...";

    // Pasamos el $lider1_id
    $stmtEnvio->execute([$act1_id, $lider1_id, $texto_factum, $texto_tipicidad, $texto_dogmatica, $texto_jurisprudencia, 'Culpables como coautores no ejecutivos', 'Revisado', 18]);
    $envio1_id = $pdo->lastInsertId();

    // Pasamos el $lider2_id
    $stmtEnvio->execute([$act2_id, $lider2_id, 'Hechos del segundo caso...', 'Tipicidad...', 'Dogmática...', 'Jurisprudencia...', 'Absolución por atipicidad', 'Revisado', 16]);
    $envio2_id = $pdo->lastInsertId();

    // 8. ASIGNAR INTEGRANTES
    if ($envio1_id && $envio2_id) {
        $pdo->exec("INSERT INTO envio_integrantes (envio_id, estudiante_id) SELECT $envio1_id, id FROM usuarios WHERE email IN ('jperez@unap.edu.pe', 'agomez@unap.edu.pe')");
        $pdo->exec("INSERT INTO envio_integrantes (envio_id, estudiante_id) SELECT $envio2_id, id FROM usuarios WHERE email IN ('lcondori@unap.edu.pe', 'agomez@unap.edu.pe')");
    }

    echo "<div style='font-family: Arial; padding: 20px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 5px; text-align: center;'>";
    echo "<h3 style='color: #155724;'>¡Líderes asignados! Misión cumplida 🏆</h3>";
    echo "<p>La tabla envios_fichas ya tiene todo lo que nos pedía.</p>";
    echo "<a href='index.php' style='display: inline-block; padding: 10px 15px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; font-weight: bold;'>Ir al Dashboard Dinámico</a>";
    echo "</div>";

} catch (PDOException $e) {
    echo "<div style='font-family: Arial; padding: 20px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 5px;'>";
    echo "<h3 style='color: #721c24;'>Error:</h3><b>" . $e->getMessage() . "</b></div>";
}
?>