<?php
// procesar_calificacion.php
session_set_cookie_params(14400);
ini_set("session.gc_maxlifetime", 14400);
session_start();
require_once 'config/conexion.php';

// Verificación de seguridad básica (puedes ajustarla a tu lógica)
if (!isset($_SESSION['usuario_id'])) {
    die("Acceso denegado");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $envio_id = $_POST['envio_id'] ?? null;
    $nota = $_POST['nota'] ?? null;
    $feedback = $_POST['feedback'] ?? ''; // Opcional, por si tienes campo de retroalimentación en DB

    if ($envio_id && $nota !== null) {
        try {
            // Actualizamos la nota y cambiamos el estado automáticamente
            // Nota: Si en tu tabla no tienes columna 'retroalimentacion', puedes borrar esa parte de la consulta
            $stmt = $pdo->prepare("UPDATE envios_fichas SET calificacion = ?, estado = 'Revisado' WHERE id = ?");
            $stmt->execute([$nota, $envio_id]);

            // Redirigimos de vuelta al Dashboard con éxito
            header('Location: index.php?msg=calificado');
            exit;
        } catch (PDOException $e) {
            die("Error al guardar la nota: " . $e->getMessage());
        }
    } else {
        die("Faltan datos para calificar.");
    }
} else {
    header('Location: index.php');
}
?>