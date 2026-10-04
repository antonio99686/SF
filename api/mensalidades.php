<?php
require_once __DIR__ . '/_base.php';

$method = $_SERVER['REQUEST_METHOD'];
$uid = $_SESSION['usuario_id'];

if ($method === 'GET') {
    $where = "1=1"; $p = [];
    if (isDancarino()) {
        $where .= " AND m.usuario_id = ?"; $p[] = $uid;
    } elseif (isResponsavel()) {
        $where .= " AND m.usuario_id IN (SELECT id FROM usuarios WHERE responsavel_id = ?)";
        $p[] = $uid;
    }
    if (!empty($_GET['mes'])) {
        $where .= " AND DATE_FORMAT(m.mes_referencia,'%Y-%m') = ?";
        $p[] = $_GET['mes'];
    }
    if (!empty($_GET['status'])) {
        $where .= " AND m.status = ?"; $p[] = $_GET['status'];
    }
    $sql = "SELECT m.*, u.nome AS usuario_nome FROM mensalidades m
            JOIN usuarios u ON u.id = m.usuario_id
            WHERE $where ORDER BY m.mes_referencia DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($p);
    json_out(['ok' => true, 'mensalidades' => $stmt->fetchAll()]);
}

// PATCH - atualizar status (coordenador ou responsável do filho)
if ($method === 'PATCH') {
    $d = body_json();
    $id = (int)($d['id'] ?? 0);
    $status = $d['status'] ?? '';
    if (!$id || !in_array($status, ['pendente','pago','atrasado'])) json_erro('Dados inválidos');

    // Validar permissão
    $sql = "SELECT m.id FROM mensalidades m WHERE m.id = ?";
    $p = [$id];
    if (isDancarino()) {
        json_erro('Sem permissão', 403);
    } elseif (isResponsavel()) {
        $sql .= " AND m.usuario_id IN (SELECT id FROM usuarios WHERE responsavel_id = ?)";
        $p[] = $uid;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($p);
    if (!$stmt->fetch()) json_erro('Sem permissão', 403);

    $dataPag = $status === 'pago' ? date('Y-m-d') : null;
    $pdo->prepare("UPDATE mensalidades SET status=?, data_pagamento=? WHERE id=?")
        ->execute([$status, $dataPag, $id]);
    json_out(['ok' => true]);
}

// POST - cadastrar (só coordenador)
if ($method === 'POST') {
    if (!isCoordenador()) json_erro('Sem permissão', 403);
    $d = body_json();
    if (empty($d['usuario_id']) || empty($d['mes_referencia']) || empty($d['valor'])) json_erro('Campos obrigatórios');

    $stmt = $pdo->prepare("INSERT INTO mensalidades (usuario_id, mes_referencia, valor, status, observacao) VALUES (?,?,?,?,?)");
    $stmt->execute([
        (int)$d['usuario_id'],
        $d['mes_referencia'].'-01',
        (float)$d['valor'],
        $d['status'] ?? 'pendente',
        $d['observacao'] ?? ''
    ]);
    json_out(['ok' => true, 'id' => $pdo->lastInsertId()]);
}