<?php
require_once __DIR__ . '/_base.php';
if (!isCoordenador()) json_erro('Sem permissão', 403);

$q = trim($_GET['q'] ?? '');
$stmt = $pdo->prepare("SELECT id, nome, tipo, categoria FROM usuarios 
                       WHERE ativo=1 AND (nome LIKE ? OR email LIKE ?)
                       ORDER BY nome LIMIT 20");
$stmt->execute(["%$q%", "%$q%"]);
json_out(['ok' => true, 'usuarios' => $stmt->fetchAll()]);