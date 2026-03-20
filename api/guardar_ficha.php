<?php
session_start();
require_once '../config/conexion.php';

// Validar que sea una petición POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido']);
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. Sanitizar e Insertar la Ficha Principal
    $sql_ficha = "INSERT INTO envios_fichas (actividad_id, lider_id, factum, tipicidad, dogmatica, jurisprudencia, fallo) 
                  VALUES (:actividad, :lider, :factum, :tipicidad, :dogmatica, :jurisprudencia, :fallo)";
    $stmt = $pdo->prepare($sql_ficha);
    $stmt->execute([
        ':actividad' => $_POST['actividad_id'],
        ':lider' => $_SESSION['usuario_id'], // El líder es quien está logueado
        ':factum' => htmlspecialchars($_POST['factum']),
        ':tipicidad' => htmlspecialchars($_POST['tipicidad']),
        ':dogmatica' => htmlspecialchars($_POST['dogmatica']),
        ':jurisprudencia' => htmlspecialchars($_POST['jurisprudencia']),
        ':fallo' => htmlspecialchars($_POST['fallo'])
    ]);
    $envio_id = $pdo->lastInsertId();

    // 2. Insertar al resto del grupo (Array enviado por AJAX)
    if(isset($_POST['integrantes']) && is_array($_POST['integrantes'])) {
        $sql_grupo = "INSERT INTO envio_integrantes (envio_id, estudiante_id) VALUES (?, ?)";
        $stmt_grupo = $pdo->prepare($sql_grupo);
        // El líder también se añade al grupo
        $stmt_grupo->execute([$envio_id, $_SESSION['usuario_id']]); 
        foreach($_POST['integrantes'] as $integrante_id) {
            $stmt_grupo->execute([$envio_id, $integrante_id]);
        }
    }

    // 3. Procesamiento Seguro de Archivos (Anexos)
    if (!empty($_FILES['anexos']['name'][0])) {
        $permitidos = [
            'application/pdf', 
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document', // DOCX
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' // PPTX
        ];
        $directorio_subida = '../uploads/2026/';
        
        foreach ($_FILES['anexos']['tmp_name'] as $key => $tmp_name) {
            $file_type = mime_content_type($tmp_name); // Verificación real del MIME type
            
            if (in_array($file_type, $permitidos)) {
                $nombre_original = basename($_FILES['anexos']['name'][$key]);
                // Evitar sobreescrituras y nombres con caracteres extraños
                $nombre_seguro = time() . '_' . preg_replace("/[^a-zA-Z0-9.]/", "", $nombre_original);
                $ruta_destino = $directorio_subida . $nombre_seguro;
                
                if (move_uploaded_file($tmp_name, $ruta_destino)) {
                    $sql_archivo = "INSERT INTO anexos (envio_id, nombre_archivo, ruta_archivo, tipo_archivo) VALUES (?, ?, ?, ?)";
                    $pdo->prepare($sql_archivo)->execute([$envio_id, $nombre_original, $ruta_destino, $file_type]);
                }
            }
        }
    }

    $pdo->commit();
    echo json_encode(['status' => 'success', 'message' => 'Ficha enviada correctamente y portafolio actualizado.']);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['status' => 'error', 'message' => 'Error al procesar: ' . $e->getMessage()]);
}
?>