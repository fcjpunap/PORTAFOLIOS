<?php
// gestionar_cursos.php - Panel de Admin para crear, editar y asignar cursos
session_start();
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'admin') {
    header('Location: login.php');
    exit;
}
require_once 'config/conexion.php';

$mensaje = '';

// Procesar CRUD
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    if ($_POST['accion'] === 'crear') {
        $nombre_curso = trim($_POST['nombre_curso']);
        $docente_id = $_POST['docente_id'];

        if (!empty($nombre_curso) && !empty($docente_id)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO cursos (nombre_curso, docente_id) VALUES (?, ?)");
                $stmt->execute([$nombre_curso, $docente_id]);
                $mensaje = "<div class='alert alert-success'><i class='fas fa-check-circle me-1'></i> Curso creado exitosamente.</div>";
            } catch (PDOException $e) {
                $mensaje = "<div class='alert alert-danger'>Error al crear curso.</div>";
            }
        }
    } elseif ($_POST['accion'] === 'editar') {
        $id = $_POST['curso_id'];
        $nombre_curso = trim($_POST['nombre_curso']);
        $docente_id = $_POST['docente_id'];
        try {
            $stmt = $pdo->prepare("UPDATE cursos SET nombre_curso=?, docente_id=? WHERE id=?");
            $stmt->execute([$nombre_curso, $docente_id, $id]);
            $mensaje = "<div class='alert alert-info'>Curso actualizado correctamente.</div>";
        } catch (PDOException $e) {
            $mensaje = "<div class='alert alert-danger'>Error al actualizar.</div>";
        }
    } elseif ($_POST['accion'] === 'eliminar') {
        try {
            $stmt = $pdo->prepare("DELETE FROM cursos WHERE id=?");
            $stmt->execute([$_POST['curso_id']]);
            $mensaje = "<div class='alert alert-warning'>Curso eliminado.</div>";
        } catch (PDOException $e) {
            $mensaje = "<div class='alert alert-danger'>No se puede eliminar el curso porque ya tiene alumnos o casos vinculados.</div>";
        }
    }
}

// Obtener lista de docentes
$docentes = [];
$stmt_docentes = $pdo->query("SELECT id, nombres, apellidos FROM usuarios WHERE rol = 'docente' ORDER BY apellidos ASC");
$docentes = $stmt_docentes->fetchAll(PDO::FETCH_ASSOC);

// BUSCADOR Y PAGINACIÓN
$search = $_GET['buscar'] ?? '';
$pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
if ($pagina < 1) $pagina = 1;
$limite = 10; 
$offset = ($pagina - 1) * $limite;

$whereSql = "";
$params = [];
if ($search !== '') {
    $whereSql = " WHERE c.nombre_curso LIKE ? OR u.nombres LIKE ? OR u.apellidos LIKE ?";
    $searchParam = "%$search%";
    $params = [$searchParam, $searchParam, $searchParam];
}

$stmt_total = $pdo->prepare("SELECT COUNT(*) FROM cursos c INNER JOIN usuarios u ON c.docente_id = u.id $whereSql");
$stmt_total->execute($params);
$total_cursos = $stmt_total->fetchColumn();
$total_paginas = ceil($total_cursos / $limite);

$sql_cursos = "SELECT c.id, c.nombre_curso, c.docente_id, u.nombres as doc_nom, u.apellidos as doc_ape 
               FROM cursos c 
               INNER JOIN usuarios u ON c.docente_id = u.id 
               $whereSql 
               ORDER BY c.id DESC LIMIT $limite OFFSET $offset";
