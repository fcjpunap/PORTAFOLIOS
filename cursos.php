<?php
// cursos.php - Gestión de Cursos
session_set_cookie_params(14400);
ini_set("session.gc_maxlifetime", 14400);
session_start();

// Validar Sesión Segura
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'config/conexion.php';

$mensaje = '';
$tipo_mensaje = '';

// --- 1. PROCESAR FORMULARIOS (CREAR, EDITAR, ELIMINAR) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    $accion = $_POST['accion'];

    if ($accion === 'guardar') {
        $id_curso = $_POST['id_curso'] ?? '';
        $nombre_curso = $_POST['nombre_curso'] ?? '';
        $semestre = $_POST['semestre'] ?? '';
        $docente_id = $_POST['docente_id'] ?? null;

        if (empty($id_curso)) {
            // CREAR NUEVO CURSO
            try {
                // Inyectamos en 'nombre_curso' y 'nombre' por la estructura de tu BD
                $stmt = $pdo->prepare("INSERT INTO cursos (nombre_curso, nombre, semestre, docente_id) VALUES (?, ?, ?, ?)");
                $stmt->execute([$nombre_curso, $nombre_curso, $semestre, $docente_id]);
                $mensaje = "¡Curso creado exitosamente!";
                $tipo_mensaje = "success";
            } catch (PDOException $e) {
                $mensaje = "Error al crear: " . $e->getMessage();
                $tipo_mensaje = "danger";
            }
        } else {
            // EDITAR CURSO EXISTENTE
            try {
                $stmt = $pdo->prepare("UPDATE cursos SET nombre_curso = ?, nombre = ?, semestre = ?, docente_id = ? WHERE id = ?");
                $stmt->execute([$nombre_curso, $nombre_curso, $semestre, $docente_id, $id_curso]);
                $mensaje = "¡Curso actualizado correctamente!";
                $tipo_mensaje = "success";
            } catch (PDOException $e) {
                $mensaje = "Error al actualizar: " . $e->getMessage();
                $tipo_mensaje = "danger";
            }
        }
    } elseif ($accion === 'eliminar') {
        // ELIMINAR CURSO
        $id_curso = $_POST['id_curso'];
        try {
            $stmt = $pdo->prepare("DELETE FROM cursos WHERE id = ?");
            $stmt->execute([$id_curso]);
            $mensaje = "Curso eliminado correctamente.";
            $tipo_mensaje = "success";
        } catch (PDOException $e) {
            // Si salta el error de Llave Foránea, mostramos un mensaje amigable
            $mensaje = "No se puede eliminar este curso porque ya tiene actividades o alumnos asignados.";
            $tipo_mensaje = "danger";
        }
    }
}

// --- 2. OBTENER DATOS PARA LA TABLA ---
$sql_cursos = "SELECT c.id, c.nombre_curso, c.semestre, u.nombres, u.apellidos 
               FROM cursos c 
               LEFT JOIN usuarios u ON c.docente_id = u.id 
               ORDER BY c.id DESC";
$lista_cursos = $pdo->query($sql_cursos)->fetchAll(PDO::FETCH_ASSOC);

