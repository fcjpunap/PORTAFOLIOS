<?php
// recuperar_password.php - Solicitar reseteo de clave
require_once 'config/conexion.php';
$mensaje = '';
$tipo_mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    
    // Verificar si el correo existe
    $stmt = $pdo->prepare("SELECT id, nombres FROM usuarios WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        // Generar un token único y seguro
        $token = bin2hex(random_bytes(32));
        // El token expira en 1 hora
        $expira = date("Y-m-d H:i:s", strtotime('+1 hour'));

        // Guardar token en la base de datos
        $stmt_update = $pdo->prepare("UPDATE usuarios SET reset_token = ?, reset_token_exp = ? WHERE email = ?");
        $stmt_update->execute([$token, $expira, $email]);

        // PREPARAR Y ENVIAR EL EMAIL
        $enlace_recuperacion = "https://derecho.unap.edu.pe/portafolios/restablecer_password.php?token=" . $token;
        
        $para = $email;
        $titulo = "Recuperación de Contraseña - Portafolio FCJP UNAP";
        
        // Cabeceras para enviar correo en formato HTML
        $cabeceras  = "MIME-Version: 1.0" . "\r\n";
        $cabeceras .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $cabeceras .= "From: Soporte Portafolios UNAP <noreply@derecho.unap.edu.pe>" . "\r\n";

        $cuerpo = "
        <html>
        <head><title>Restablecer Contraseña</title></head>
        <body style='font-family: Arial, sans-serif; color: #333;'>
            <h2>Hola, " . htmlspecialchars($user['nombres']) . "</h2>
            <p>Hemos recibido una solicitud para restablecer tu contraseña en el sistema de Portafolios FCJP.</p>
            <p>Si no fuiste tú, puedes ignorar este correo.</p>
            <p>Para crear una nueva contraseña, haz clic en el siguiente enlace (válido por 1 hora):</p>
            <p><a href='{$enlace_recuperacion}' style='background-color: #0b2e59; color: white; padding: 10px 15px; text-decoration: none; border-radius: 5px;'>Restablecer mi contraseña</a></p>
            <br>
            <p>Si el botón no funciona, copia y pega este enlace en tu navegador:</p>
            <p>{$enlace_recuperacion}</p>
        </body>
        </html>
        ";

        // Función mail() de PHP (Requiere que el servidor tenga un servicio de correo configurado)
        if (mail($para, $titulo, $cuerpo, $cabeceras)) {
            $mensaje = "Te hemos enviado un enlace al correo ingresado. Por favor, revisa tu bandeja de entrada o la carpeta de Spam.";
            $tipo_mensaje = "success";
        } else {
            $mensaje = "Error al intentar enviar el correo. Por favor, contacta con soporte técnico.";
            $tipo_mensaje = "danger";
        }
    } else {
        // Por seguridad, damos el mismo mensaje de éxito aunque no exista, 
        // para evitar que escaneen qué correos están registrados.
        $mensaje = "Te hemos enviado un enlace al correo ingresado. Por favor, revisa tu bandeja de entrada o la carpeta de Spam.";
        $tipo_mensaje = "success";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Recuperar Contraseña | UNAP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style> body { background-color: #f4f6f9; } </style>
</head>
<body class="d-flex align-items-center min-vh-100">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                
                <?php if ($mensaje): ?>
                    <div class="alert alert-<?= $tipo_mensaje ?> shadow-sm">
                        <?= $mensaje ?>
                    </div>
                <?php endif; ?>

                <div class="card shadow border-0 p-4">
                    <div class="text-center mb-4">
                        <i class="fas fa-envelope-open-text fa-3x text-primary mb-3"></i>
                        <h4 class="fw-bold">Recuperar Contraseña</h4>
                        <p class="text-muted small">Ingresa el correo electrónico institucional con el que estás registrado. Te enviaremos un enlace seguro.</p>
                    </div>

                    <form action="recuperar_password.php" method="POST">
                        <div class="mb-4">
                            <label class="form-label fw-bold text-secondary">Correo Institucional</label>
                            <input type="email" class="form-control form-control-lg" name="email" placeholder="ejemplo@est.unap.edu.pe" required>
                        </div>
                        <button type="submit" class="btn btn-primary btn-lg w-100 fw-bold shadow-sm">Enviar Enlace</button>
                    </form>
                    
                    <div class="text-center mt-4">
                        <a href="login.php" class="text-decoration-none text-secondary"><i class="fas fa-arrow-left me-1"></i> Volver al Login</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>