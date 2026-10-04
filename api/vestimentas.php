<?php
require_once __DIR__ . '/_base.php';

$method = $_SERVER['REQUEST_METHOD'];
$uid = $_SESSION['usuario_id'];

if ($method === 'GET') {
    $vest = $pdo->query("SELECT * FROM vestimentas ORDER BY nome")->fetchAll();

    if (isCoordenador()) {
        $stmt = $pdo->query("SELECT e.*, v.nome AS vestimenta_nome, u.nome AS usuario_nome
                             FROM vestimenta_entregas e
                             JOIN vestimentas v ON v.id = e.vestimenta_id
                             JOIN usuarios u ON u.id = e.usuario_id
                             ORDER BY e.data_entrega DESC");
    } elseif (isDancarino()) {
        $stmt = $pdo->prepare("SELECT e.*, v.nome AS vestimenta_nome, u.nome AS usuario_nome
                               FROM vestimenta_entregas e
                               JOIN vestimentas v ON v.id = e.vestimenta_id
                               JOIN usuarios u ON u.id = e.usuario_id
                               WHERE e.usuario_id = ?");
        $stmt->execute([$uid]);
    } else {
        $stmt = $pdo->prepare("SELECT e.*, v.nome AS vestimenta_nome, u.nome AS usuario_nome
                               FROM vestimenta_entregas e
                               JOIN vestimentas v ON v.id = e.vestimenta_id
                               JOIN usuarios u ON u.id = e.usuario_id
                               WHERE u.responsavel_id = ?");
        $stmt->execute([$uid]);
    }
    json_out(['ok'=>true, 'vestimentas'=>$vest, 'entregas'=>$stmt->fetchAll()]);
}

// POST - entregar (coordenador)
if ($method === 'POST') {
    if (!isCoordenador()) json_erro('Sem permissão', 403);
    $d = body_json();
    $vid = (int)($d['vestimenta_id'] ?? 0);
    $u   = (int)($d['usuario_id'] ?? 0);
    if (!$vid || !$u) json_erro('Dados inválidos');

    $stmt = $pdo->prepare("SELECT quantidade FROM vestimentas WHERE id=?");
    $stmt->execute([$vid]);
    $v = $stmt->fetch();
    if (!$v || $v['quantidade'] <= 0) json_erro('Sem estoque');

    $pdo->beginTransaction();
    $pdo->prepare("INSERT INTO vestimenta_entregas (vestimenta_id, usuario_id, data_entrega) VALUES (?,?,CURDATE())")
        ->execute([$vid, $u]);
    $pdo->prepare("UPDATE vestimentas SET quantidade = quantidade - 1 WHERE id=?")->execute([$vid]);
    $pdo->commit();
    json_out(['ok' => true]);
}

// DELETE - devolver (coordenador)
if ($method === 'DELETE') {
    if (!isCoordenador()) json_erro('Sem permissão', 403);
    $id = (int)($_GET['id'] ?? 0);
    $stmt = $pdo->prepare("SELECT vestimenta_id FROM vestimenta_entregas WHERE id=? AND devolvido=0");
    $stmt->execute([$id]);
    $e = $stmt->fetch();
    if (!$e) json_erro('Entrega não encontrada');

    $pdo->beginTransaction();
    $pdo->prepare("UPDATE vestimenta_entregas SET devolvido=1 WHERE id=?")->execute([$id]);
    $pdo->prepare("UPDATE vestimentas SET quantidade = quantidade + 1 WHERE id=?")->execute([$e['vestimenta_id']]);
    $pdo->commit();
    json_out(['ok' => true]);
}