// Lista de docentes para el selector del Modal
$docentes = $pdo->query("SELECT id, nombres, apellidos FROM usuarios WHERE rol = 'docente'")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Cursos | Portafolios UNAP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    <style>
        body { display: flex; flex-direction: column; min-height: 100vh; background-color: #f4f6f9; }
        main { flex: 1; }
    </style>
</head>
<body>

    <nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php"><i class="fas fa-balance-scale text-warning"></i> Portafolios UNAP</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item"><a class="nav-link" href="index.php"><i class="fas fa-chart-line"></i> Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link active" href="cursos.php"><i class="fas fa-book"></i> Cursos</a></li>
                    <li class="nav-item"><a class="nav-link" href="estudiantes.php"><i class="fas fa-user-graduate"></i> Estudiantes</a></li>
                    <li class="nav-item"><a class="nav-link" href="usuarios.php"><i class="fas fa-users-cog"></i> Usuarios</a></li>
                </ul>
                <div class="d-flex text-white align-items-center">
                    <span class="me-3"><i class="fas fa-user-tie"></i> <?= htmlspecialchars($_SESSION['nombre_completo'] ?? 'Docente') ?></span>
                    <a href="logout.php" class="btn btn-outline-danger btn-sm"><i class="fas fa-sign-out-alt"></i> Salir</a>
                </div>
            </div>
        </div>
    </nav>

    <main class="container py-4">
        
        <?php if ($mensaje): ?>
            <div class="alert alert-<?= $tipo_mensaje ?> alert-dismissible fade show shadow-sm" role="alert">
                <?= $tipo_mensaje === 'success' ? '<i class="fas fa-check-circle"></i>' : '<i class="fas fa-exclamation-triangle"></i>' ?> 
                <?= htmlspecialchars($mensaje) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="h4 text-secondary"><i class="fas fa-book-open"></i> Gestión de Cursos</h2>
            <button class="btn btn-primary shadow-sm" onclick="abrirModalCurso()">
                <i class="fas fa-plus-circle"></i> Nuevo Curso
            </button>
        </div>

        <div class="card shadow">
            <div class="card-body">
                <div class="table-responsive">
                    <table id="tablaCursos" class="table table-striped table-hover align-middle w-100">
                        <thead class="table-dark">
                            <tr>
                                <th>ID</th>
                                <th>Nombre del Curso</th>
                                <th>Semestre</th>
                                <th>Docente Asignado</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lista_cursos as $curso): ?>
                            <tr>
                                <td><?= $curso['id'] ?></td>
                                <td class="fw-bold"><?= htmlspecialchars($curso['nombre_curso']) ?></td>
                                <td><span class="badge bg-info text-dark"><?= htmlspecialchars($curso['semestre']) ?></span></td>
                                <td><?= htmlspecialchars($curso['nombres'] . ' ' . $curso['apellidos']) ?></td>
                                <td class="text-center">
                                    <button class="btn btn-sm btn-warning" title="Editar" 
                                            onclick="abrirModalCurso(<?= $curso['id'] ?>, '<?= htmlspecialchars($curso['nombre_curso'], ENT_QUOTES) ?>', '<?= htmlspecialchars($curso['semestre'], ENT_QUOTES) ?>')">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <form action="cursos.php" method="POST" class="d-inline" onsubmit="return confirm('¿Estás seguro de eliminar este curso?');">
                                        <input type="hidden" name="accion" value="eliminar">
                                        <input type="hidden" name="id_curso" value="<?= $curso['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-danger" title="Eliminar"><i class="fas fa-trash-alt"></i></button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="modal fade" id="modalCurso" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog">
            <div class="modal-content shadow-lg border-0">
              <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modalTitulo"><i class="fas fa-book"></i> Nuevo Curso</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <form action="cursos.php" method="POST">
                <div class="modal-body bg-light">
                  <input type="hidden" name="accion" value="guardar">
                  <input type="hidden" name="id_curso" id="inputIdCurso">
                  
                  <div class="mb-3">
                    <label class="form-label fw-bold text-secondary">Nombre del Curso</label>
                    <input type="text" class="form-control" id="inputNombreCurso" name="nombre_curso" required placeholder="Ej. Derecho Penal Especial III">
                  </div>
                  
                  <div class="mb-3">
                    <label class="form-label fw-bold text-secondary">Semestre / Periodo</label>
                    <input type="text" class="form-control" id="inputSemestre" name="semestre" required placeholder="Ej. 2026-1">
                  </div>

                  <div class="mb-3">
                    <label class="form-label fw-bold text-secondary">Docente Responsable</label>
                    <select class="form-select" name="docente_id" required>
                        <option value="">Seleccione un docente...</option>
                        <?php foreach ($docentes as $doc): ?>
                            <option value="<?= $doc['id'] ?>"><?= htmlspecialchars($doc['nombres'] . ' ' . $doc['apellidos']) ?></option>
                        <?php endforeach; ?>
                    </select>
                  </div>
                </div>
                <div class="modal-footer">
                  <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                  <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Guardar Curso</button>
                </div>
              </form>
            </div>
          </div>
        </div>

    </main>

    <footer class="footer mt-auto py-3 bg-dark text-white text-center shadow-lg">
        <div class="container"><span class="text-muted">Desarrollado por Michael Espinoza Coila</span></div>
    </footer>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>

    <script>
        $(document).ready(function() {
            $('#tablaCursos').DataTable({
                "language": { "url": "https://cdn.datatables.net/plug-ins/1.13.4/i18n/es-ES.json" },
                "pageLength": 10
            });
        });

        // Script para reutilizar el mismo Modal para Crear y Editar
        function abrirModalCurso(id = '', nombre = '', semestre = '') {
            document.getElementById('inputIdCurso').value = id;
            document.getElementById('inputNombreCurso').value = nombre;
            document.getElementById('inputSemestre').value = semestre;
            
            document.getElementById('modalTitulo').innerHTML = id === '' 
                ? '<i class="fas fa-book"></i> Nuevo Curso' 
                : '<i class="fas fa-edit"></i> Editar Curso';
            
            var modal = new bootstrap.Modal(document.getElementById('modalCurso'));
            modal.show();
        }
    </script>
</body>
</html>