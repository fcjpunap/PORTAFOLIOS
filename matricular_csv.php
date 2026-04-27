<?php
// matricular_csv.php - Importador AJAX con Detección Dinámica de Columnas
session_set_cookie_params(14400);
ini_set("session.gc_maxlifetime", 14400);
session_start();

if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['rol'], ['docente', 'admin'])) {
    header('Location: login.php');
    exit;
}

require_once 'config/conexion.php';
$usuario_id = $_SESSION['usuario_id'];
$rol = $_SESSION['rol'];
$es_admin = ($rol === 'admin');

// ==============================================================================
// BLOQUE AJAX: PROCESAMIENTO EN SEGUNDO PLANO Y CONSULTA DE PROGRESO
// ==============================================================================
if (isset($_GET['action'])) {
    
    // 1. ENDPOINT: Consultar el porcentaje de progreso actual
    if ($_GET['action'] === 'status') {
        $id = preg_replace('/[^a-zA-Z0-9_]/', '', $_GET['id']);
        $file = sys_get_temp_dir() . '/progreso_matricula_' . $id . '.txt';
        $progress = file_exists($file) ? (int)file_get_contents($file) : 0;
        header('Content-Type: application/json');
        echo json_encode(['progress' => $progress]);
        exit;
    }

    // 2. ENDPOINT: Procesar el archivo subido
    if ($_GET['action'] === 'process' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json');
        
        $curso_id = $_POST['curso_id'] ?? '';
        $upload_id = preg_replace('/[^a-zA-Z0-9_]/', '', $_POST['upload_id']);
        $progress_file = sys_get_temp_dir() . '/progreso_matricula_' . $upload_id . '.txt';
        
        if (!isset($_FILES['archivo_excel']) || $_FILES['archivo_excel']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['status' => 'error', 'message' => 'Error al subir el archivo al servidor.']); exit;
        }
        
        // Validar seguridad del curso
        if ($es_admin) {
            $stmt_check = $pdo->prepare("SELECT id FROM cursos WHERE id = ?");
            $stmt_check->execute([$curso_id]);
        } else {
            $stmt_check = $pdo->prepare("SELECT id FROM cursos WHERE id = ? AND docente_id = ?");
            $stmt_check->execute([$curso_id, $usuario_id]);
        }
        
        if (!$stmt_check->fetch()) {
            echo json_encode(['status' => 'error', 'message' => 'Acceso denegado al curso seleccionado.']); exit;
        }

        $archivo = $_FILES['archivo_excel']['tmp_name'];
        $extension = strtolower(pathinfo($_FILES['archivo_excel']['name'], PATHINFO_EXTENSION));
        $datos_extraidos = [];

        // Extraer datos (XLSX o CSV)
        if ($extension === 'xlsx') {
            $zip = new ZipArchive();
            if ($zip->open($archivo) === true) {
                $sharedStrings = [];
                if (($indexData = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
                    $xml = simplexml_load_string($indexData);
                    if ($xml && isset($xml->si)) {
                        foreach ($xml->si as $val) {
                            $text = isset($val->t) ? (string)$val->t : '';
                            if (isset($val->r)) { foreach ($val->r as $r) { $text .= (string)$r->t; } }
                            $sharedStrings[] = $text;
                        }
                    }
                }
                $sheetData = $zip->getFromName('xl/worksheets/sheet1.xml');
                if ($sheetData !== false) {
                    $xml = simplexml_load_string($sheetData);
                    if ($xml && isset($xml->sheetData->row)) {
                        foreach ($xml->sheetData->row as $row) {
                            $rowData = [];
                            foreach ($row->c as $c) {
                                $val = (string)$c->v;
                                if (isset($c['t']) && (string)$c['t'] == 's') { $val = $sharedStrings[(int)$val] ?? ''; }
                                $coord = (string)$c['r'];
                                preg_match('/([A-Z]+)(\d+)/', $coord, $matches);
                                if ($matches) {
                                    $colStr = $matches[1]; $colIndex = 0;
                                    for ($i = 0; $i < strlen($colStr); $i++) { $colIndex = $colIndex * 26 + (ord($colStr[$i]) - 64); }
                                    $rowData[$colIndex - 1] = $val;
                                }
                            }
                            if (!empty($rowData)) {
                                $maxKey = max(array_keys($rowData)); $filledRow = [];
                                for ($i = 0; $i <= $maxKey; $i++) { $filledRow[] = $rowData[$i] ?? ''; }
                                $datos_extraidos[] = $filledRow;
                            }
                        }
                    }
                }
                $zip->close();
            } else {
                echo json_encode(['status' => 'error', 'message' => 'El archivo Excel parece estar corrupto.']); exit;
            }
        } elseif ($extension === 'csv') {
            $handle = fopen($archivo, "r");
            while (($fila = fgetcsv($handle, 1000, ",")) !== FALSE) { $datos_extraidos[] = $fila; }
            fclose($handle);
        }

        // ==============================================================================
        // NUEVA LÓGICA: DETECCIÓN DINÁMICA DE CABECERAS
        // ==============================================================================
        $filas_validas = [];
        $inicio_datos = false;
        $idx_codigo = -1;
        $idx_nombre = -1;
        $idx_correo = -1;

        foreach ($datos_extraidos as $fila) {
            if (!$inicio_datos) {
                // Buscar en todas las celdas de la fila
                foreach ($fila as $index => $celda) {
                    $val = trim($celda);
                    if (strcasecmp($val, 'Código') === 0 || strcasecmp($val, 'Codigo') === 0) {
                        $inicio_datos = true;
                        $idx_codigo = $index;
                    } elseif (strcasecmp($val, 'Nombre Completo') === 0 || stripos($val, 'Nombres') !== false) {
                        $idx_nombre = $index;
                    } elseif (stripos($val, 'Correo') !== false || stripos($val, 'Email') !== false) {
                        $idx_correo = $index;
                    }
                }
                
                // Si encontramos "Código", definimos las columnas faltantes por defecto basándonos en tu archivo
                if ($inicio_datos) {
                    if ($idx_nombre === -1) $idx_nombre = $idx_codigo + 1; // Generalmente el nombre está al lado del código
                    if ($idx_correo === -1) $idx_correo = $idx_codigo + 3; // En la UNAP el correo suele estar 3 posiciones a la derecha
                }
                continue;
            }
            
            // Si ya estamos leyendo los datos de los alumnos
            if ($inicio_datos && $idx_codigo !== -1) {
                $codigo_val = trim($fila[$idx_codigo] ?? '');
                $nombre_val = trim($fila[$idx_nombre] ?? '');
                
                if (!empty($codigo_val) && !empty($nombre_val)) {
                    $filas_validas[] = [
                        'codigo' => $codigo_val,
                        'nombre_completo' => $nombre_val,
                        'email' => trim($fila[$idx_correo] ?? '')
                    ];
                }
            }
        }

        $total_filas = count($filas_validas);
        if ($total_filas === 0) {
            echo json_encode(['status' => 'error', 'message' => 'No se encontraron registros. Asegúrate de que exista una columna llamada "Código" o "Codigo".']); exit;
        }

        // Empezar a procesar e insertar en la BD
        file_put_contents($progress_file, "0"); // Inicializar progreso
        $nuevos = 0; $matriculados = 0; $procesados = 0;
        
        try {
            $pdo->beginTransaction();
            foreach ($filas_validas as $datos) {
                $codigo = $datos['codigo'];
                $nombre_completo = $datos['nombre_completo'];
                $email = $datos['email'];

                // Separar Apellidos y Nombres (Detecta si hay coma o solo espacios)
                $apellidos = ''; $nombres = '';
                if (strpos($nombre_completo, ',') !== false) {
                    $partes = explode(',', $nombre_completo);
                    $apellidos = trim($partes[0]); $nombres = trim($partes[1]);
                } else {
                    $partes = explode(' ', $nombre_completo);
                    if (count($partes) >= 3) {
                        $apellidos = $partes[0] . ' ' . $partes[1];
                        $nombres = implode(' ', array_slice($partes, 2));
                    } else {
                        $apellidos = $partes[0] ?? ''; $nombres = $partes[1] ?? '';
                    }
                }

                // Insertar/Buscar Usuario
                $stmtCheck = $pdo->prepare("SELECT id FROM usuarios WHERE email = ? OR codigo_estudiante = ?");
                $stmtCheck->execute([$email, $codigo]);
                $usuario = $stmtCheck->fetch(PDO::FETCH_ASSOC);

                if ($usuario) {
                    $estudiante_id = $usuario['id'];
                } else {
                    $password_hash = password_hash($codigo, PASSWORD_DEFAULT);
                    $stmtInsertUser = $pdo->prepare("INSERT INTO usuarios (rol, nombres, apellidos, email, password, codigo_estudiante) VALUES ('estudiante', ?, ?, ?, ?, ?)");
                    $stmtInsertUser->execute([$nombres, $apellidos, $email, $password_hash, $codigo]);
                    $estudiante_id = $pdo->lastInsertId();
                    $nuevos++;
                }

                // Matricular
                $stmtCheckMat = $pdo->prepare("SELECT 1 FROM matriculas WHERE curso_id = ? AND estudiante_id = ?");
                $stmtCheckMat->execute([$curso_id, $estudiante_id]);
                if (!$stmtCheckMat->fetch()) {
                    $stmtInsertMat = $pdo->prepare("INSERT INTO matriculas (curso_id, estudiante_id) VALUES (?, ?)");
                    $stmtInsertMat->execute([$curso_id, $estudiante_id]);
                    $matriculados++;
                }

                // Actualizar barra de progreso (por lotes para eficiencia)
                $procesados++;
                if ($procesados % 3 === 0 || $procesados === $total_filas) { 
                    file_put_contents($progress_file, round(($procesados / $total_filas) * 100));
                }
            }
            $pdo->commit();
            @unlink($progress_file); // Limpiar archivo temporal

            echo json_encode([
                'status' => 'success', 
                'message' => "¡Completado! Se leyeron $total_filas alumnos. $matriculados fueron matriculados ($nuevos cuentas nuevas creadas)."
            ]);
        } catch (Exception $e) {
            $pdo->rollBack();
            @unlink($progress_file);
            echo json_encode(['status' => 'error', 'message' => 'Error de Base de Datos: ' . $e->getMessage()]);
        }
        exit;
    }
}
// ==============================================================================
// RENDERIZADO DE LA VISTA HTML
// ==============================================================================

