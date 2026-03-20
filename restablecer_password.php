<?php
// restablecer_password.php - Validar token y guardar nueva clave
require_once 'config/conexion.php';
$mensaje = '';
$tipo_mensaje = '';
$token_valido = false;
$usuario_id = null;

// Obtener el token por la URL o por POST
$token = $_GET['token'] ?? $_POST['token'] ?? '';

if (empty($token)) {
    die("Enlace inválido o incompleto.");
}

// Verificar si el token existe y no ha expirado
$stmt = $pdo->prepare("SELECT id FROM usuarios WHERE reset_token = ? AND reset_token_exp > NOW()");
$stmt->execute([$token]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user) {
    $token_valido = true;
    $usuario_id = $user['id'];
} else {
    $mensaje = "El enlace de recuperación ha expirado o no es válido. Por favor, solicita uno nuevo.";
    $tipo_mensaje = "danger";
}

// Si se envía el formulario con la nueva contraseña
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $token_valido) {
    $password_nueva = $_POST['password_nueva'];
    $password_confirmar = $_POST['password_confirmar'];

    if ($password_nueva === $password_confirmar && strlen($password_nueva) >= 6) {
        $hash_nuevo = password_hash($password_nueva, PASSWORD_DEFAULT);
        
        // Actualizar contraseña y destruir el token para que no se re-use
        $stmt_update = $pdo->prepare("UPDATE usuarios SET password = ?, reset_token = NULL, reset_token_exp = NULL WHERE id = ?");
        $stmt_update->execute([$hash_nuevo, $usuario_id]);
        
        $mensaje = "¡Contraseña restablecida con éxito! Ya puedes iniciar sesión.";
        $tipo_mensaje = "success";
        $token_valido = false; // Ocultar el formulario
    } else {
        $mensaje = "Las contraseñas no coinciden o son menores a 6 caracteres.";
        $tipo_mensaje = "warning";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Nueva Contraseña | UNAP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style> body { background-color: #f4f6f9; } </style>
</head>
<body class="d-flex align-items-center min-vh-100">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                
                <?php if ($mensaje): ?>
                    <div class="alert alert-<?= $tipo_mensaje ?> shadow-sm text-center">
                        <?= $mensaje ?>
                        <?php if ($tipo_mensaje === 'success'): ?>
                            <br><br>
                            <a href="login.php" class="btn btn-success fw-bold">Ir al Login</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if ($token_valido): ?>
                    <div class="card shadow border-0 p-4 border-top border-4 border-success">
                        <div class="text-center mb-4">
                            <i class="fas fa-shield-alt fa-3x text-success mb-3"></i>
                            <h4 class="fw-bold">Crear Nueva Contraseña</h4>
                            <p class="text-muted small">Establece una nueva contraseña para tu cuenta.</p>
                        </div>

                        <form action="restablecer_password.php" method="POST">
                            <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                            
                            <div class="mb-3">
                                <label class="form-label fw-bold text-secondary">Nueva Contraseña</label>
                                <input type="password" class="form-control" name="password_nueva" required minlength="6">
                            </div>
                            <div class="mb-4">
                                <label class="form-label fw-bold text-secondary">Repetir Nueva Contraseña</label>
                                <input type="password" class="form-control" name="password_confirmar" required minlength="6">
                            </div>
                            <button type="submit" class="btn btn-success w-100 fw-bold shadow-sm">Guardar y Entrar</button>
                        </form>
                    </div>
                <?php endif; ?>
                
            </div>
        </div>
    </div>
</body>
</html>