$stmt_cursos = $pdo->prepare($sql_cursos);
$stmt_cursos->execute($params);
$cursos = $stmt_cursos->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestionar Cursos | Admin UNAP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style> body { background-color: #f8f9fa; } .bg-unap { background-color: #0b2e59; } </style>
</head>
<body>
    <nav class="navbar navbar-dark bg-unap shadow-sm">
        <div class="container"><a class="navbar-brand" href="dashboard_admin.php"><i class="fas fa-arrow-left me-2"></i> Volver al Panel</a></div>
    </nav>
    <main class="container py-4">
        <h3 class="mb-4 text-secondary"><i class="fas fa-book text-info me-2"></i> Gestión de Cursos</h3>
        <?= $mensaje ?>

        <div class="row">
            <div class="col-md-4 mb-4">
                <div class="card shadow-sm border-0 border-top border-4 border-info">
                    <div class="card-body">
                        <h5 class="card-title fw-bold mb-3">Crear Nuevo Curso</h5>
                        <form action="gestionar_cursos.php" method="POST">
                            <input type="hidden" name="accion" value="crear">
                            <div class="mb-3">
                                <label class="small fw-bold">Nombre de la Asignatura *</label>
                                <input type="text" name="nombre_curso" class="form-control" placeholder="Ej. Derecho Penal Especial III" required>
                            </div>
                            <div class="mb-4">
                                <label class="small fw-bold">Asignar a Docente *</label>
                                <select name="docente_id" class="form-select" required>
                                    <option value="">-- Selecciona un docente --</option>
                                    <?php foreach($docentes as $d): ?>
                                        <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['apellidos'] . ', ' . $d['nombres']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-info text-white w-100 fw-bold"><i class="fas fa-plus me-1"></i> Crear Curso</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white py-3">
                        <form action="gestionar_cursos.php" method="GET" class="d-flex">
                            <input type="text" name="buscar" class="form-control me-2" placeholder="Buscar por asignatura o docente..." value="<?= htmlspecialchars($search) ?>">
                            <button type="submit" class="btn btn-info text-white"><i class="fas fa-search"></i></button>
                            <?php if ($search): ?>
                                <a href="gestionar_cursos.php" class="btn btn-outline-secondary ms-2"><i class="fas fa-times"></i></a>
                            <?php endif; ?>
                        </form>
                    </div>
                    <div class="card-body table-responsive p-0">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">ID</th>
                                    <th>Asignatura</th>
                                    <th>Docente Asignado</th>
                                    <th class="text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($cursos)): ?>
                                    <tr><td colspan="4" class="text-center py-4 text-muted">No se encontraron cursos.</td></tr>
                                <?php else: ?>
                                    <?php foreach($cursos as $c): ?>
                                    <tr>
                                        <td class="ps-3 fw-bold text-muted">#<?= $c['id'] ?></td>
                                        <td class="fw-bold"><?= htmlspecialchars($c['nombre_curso']) ?></td>
                                        <td><i class="fas fa-chalkboard-teacher text-muted me-1"></i> <?= htmlspecialchars($c['doc_ape'] . ', ' . $c['doc_nom']) ?></td>
                                        <td class="text-center">
                                            <div class="btn-group">
                                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="abrirModalEditar(<?= htmlspecialchars(json_encode($c)) ?>)"><i class="fas fa-edit"></i></button>
                                                <form action="gestionar_cursos.php" method="POST" onsubmit="return confirm('¿Eliminar este curso definitivamente?');" style="display:inline;">
                                                    <input type="hidden" name="accion" value="eliminar">
                                                    <input type="hidden" name="curso_id" value="<?= $c['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if ($total_paginas > 1): ?>
                    <div class="card-footer bg-white py-3">
                        <ul class="pagination pagination-sm justify-content-center mb-0">
                            <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                                <li class="page-item <?= ($i == $pagina) ? 'active' : '' ?>"><a class="page-link" href="?buscar=<?= urlencode($search) ?>&pagina=<?= $i ?>"><?= $i ?></a></li>
                            <?php endfor; ?>
                        </ul>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <div class="modal fade" id="modalEditarCurso" tabindex="-1">
      <div class="modal-dialog">
        <div class="modal-content">
          <form action="gestionar_cursos.php" method="POST">
              <div class="modal-header bg-info text-white">
                <h5 class="modal-title">Editar Curso</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
              </div>
              <div class="modal-body">
                  <input type="hidden" name="accion" value="editar">
                  <input type="hidden" name="curso_id" id="edit_curso_id">
                  <div class="mb-3">
                      <label class="small fw-bold">Nombre de la Asignatura</label>
                      <input type="text" name="nombre_curso" id="edit_nombre_curso" class="form-control" required>
                  </div>
                  <div class="mb-3">
                      <label class="small fw-bold">Docente Asignado</label>
                      <select name="docente_id" id="edit_docente_id" class="form-select" required>
                          <?php foreach($docentes as $d): ?>
                              <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['apellidos'] . ', ' . $d['nombres']) ?></option>
                          <?php endforeach; ?>
                      </select>
                  </div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-info text-white">Guardar Cambios</button>
              </div>
          </form>
        </div>
      </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function abrirModalEditar(curso) {
            document.getElementById('edit_curso_id').value = curso.id;
            document.getElementById('edit_nombre_curso').value = curso.nombre_curso;
            document.getElementById('edit_docente_id').value = curso.docente_id;
            new bootstrap.Modal(document.getElementById('modalEditarCurso')).show();
        }
    </script>
</body>
</html>