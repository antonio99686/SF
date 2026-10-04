<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

function json_out($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function json_erro($msg, $code = 400) {
    json_out(['ok' => false, 'erro' => $msg], $code);
}

function body_json() {
    $raw = file_get_contents('php://input');
    return json_decode($raw, true) ?: [];
}

if (!usuarioLogado()) json_erro('Não autenticado', 401);

// Após checar se está logado
if (in_array($_SERVER['REQUEST_METHOD'], ['POST','PATCH','DELETE'])) {
    $enviado = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? '');
    if (!$enviado || !hash_equals($_SESSION['csrf_token'] ?? '', $enviado)) {
        json_erro('Token CSRF inválido', 403);
    }
}