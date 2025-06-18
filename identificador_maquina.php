<?php
include('config.php'); // Conexão com o banco

if (!isset($_COOKIE['uuid'])) {
    $uuid = bin2hex(random_bytes(16)); // Cria um identificador único
    setcookie('uuid', $uuid, time() + (10 * 365 * 24 * 60 * 60), "/"); // 10 anos

    // Insere no banco, usando INSERT IGNORE para evitar duplicação
    $stmt = $conn->prepare("INSERT IGNORE INTO maquinas (uuid) VALUES (?)");
    $stmt->bind_param("s", $uuid);
    $stmt->execute();
} else {
    $uuid = $_COOKIE['uuid'];

    // Verifica se já está no banco (caso o cookie esteja, mas não foi registrado ainda)
    $stmt = $conn->prepare("SELECT id FROM maquinas WHERE uuid = ?");
    $stmt->bind_param("s", $uuid);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        // Insere no banco, mas só se não houver UUID duplicado
        $stmt = $conn->prepare("INSERT INTO maquinas (uuid) VALUES (?)");
        $stmt->bind_param("s", $uuid);
        $stmt->execute();
    }
}
?>
