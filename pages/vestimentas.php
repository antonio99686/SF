<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
exigirLogin();

$uid = $_SESSION['usuario_id'];
$msg = ''; $erro = '';

// ==================== AÇÕES DO COORDENADOR ====================

// --- Cadastrar vestimenta ---
if (isset($_POST['nova_vestimenta']) && isCoordenador()) {
    try {
        $stmt = $pdo->prepare("INSERT INTO vestimentas (nome, descricao, tamanho, categoria, quantidade, valor) VALUES (?,?,?,?,?,?)");
        $stmt->execute([
            trim($_POST['nome']),
            trim($_POST['descricao']),
            trim($_POST['tamanho']),
            $_POST['categoria'],
            (int)$_POST['quantidade'],
            (float)$_POST['valor']
        ]);
        $msg = 'Vestimenta cadastrada!';
    } catch (Exception $e) { $erro = $e->getMessage(); }
}

// --- Entrega ---
if (isset($_POST['entregar']) && isCoordenador()) {
    $vid = (int)$_POST['vestimenta_id'];
    $usuario_id = (int)$_POST['usuario_id'];
    $data = $_POST['data_entrega'] ?: date('Y-m-d');

    // Verificar estoque
    $stmt = $pdo->prepare("SELECT quantidade FROM vestimentas WHERE id=?");
    $stmt->execute([$vid]);
    $v = $stmt->fetch();
    if ($v && $v['quantidade'] > 0) {
        $pdo->beginTransaction();
        $pdo->prepare("INSERT INTO vestimenta_entregas (vestimenta_id, usuario_id, data_entrega) VALUES (?,?,?)")
            ->execute([$vid, $usuario_id, $data]);
        $pdo->prepare("UPDATE vestimentas SET quantidade = quantidade - 1 WHERE id=?")->execute([$vid]);
        $pdo->commit();
        $msg = 'Vestimenta entregue!';
    } else {
        $erro = 'Sem estoque disponível!';
    }
}

// --- Devolução ---
if (isset($_GET['devolver']) && isCoordenador()) {
    $eid = (int)$_GET['devolver'];
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("SELECT vestimenta_id FROM vestimenta_entregas WHERE id=? AND devolvido=0");
    $stmt->execute([$eid]);
    $e = $stmt->fetch();
    if ($e) {
        $pdo->prepare("UPDATE vestimenta_entregas SET devolvido=1 WHERE id=?")->execute([$eid]);
        $pdo->prepare("UPDATE vestimentas SET quantidade = quantidade + 1 WHERE id=?")->execute([$e['vestimenta_id']]);
    }
    $pdo->commit();
    header('Location: vestimentas.php?ok=1');
    exit;
}

// --- Excluir vestimenta ---
if (isset($_GET['excluir_vest']) && isCoordenador()) {
    $pdo->prepare("DELETE FROM vestimentas WHERE id=?")->execute([(int)$_GET['excluir_vest']]);
    header('Location: vestimentas.php?ok=1');
    exit;
}

// ==================== LISTAGENS ====================

$vestimentas = $pdo->query("SELECT * FROM vestimentas ORDER BY nome")->fetchAll();

