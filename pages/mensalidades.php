<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
exigirLogin();

$uid = $_SESSION['usuario_id'];
$msg = ''; $erro = '';

// --- MARCAR COMO PAGA ---
if (isset($_GET['pagar'])) {
    $id = (int)$_GET['pagar'];

    // Validar permissão
    $sqlCheck = "SELECT m.* FROM mensalidades m WHERE m.id = ?";
    $p = [$id];
    if (isDancarino()) {
        $sqlCheck .= " AND m.usuario_id = ?"; $p[] = $uid;
    } elseif (isResponsavel()) {
        $sqlCheck .= " AND m.usuario_id IN (SELECT id FROM usuarios WHERE responsavel_id = ?)";
        $p[] = $uid;
    }
    $stmt = $pdo->prepare($sqlCheck);
    $stmt->execute($p);
    if ($stmt->fetch()) {
        $upd = $pdo->prepare("UPDATE mensalidades SET status='pago', data_pagamento=CURDATE() WHERE id=?");
        $upd->execute([$id]);
        header('Location: mensalidades.php?ok=1');
        exit;
    }
}

// --- CADASTRAR (só coordenador) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isCoordenador()) {
    $usuario_id = (int)$_POST['usuario_id'];
    $mes        = $_POST['mes_referencia']; // formato YYYY-MM
    $valor      = (float)$_POST['valor'];
    $status     = $_POST['status'];
    $obs        = trim($_POST['observacao']);

    try {
        $stmt = $pdo->prepare("INSERT INTO mensalidades (usuario_id, mes_referencia, valor, status, observacao) VALUES (?,?,?,?,?)");
        $stmt->execute([$usuario_id, $mes.'-01', $valor, $status, $obs]);
        $msg = 'Mensalidade cadastrada!';
    } catch (Exception $e) {
        $erro = 'Erro: ' . $e->getMessage();
    }
}

// --- GERAR EM LOTE (só coordenador) ---
if (isset($_POST['gerar_lote']) && isCoordenador()) {
    $mes   = $_POST['mes_lote'];
    $valor = (float)$_POST['valor_lote'];
    $cat   = $_POST['cat_lote'] ?: null;

    $sql = "SELECT id FROM usuarios WHERE tipo='dancarino' AND ativo=1";
    $p = [];
    if ($cat) { $sql .= " AND categoria=?"; $p[] = $cat; }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($p);
    $dancarinos = $stmt->fetchAll();

    $ins = $pdo->prepare("INSERT IGNORE INTO mensalidades (usuario_id, mes_referencia, valor, status) VALUES (?,?,?,'pendente')");
    $count = 0;
    foreach ($dancarinos as $d) {
        // Evitar duplicar
        $chk = $pdo->prepare("SELECT id FROM mensalidades WHERE usuario_id=? AND mes_referencia=?");
        $chk->execute([$d['id'], $mes.'-01']);
        if (!$chk->fetch()) {
            $ins->execute([$d['id'], $mes.'-01', $valor]);
            $count++;
        }
    }
    $msg = "$count mensalidades geradas!";
}

// --- LISTAGEM ---
$where = "1=1";
$params = [];
if (isDancarino()) {
    $where .= " AND m.usuario_id = ?";
    $params[] = $uid;
} elseif (isResponsavel()) {
    $where .= " AND m.usuario_id IN (SELECT id FROM usuarios WHERE responsavel_id = ?)";
    $params[] = $uid;
}
if (!empty($_GET['mes'])) {
    $where .= " AND DATE_FORMAT(m.mes_referencia,'%Y-%m') = ?";
    $params[] = $_GET['mes'];
}
if (!empty($_GET['status'])) {
    $where .= " AND m.status = ?";
    $params[] = $_GET['status'];
}
if (isCoordenador() && !empty($_GET['usuario_id'])) {
    $where .= " AND m.usuario_id = ?";
    $params[] = (int)$_GET['usuario_id'];
}

$sql = "SELECT m.*, u.nome AS usuario_nome, u.categoria 
        FROM mensalidades m 
        JOIN usuarios u ON u.id = m.usuario_id
        WHERE $where
        ORDER BY m.mes_referencia DESC, u.nome";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$mensalidades = $stmt->fetchAll();

// Lista de dançarinos (para o coordenador)
$dancarinos = isCoordenador() 
    ? $pdo->query("SELECT id, nome, categoria FROM usuarios WHERE tipo='dancarino' AND ativo=1 ORDER BY nome")->fetchAll()
    : [];

