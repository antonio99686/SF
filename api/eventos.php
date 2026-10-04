<?php
require_once __DIR__ . '/_base.php';

$method = $_SERVER['REQUEST_METHOD'];
$uid = $_SESSION['usuario_id'];

// GET - listar
if ($method === 'GET') {
    if (isCoordenador()) {
        $stmt = $pdo->query("SELECT e.*, u.nome AS criador FROM eventos e 
                             LEFT JOIN usuarios u ON u.id = e.criado_por 
                             ORDER BY e.data_evento DESC");
    } elseif (isDancarino()) {
        $stmt = $pdo->prepare("SELECT * FROM eventos 
                               WHERE categoria IN ('todos', ?) 
                               ORDER BY data_evento DESC");
        $stmt->execute([$_SESSION['categoria']]);
    } else {
        $stmt = $pdo->prepare("SELECT DISTINCT e.* FROM eventos e
                               JOIN usuarios u ON u.responsavel_id = ?
                               WHERE e.categoria IN ('todos', u.categoria)
                               ORDER BY e.data_evento DESC");
        $stmt->execute([$uid]);
    }
    json_out(['ok' => true, 'eventos' => $stmt->fetchAll()]);
}

// POST - criar (só coordenador)
if ($method === 'POST') {
    if (!isCoordenador()) json_erro('Sem permissão', 403);
    $d = body_json();
    if (empty($d['titulo']) || empty($d['data_evento'])) json_erro('Campos obrigatórios');

    $stmt = $pdo->prepare("INSERT INTO eventos (titulo, descricao, data_evento, local, categoria, criado_por) 
                           VALUES (?,?,?,?,?,?)");
    $stmt->execute([
        $d['titulo'], $d['descricao'] ?? '', $d['data_evento'],
        $d['local'] ?? '', $d['categoria'] ?? 'todos', $uid
    ]);
    json_out(['ok' => true, 'id' => $pdo->lastInsertId()]);
}

// DELETE - excluir (só coordenador)
if ($method === 'DELETE') {
    if (!isCoordenador()) json_erro('Sem permissão', 403);
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) json_erro('ID obrigatório');
    $pdo->prepare("DELETE FROM eventos WHERE id=?")->execute([$id]);
    json_out(['ok' => true]);
}   