// Entregas: coordenador vê todas; outros veem só as suas/dos filhos
if (isCoordenador()) {
    $entregas = $pdo->query("SELECT e.*, v.nome AS vestimenta_nome, v.tamanho, u.nome AS usuario_nome, u.categoria
                             FROM vestimenta_entregas e
                             JOIN vestimentas v ON v.id = e.vestimenta_id
                             JOIN usuarios u ON u.id = e.usuario_id
                             ORDER BY e.data_entrega DESC")->fetchAll();
} elseif (isDancarino()) {
    $stmt = $pdo->prepare("SELECT e.*, v.nome AS vestimenta_nome, v.tamanho, u.nome AS usuario_nome, u.categoria
                           FROM vestimenta_entregas e
                           JOIN vestimentas v ON v.id = e.vestimenta_id
                           JOIN usuarios u ON u.id = e.usuario_id
                           WHERE e.usuario_id = ?
                           ORDER BY e.data_entrega DESC");
    $stmt->execute([$uid]);
    $entregas = $stmt->fetchAll();
} else {
    $stmt = $pdo->prepare("SELECT e.*, v.nome AS vestimenta_nome, v.tamanho, u.nome AS usuario_nome, u.categoria
                           FROM vestimenta_entregas e
                           JOIN vestimentas v ON v.id = e.vestimenta_id
                           JOIN usuarios u ON u.id = e.usuario_id
                           WHERE u.responsavel_id = ?
                           ORDER BY e.data_entrega DESC");
    $stmt->execute([$uid]);
    $entregas = $stmt->fetchAll();
}

$dancarinos = isCoordenador()
    ? $pdo->query("SELECT id, nome, categoria FROM usuarios WHERE tipo='dancarino' AND ativo=1 ORDER BY nome")->fetchAll()
    : [];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Vestimentas - Sentinela</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<?php include '../includes/header.php'; ?>
<main class="container">
    <h1>👕 Vestimentas</h1>

    <?php if (isset($_GET['ok'])): ?><div class="alert ok">Ação realizada!</div><?php endif; ?>
    <?php if ($msg): ?><div class="alert ok"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <?php if ($erro): ?><div class="alert erro"><?= htmlspecialchars($erro) ?></div><?php endif; ?>

    <?php if (isCoordenador()): ?>
    <!-- Cadastro de vestimenta -->
    <details style="margin:20px 0; background:#fff; padding:20px; border-radius:10px;">
        <summary style="cursor:pointer; font-weight:bold;">➕ Nova Vestimenta</summary>
        <form method="POST" style="margin-top:15px; display:grid; grid-template-columns:1fr 1fr 1fr; gap:10px;">
            <input type="hidden" name="nova_vestimenta" value="1">
            <input type="text" name="nome" placeholder="Nome da peça *" required>
            <input type="text" name="tamanho" placeholder="Tamanho (P, M, G...)">
            <select name="categoria" required>
                <option value="todos">Todas as categorias</option>
                <option value="pre_mirim">Pré-Mirim</option>
                <option value="mirim">Mirim</option>
                <option value="juvenil">Juvenil</option>
                <option value="adulto">Adulto</option>
            </select>
            <input type="number" name="quantidade" placeholder="Quantidade *" required min="0">
            <input type="number" name="valor" step="0.01" placeholder="Valor (R$)">
            <input type="text" name="descricao" placeholder="Descrição" style="grid-column:span 3;">
            <div style="grid-column:1/-1;">
                <button class="btn btn-primary" type="submit">💾 Cadastrar</button>
            </div>
        </form>
    </details>

    <!-- Entrega -->
    <details style="margin:20px 0; background:#fff; padding:20px; border-radius:10px;">
        <summary style="cursor:pointer; font-weight:bold;">📦 Registrar Entrega</summary>
        <form method="POST" style="margin-top:15px; display:grid; grid-template-columns:1fr 1fr 1fr; gap:10px;">
            <input type="hidden" name="entregar" value="1">
            <select name="vestimenta_id" required>
                <option value="">Vestimenta *</option>
                <?php foreach ($vestimentas as $v): ?>
                    <option value="<?= $v['id'] ?>" <?= $v['quantidade']<=0?'disabled':'' ?>>
                        <?= htmlspecialchars($v['nome']) ?> 
                        (<?= $v['tamanho'] ?: 's/t' ?>) - Estoque: <?= $v['quantidade'] ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <select name="usuario_id" required>
                <option value="">Dançarino *</option>
                <?php foreach ($dancarinos as $d): ?>
                    <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['nome']) ?> (<?= $d['categoria'] ?>)</option>
                <?php endforeach; ?>
            </select>
            <input type="date" name="data_entrega" value="<?= date('Y-m-d') ?>">
            <div style="grid-column:1/-1;">
                <button class="btn btn-success" type="submit">📦 Entregar</button>
            </div>
        </form>
    </details>
    <?php endif; ?>

    <!-- ESTOQUE -->
    <h2 style="margin-top:30px;">📦 Estoque</h2>
    <table style="margin-top:10px;">
        <tr>
            <th>Nome</th><th>Tamanho</th><th>Categoria</th>
            <th>Valor</th><th>Estoque</th>
            <?php if (isCoordenador()): ?><th>Ações</th><?php endif; ?>
        </tr>
        <?php foreach ($vestimentas as $v): ?>
        <tr>
            <td><?= htmlspecialchars($v['nome']) ?></td>
            <td><?= htmlspecialchars($v['tamanho'] ?: '—') ?></td>
            <td><?= ucfirst(str_replace('_','-',$v['categoria'])) ?></td>
            <td>R$ <?= number_format($v['valor'], 2, ',', '.') ?></td>
            <td>
                <span style="font-weight:bold;color:<?= $v['quantidade']>0 ? '#16a34a':'#dc2626' ?>">
                    <?= $v['quantidade'] ?>
                </span>
            </td>
            <?php if (isCoordenador()): ?>
            <td>
                <a class="btn btn-danger" href="?excluir_vest=<?= $v['id'] ?>"
                   onclick="return confirm('Excluir esta vestimenta?')">🗑️</a>
            </td>
            <?php endif; ?>
        </tr>
        <?php endforeach; ?>
        <?php if (!$vestimentas): ?>
            <tr><td colspan="6" style="text-align:center;padding:20px;">Nenhuma vestimenta cadastrada.</td></tr>
        <?php endif; ?>
    </table>

    <!-- ENTREGAS / HISTÓRICO -->
    <h2 style="margin-top:30px;">
        <?= isCoordenador() ? '📋 Histórico de Entregas' : '📋 Minhas Vestimentas' ?>
    </h2>
    <table style="margin-top:10px;">
        <tr>
            <th>Vestimenta</th><th>Tamanho</th>
            <?php if (isCoordenador()): ?><th>Dançarino</th><th>Categoria</th><?php endif; ?>
            <th>Data Entrega</th><th>Status</th>
            <?php if (isCoordenador()): ?><th>Ações</th><?php endif; ?>
        </tr>
        <?php foreach ($entregas as $e): ?>
        <tr>
            <td><?= htmlspecialchars($e['vestimenta_nome']) ?></td>
            <td><?= htmlspecialchars($e['tamanho'] ?: '—') ?></td>
            <?php if (isCoordenador()): ?>
                <td><?= htmlspecialchars($e['usuario_nome']) ?></td>
                <td><?= ucfirst(str_replace('_','-',$e['categoria'] ?? '')) ?></td>
            <?php endif; ?>
            <td><?= $e['data_entrega'] ? date('d/m/Y', strtotime($e['data_entrega'])) : '—' ?></td>
            <td>
                <?= $e['devolvido'] 
                    ? '<span style="color:#16a34a;">✅ Devolvido</span>' 
                    : '<span style="color:#f59e0b;">📦 Em uso</span>' ?>
            </td>
            <?php if (isCoordenador() && !$e['devolvido']): ?>
            <td>
                <a class="btn btn-primary" href="?devolver=<?= $e['id'] ?>"
                   onclick="return confirm('Registrar devolução?')">↩️ Devolver</a>
            </td>
            <?php elseif (isCoordenador()): ?>
            <td>—</td>
            <?php endif; ?>
        </tr>
        <?php endforeach; ?>
        <?php if (!$entregas): ?>
            <tr><td colspan="<?= isCoordenador() ? 7 : 4 ?>" style="text-align:center;padding:20px;">
                Nenhuma entrega registrada.
            </td></tr>
        <?php endif; ?>
    </table>
</main>
</body>
</html>