$cores = ['pendente'=>'#f59e0b', 'pago'=>'#16a34a', 'atrasado'=>'#dc2626'];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Mensalidades - Sentinela</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<?php include '../includes/header.php'; ?>
<main class="container">
    <h1>💰 Mensalidades</h1>

    <?php if (isset($_GET['ok'])): ?><div class="alert ok">Mensalidade atualizada!</div><?php endif; ?>
    <?php if ($msg): ?><div class="alert ok"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <?php if ($erro): ?><div class="alert erro"><?= htmlspecialchars($erro) ?></div><?php endif; ?>

    <?php if (isCoordenador()): ?>
    <!-- Cadastro individual -->
    <details style="margin:20px 0; background:#fff; padding:20px; border-radius:10px;">
        <summary style="cursor:pointer; font-weight:bold;">➕ Cadastrar Mensalidade Individual</summary>
        <form method="POST" style="margin-top:15px; display:grid; grid-template-columns:1fr 1fr 1fr; gap:10px;">
            <select name="usuario_id" required>
                <option value="">Dançarino *</option>
                <?php foreach ($dancarinos as $d): ?>
                    <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['nome']) ?> (<?= $d['categoria'] ?>)</option>
                <?php endforeach; ?>
            </select>
            <input type="month" name="mes_referencia" required>
            <input type="number" name="valor" step="0.01" placeholder="Valor *" required>
            <select name="status">
                <option value="pendente">Pendente</option>
                <option value="pago">Pago</option>
                <option value="atrasado">Atrasado</option>
            </select>
            <input type="text" name="observacao" placeholder="Observação" style="grid-column:span 2;">
            <div style="grid-column:1/-1;">
                <button class="btn btn-primary" type="submit">💾 Salvar</button>
            </div>
        </form>
    </details>

    <!-- Gerar em lote -->
    <details style="margin:20px 0; background:#fff; padding:20px; border-radius:10px;">
        <summary style="cursor:pointer; font-weight:bold;">⚡ Gerar Mensalidades em Lote</summary>
        <form method="POST" style="margin-top:15px; display:grid; grid-template-columns:1fr 1fr 1fr; gap:10px;">
            <input type="month" name="mes_lote" required>
            <input type="number" name="valor_lote" step="0.01" placeholder="Valor *" required>
            <select name="cat_lote">
                <option value="">Todas as categorias</option>
                <option value="pre_mirim">Pré-Mirim</option>
                <option value="mirim">Mirim</option>
                <option value="juvenil">Juvenil</option>
                <option value="adulto">Adulto</option>
            </select>
            <div style="grid-column:1/-1;">
                <button class="btn btn-success" type="submit" onclick="return confirm('Gerar mensalidades para todos os dançarinos selecionados?')">⚡ Gerar</button>
            </div>
        </form>
    </details>
    <?php endif; ?>

    <!-- FILTROS -->
    <form method="GET" style="margin:20px 0; display:flex; gap:10px; flex-wrap:wrap;">
        <input type="month" name="mes" value="<?= htmlspecialchars($_GET['mes'] ?? '') ?>" style="padding:8px;">
        <select name="status" style="padding:8px;">
            <option value="">Todos os status</option>
            <option value="pendente" <?= ($_GET['status']??'')==='pendente'?'selected':'' ?>>Pendente</option>
            <option value="pago"     <?= ($_GET['status']??'')==='pago'?'selected':'' ?>>Pago</option>
            <option value="atrasado" <?= ($_GET['status']??'')==='atrasado'?'selected':'' ?>>Atrasado</option>
        </select>
        <?php if (isCoordenador()): ?>
        <select name="usuario_id" style="padding:8px;">
            <option value="">Todos os dançarinos</option>
            <?php foreach ($dancarinos as $d): ?>
                <option value="<?= $d['id'] ?>" <?= ($_GET['usuario_id']??'')==$d['id']?'selected':'' ?>>
                    <?= htmlspecialchars($d['nome']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php endif; ?>
        <button class="btn btn-primary">🔍 Filtrar</button>
        <a class="btn" href="mensalidades.php" style="background:#999;color:#fff;">Limpar</a>
    </form>

    <!-- TABELA -->
    <table>
        <tr>
            <th>Dançarino</th><th>Categoria</th><th>Mês</th>
            <th>Valor</th><th>Status</th><th>Pagamento</th><th>Ações</th>
        </tr>
        <?php foreach ($mensalidades as $m): ?>
        <tr>
            <td><?= htmlspecialchars($m['usuario_nome']) ?></td>
            <td><?= ucfirst(str_replace('_','-',$m['categoria'] ?? '')) ?></td>
            <td><?= date('m/Y', strtotime($m['mes_referencia'])) ?></td>
            <td>R$ <?= number_format($m['valor'], 2, ',', '.') ?></td>
            <td>
                <span style="background:<?= $cores[$m['status']] ?>;color:#fff;padding:4px 10px;border-radius:12px;font-size:.8rem;">
                    <?= ucfirst($m['status']) ?>
                </span>
            </td>
            <td><?= $m['data_pagamento'] ? date('d/m/Y', strtotime($m['data_pagamento'])) : '—' ?></td>
            <td>
                <?php if ($m['status'] !== 'pago'): ?>
                    <button class="btn btn-success" onclick="pagarAjax(<?= $m['id'] ?>, this)">✅ Pagar</button>
                <?php else: ?>
                    <span style="color:#16a34a;">✔️</span>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$mensalidades): ?>
            <tr><td colspan="7" style="text-align:center;padding:20px;">Nenhuma mensalidade encontrada.</td></tr>
        <?php endif; ?>
    </table>
</main>
<?php include '../includes/footer.php'; ?>
<script src="../assets/js/api.js"></script>
<script>
async function pagarAjax(id, btn) {
    if (!confirm('Marcar como PAGA?')) return;
    try {
        await API.mensalidades.pagar(id);
        toast('Mensalidade paga!', 'ok');
        btn.outerHTML = '<span style="color:#16a34a;">✔️</span>';
    } catch (e) {
        toast(e.message, 'erro');
    }
}
</script>
</body>
</html>