// Obtener los cursos para el select
if ($es_admin) {
    $stmt_cursos = $pdo->query("SELECT id, nombre_curso FROM cursos ORDER BY nombre_curso ASC");
    $cursos = $stmt_cursos->fetchAll(PDO::FETCH_ASSOC);
} else {
    $stmt_cursos = $pdo->prepare("SELECT id, nombre_curso FROM cursos WHERE docente_id = ?");
    $stmt_cursos->execute([$usuario_id]);
    $cursos = $stmt_cursos->fetchAll(PDO::FETCH_ASSOC);
}

$btn_volver = $es_admin ? "dashboard_admin.php" : "dashboard_docente.php";
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Importar Matrículas | UNAP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style> 
        body { background-color: #f8f9fa; } 
        .bg-unap { background-color: #0b2e59; } 
        .progress-container { display: none; }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark bg-unap shadow-sm">
        <div class="container"><a class="navbar-brand" href="<?= $btn_volver ?>"><i class="fas fa-arrow-left me-2"></i> Volver al panel</a></div>
    </nav>
    <main class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                
                <div id="alertContainer"></div>
                
                <div class="card shadow border-0 border-top border-4 border-success mb-4">
                    <div class="card-header bg-white py-3">
                        <h4 class="mb-0 text-success fw-bold"><i class="fas fa-file-excel me-2"></i> Importar Lista de Alumnos</h4>
                    </div>
                    <div class="card-body p-4 bg-light">
                        
                        <div class="alert alert-secondary small mb-4 border-0 shadow-sm text-dark">
                            <h6 class="fw-bold"><i class="fas fa-magic text-primary me-1"></i> Detección Inteligente Activada:</h6>
                            <p class="mb-2">Sube directamente tu archivo. El sistema escaneará automáticamente el documento buscando las columnas obligatorias sin importar en qué orden estén:</p>
                            <ul class="mb-2 list-group list-group-flush border rounded">
                                <li class="list-group-item bg-transparent py-1"><i class="fas fa-hashtag text-muted me-2"></i> <strong>Código</strong> (O "Codigo")</li>
                                <li class="list-group-item bg-transparent py-1"><i class="fas fa-user text-muted me-2"></i> <strong>Nombre Completo</strong> (O "Nombres")</li>
                                <li class="list-group-item bg-transparent py-1"><i class="fas fa-envelope text-muted me-2"></i> <strong>Correo</strong> (O "Email")</li>
                            </ul>
                            <span class="text-muted d-block mt-2"><i class="fas fa-lightbulb text-warning me-1"></i> Compatible con archivos <strong>.xlsx</strong> o <strong>.csv</strong> de la plataforma de la UNAP.</span>
                        </div>

                        <form id="formImportar" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label class="fw-bold text-secondary mb-2">Selecciona el Curso a Matricular</label>
                                <select class="form-select" name="curso_id" required>
                                    <option value="">-- Elige el curso --</option>
                                    <?php foreach($cursos as $c): ?>
                                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nombre_curso']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-4">
                                <label class="fw-bold text-secondary mb-2">Archivo (.xlsx o .csv)</label>
                                <input type="file" class="form-control" name="archivo_excel" accept=".csv, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, application/vnd.ms-excel" required>
                            </div>

                            <div id="progressContainer" class="progress-container mb-4">
                                <label class="small fw-bold text-muted mb-1" id="progressText">Analizando el archivo e importando datos...</label>
                                <div class="progress" style="height: 25px;">
                                    <div id="progressBar" class="progress-bar progress-bar-striped progress-bar-animated bg-success fw-bold fs-6" role="progressbar" style="width: 0%;">0%</div>
                                </div>
                            </div>

                            <button type="submit" id="btnSubmit" class="btn btn-success btn-lg w-100 fw-bold shadow-sm"><i class="fas fa-upload me-2"></i> Procesar y Matricular</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        document.getElementById('formImportar').addEventListener('submit', function(e) {
            e.preventDefault();
            
            let form = this;
            let formData = new FormData(form);
            let btnSubmit = document.getElementById('btnSubmit');
            let progressContainer = document.getElementById('progressContainer');
            let progressBar = document.getElementById('progressBar');
            let alertContainer = document.getElementById('alertContainer');
            
            let uploadId = 'up_' + Date.now();
            formData.append('upload_id', uploadId);

            alertContainer.innerHTML = '';
            btnSubmit.disabled = true;
            btnSubmit.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Por favor espera...';
            progressContainer.style.display = 'block';
            progressBar.style.width = '0%';
            progressBar.innerHTML = '0%';

            let statusInterval = setInterval(() => {
                fetch('matricular_csv.php?action=status&id=' + uploadId)
                .then(response => response.json())
                .then(data => {
                    let porcentaje = data.progress || 0;
                    progressBar.style.width = porcentaje + '%';
                    progressBar.innerHTML = porcentaje + '%';
                });
            }, 500);

            fetch('matricular_csv.php?action=process', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                clearInterval(statusInterval); 
                progressBar.style.width = '100%';
                progressBar.innerHTML = '100%';
                
                setTimeout(() => {
                    progressContainer.style.display = 'none';
                    btnSubmit.disabled = false;
                    btnSubmit.innerHTML = '<i class="fas fa-upload me-2"></i> Procesar y Matricular';
                    progressBar.style.width = '0%';

                    let alertType = data.status === 'success' ? 'success' : 'danger';
                    let icon = data.status === 'success' ? 'check-circle' : 'exclamation-triangle';
                    alertContainer.innerHTML = `
                        <div class="alert alert-${alertType} shadow-sm border-0 border-start border-4 border-${alertType}">
                            <i class="fas fa-${icon} me-2"></i> ${data.message}
                        </div>`;
                    
                    if (data.status === 'success') { form.reset(); }
                }, 800); 
            })
            .catch(error => {
                clearInterval(statusInterval);
                progressContainer.style.display = 'none';
                btnSubmit.disabled = false;
                btnSubmit.innerHTML = '<i class="fas fa-upload me-2"></i> Procesar y Matricular';
                alertContainer.innerHTML = `<div class="alert alert-danger shadow-sm"><i class="fas fa-wifi me-2"></i> Error de conexión con el servidor. Verifica el formato del archivo.</div>`;
            });
        });
    </script>
</body>
</html>