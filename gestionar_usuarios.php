<?php
// gestionar_usuarios.php - Panel de Admin para CRUD de usuarios
session_set_cookie_params(14400);
ini_set("session.gc_maxlifetime", 14400);
session_start();
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'admin') {
    header('Location: login.php');
    exit;
}
require_once 'config/conexion.php';

$mensaje = '';

// Procesar acciones (Crear, Editar, Eliminar, Cambiar Clave)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    try {
        if ($_POST['accion'] === 'crear') {
            $nombres = trim($_POST['nombres']);
            $apellidos = trim($_POST['apellidos']);
            $email = trim($_POST['email']);
            $rol = $_POST['rol'];
            $codigo = trim($_POST['codigo_estudiante']);
            $password_plain = !empty($codigo) ? $codigo : 'unap12345';
            $password_hash = password_hash($password_plain, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare("INSERT INTO usuarios (nombres, apellidos, email, password, rol, codigo_estudiante) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$nombres, $apellidos, $email, $password_hash, $rol, $codigo]);
            $mensaje = "<div class='alert alert-success'>Usuario creado. Contraseña inicial: <b>$password_plain</b></div>";
        
        } elseif ($_POST['accion'] === 'editar') {
            $id = $_POST['id_usuario'];
            $nombres = trim($_POST['nombres']);
            $apellidos = trim($_POST['apellidos']);
            $email = trim($_POST['email']);
            $rol = $_POST['rol'];
            $codigo = trim($_POST['codigo_estudiante']);
            
            $stmt = $pdo->prepare("UPDATE usuarios SET nombres=?, apellidos=?, email=?, rol=?, codigo_estudiante=? WHERE id=?");
            $stmt->execute([$nombres, $apellidos, $email, $rol, $codigo, $id]);
            $mensaje = "<div class='alert alert-info'>Usuario actualizado correctamente.</div>";
            
        } elseif ($_POST['accion'] === 'eliminar') {
            $id = $_POST['id_usuario'];
            $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id=?");
            $stmt->execute([$id]);
            $mensaje = "<div class='alert alert-warning'>Usuario eliminado del sistema.</div>";
            
        } elseif ($_POST['accion'] === 'cambiar_clave') {
            $id = $_POST['id_usuario'];
            $nueva_clave = trim($_POST['nueva_clave']);
            if(strlen($nueva_clave) >= 6) {
                $hash = password_hash($nueva_clave, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE usuarios SET password=? WHERE id=?");
                $stmt->execute([$hash, $id]);
                $mensaje = "<div class='alert alert-success'><i class='fas fa-key'></i> Contraseña actualizada correctamente.</div>";
            } else {
                $mensaje = "<div class='alert alert-danger'>La contraseña debe tener al menos 6 caracteres.</div>";
            }
        }
    } catch (PDOException $e) {
        $mensaje = "<div class='alert alert-danger'>Error en la base de datos: " . $e->getMessage() . "</div>";
    }
}

// BUSCADOR Y PAGINACIÓN
$search = $_GET['buscar'] ?? '';
$pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
if ($pagina < 1) $pagina = 1;
$limite = 10; 
$offset = ($pagina - 1) * $limite;

$whereSql = "";
$params = [];
if ($search !== '') {
    $whereSql = " WHERE nombres LIKE ? OR apellidos LIKE ? OR email LIKE ? OR codigo_estudiante LIKE ?";
    $searchParam = "%$search%";
    $params = [$searchParam, $searchParam, $searchParam, $searchParam];
}

$stmt_total = $pdo->prepare("SELECT COUNT(*) FROM usuarios $whereSql");
$stmt_total->execute($params);
$total_usuarios = $stmt_total->fetchColumn();
$total_paginas = ceil($total_usuarios / $limite);

$sql = "SELECT id, nombres, apellidos, email, rol, codigo_estudiante FROM usuarios $whereSql ORDER BY rol ASC, apellidos ASC LIMIT $limite OFFSET $offset";
$stmt_usuarios = $pdo->prepare($sql);
$stmt_usuarios->execute($params);
$usuarios = $stmt_usuarios->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestionar Usuarios | Admin UNAP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style> body { background-color: #f8f9fa; } .bg-unap { background-color: #0b2e59; } </style>
</head>
<body>
    <nav class="navbar navbar-dark bg-unap shadow-sm">
        <div class="container"><a class="navbar-brand" href="dashboard_admin.php"><i class="fas fa-arrow-left me-2"></i> Volver al Panel</a></div>
    </nav>
    <main class="container py-4">
        <h3 class="mb-4 text-secondary"><i class="fas fa-users-cog text-primary me-2"></i> Gestión de Usuarios</h3>
        <?= $mensaje ?>

        <div class="row">
            <div class="col-md-4 mb-4">
                <div class="card shadow-sm border-0 border-top border-4 border-success">
                    <div class="card-body">
                        <h5 class="card-title fw-bold mb-3">Nuevo Usuario</h5>
                        <form action="gestionar_usuarios.php" method="POST">
                            <input type="hidden" name="accion" value="crear">
                            <div class="mb-2"><label class="small fw-bold">Nombres</label><input type="text" name="nombres" class="form-control" required></div>
                            <div class="mb-2"><label class="small fw-bold">Apellidos</label><input type="text" name="apellidos" class="form-control" required></div>
                            <div class="mb-2"><label class="small fw-bold">Correo Institucional</label><input type="email" name="email" class="form-control" required></div>
                            <div class="mb-2">
                                <label class="small fw-bold">Rol</label>
                                <select name="rol" class="form-select" required>
                                    <option value="estudiante">Estudiante</option>
                                    <option value="docente">Docente</option>
                                    <option value="admin">Administrador</option>
                                </select>
                            </div>
                            <div class="mb-3"><label class="small fw-bold">Código (Solo alumnos)</label><input type="text" name="codigo_estudiante" class="form-control"></div>
                            <button type="submit" class="btn btn-success w-100 fw-bold">Registrar Usuario</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white py-3">
                        <form action="gestionar_usuarios.php" method="GET" class="d-flex">
                            <input type="text" name="buscar" class="form-control me-2" placeholder="Buscar por nombre, correo o código..." value="<?= htmlspecialchars($search) ?>">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
                            <?php if ($search): ?>
                                <a href="gestionar_usuarios.php" class="btn btn-outline-secondary ms-2"><i class="fas fa-times"></i></a>
                            <?php endif; ?>
                        </form>
                    </div>
                    <div class="card-body table-responsive p-0">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">Rol</th>
                                    <th>Nombre y Correo</th>
                                    <th>Código</th>
                                    <th class="text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($usuarios as $u): ?>
                                <tr>
                                    <td class="ps-3"><span class="badge bg-<?= $u['rol'] == 'admin' ? 'danger' : ($u['rol'] == 'docente' ? 'primary' : 'success') ?>"><?= strtoupper($u['rol']) ?></span></td>
                                    <td>
                                        <div class="fw-bold"><?= htmlspecialchars($u['apellidos'] . ', ' . $u['nombres']) ?></div>
                                        <div class="small text-muted"><?= htmlspecialchars($u['email']) ?></div>
                                    </td>
                                    <td><?= htmlspecialchars($u['codigo_estudiante']) ?></td>
                                    <td class="text-center">
                                        <div class="btn-group">
                                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="abrirModalEditar(<?= htmlspecialchars(json_encode($u)) ?>)" title="Editar"><i class="fas fa-edit"></i></button>
                                            <button type="button" class="btn btn-sm btn-outline-warning" onclick="abrirModalClave(<?= $u['id'] ?>, '<?= addslashes($u['nombres']) ?>')" title="Cambiar Contraseña"><i class="fas fa-key"></i></button>
                                            <form action="gestionar_usuarios.php" method="POST" onsubmit="return confirm('¿Eliminar este usuario definitivamente?');" style="display:inline;">
                                                <input type="hidden" name="accion" value="eliminar">
                                                <input type="hidden" name="id_usuario" value="<?= $u['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar"><i class="fas fa-trash"></i></button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
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

    <div class="modal fade" id="modalEditar" tabindex="-1">
      <div class="modal-dialog">
        <div class="modal-content">
          <form action="gestionar_usuarios.php" method="POST">
              <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Editar Usuario</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
              </div>
              <div class="modal-body">
                  <input type="hidden" name="accion" value="editar">
                  <input type="hidden" name="id_usuario" id="edit_id">
                  <div class="mb-2"><label class="small fw-bold">Nombres</label><input type="text" name="nombres" id="edit_nombres" class="form-control" required></div>
                  <div class="mb-2"><label class="small fw-bold">Apellidos</label><input type="text" name="apellidos" id="edit_apellidos" class="form-control" required></div>
                  <div class="mb-2"><label class="small fw-bold">Correo Institucional</label><input type="email" name="email" id="edit_email" class="form-control" required></div>
                  <div class="mb-2">
                      <label class="small fw-bold">Rol</label>
                      <select name="rol" id="edit_rol" class="form-select" required>
                          <option value="estudiante">Estudiante</option>
                          <option value="docente">Docente</option>
                          <option value="admin">Administrador</option>
                      </select>
                  </div>
                  <div class="mb-2"><label class="small fw-bold">Código (Solo alumnos)</label><input type="text" name="codigo_estudiante" id="edit_codigo" class="form-control"></div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary">Guardar Cambios</button>
              </div>
          </form>
        </div>
      </div>
    </div>

    <div class="modal fade" id="modalClave" tabindex="-1">
      <div class="modal-dialog modal-sm">
        <div class="modal-content">
          <form action="gestionar_usuarios.php" method="POST">
              <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title fw-bold"><i class="fas fa-key"></i> Cambiar Clave</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
              </div>
              <div class="modal-body text-center">
                  <input type="hidden" name="accion" value="cambiar_clave">
                  <input type="hidden" name="id_usuario" id="clave_id">
                  <p class="small text-muted mb-2">Nueva clave para: <br><strong id="clave_nombre"></strong></p>
                  <input type="text" name="nueva_clave" class="form-control text-center mb-2" placeholder="Mínimo 6 caracteres" required minlength="6">
              </div>
              <div class="modal-footer p-2">
                <button type="submit" class="btn btn-warning w-100 fw-bold">Aplicar Contraseña</button>
              </div>
          </form>
        </div>
      </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function abrirModalEditar(usuario) {
            document.getElementById('edit_id').value = usuario.id;
            document.getElementById('edit_nombres').value = usuario.nombres;
            document.getElementById('edit_apellidos').value = usuario.apellidos;
            document.getElementById('edit_email').value = usuario.email;
            document.getElementById('edit_rol').value = usuario.rol;
            document.getElementById('edit_codigo').value = usuario.codigo_estudiante || '';
            new bootstrap.Modal(document.getElementById('modalEditar')).show();
        }
        function abrirModalClave(id, nombre) {
            document.getElementById('clave_id').value = id;
            document.getElementById('clave_nombre').innerText = nombre;
            new bootstrap.Modal(document.getElementById('modalClave')).show();
        }
    </script>
</body>
</html>