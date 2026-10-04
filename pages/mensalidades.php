<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
exigirLogin();

$uid = $_SESSION['usuario_id'];
$msg = ''; $erro = '';

// --- MARCAR COMO PAGA (via GET - fallback) ---
if (isset($_GET['pagar'])) {
    $id = (int)$_GET['pagar'];

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

// --- POST: CADASTRAR INDIVIDUAL (só coordenador) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isCoordenador() && isset($_POST['acao']) && $_POST['acao'] === 'cadastrar') {
    csrfProtegerPost();

    $usuario_id = (int)($_POST['usuario_id'] ?? 0);
    $mes        = trim($_POST['mes_referencia'] ?? '');
    $valor      = (float)($_POST['valor'] ?? 0);
    $status     = $_POST['status'] ?? 'pendente';
    $obs        = trim($_POST['observacao'] ?? '');

    try {
        if (!$usuario_id) throw new Exception('Selecione o dançarino.');
        if (!$mes)        throw new Exception('Informe o mês de referência.');
        if ($valor <= 0)  throw new Exception('Informe um valor válido.');
        if (!in_array($status, ['pendente','pago','atrasado'], true)) {
            throw new Exception('Status inválido.');
        }

        $stmt = $pdo->prepare("
            INSERT INTO mensalidades (usuario_id, mes_referencia, valor, status, observacao) 
            VALUES (?,?,?,?,?)
        ");
        $stmt->execute([$usuario_id, $mes . '-01', $valor, $status, $obs]);
        $msg = '✅ Mensalidade cadastrada!';
    } catch (Exception $e) {
        $erro = 'Erro: ' . $e->getMessage();
    }
}

// --- POST: GERAR EM LOTE (só coordenador) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isCoordenador() && isset($_POST['acao']) && $_POST['acao'] === 'lote') {
    csrfProtegerPost();

    $mes   = trim($_POST['mes_lote'] ?? '');
    $valor = (float)($_POST['valor_lote'] ?? 0);
    $cat   = $_POST['cat_lote'] ?? '';

    try {
        if (!$mes)       throw new Exception('Informe o mês de referência.');
        if ($valor <= 0) throw new Exception('Informe um valor válido.');

        $sql = "SELECT id FROM usuarios WHERE tipo='dancarino' AND ativo=1";
        $p = [];
        if ($cat) { $sql .= " AND categoria = ?"; $p[] = $cat; }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($p);
        $dancarinos = $stmt->fetchAll();

        $ins = $pdo->prepare("
            INSERT IGNORE INTO mensalidades (usuario_id, mes_referencia, valor, status) 
            VALUES (?,?,?,'pendente')
        ");
        $count = 0;
        foreach ($dancarinos as $d) {
            $chk = $pdo->prepare("SELECT id FROM mensalidades WHERE usuario_id=? AND mes_referencia=?");
            $chk->execute([$d['id'], $mes . '-01']);
            if (!$chk->fetch()) {
                $ins->execute([$d['id'], $mes . '-01', $valor]);
                $count++;
            }
        }
        $msg = "✅ {$count} mensalidade(s) gerada(s)!";
    } catch (Exception $e) {
        $erro = 'Erro: ' . $e->getMessage();
    }
}

// --- LISTAGEM ---
$where  = "1=1";
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

$cores = [
    'pendente' => '#C9A961',
    'pago'     => '#4A7C59',
    'atrasado' => '#A85252',
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mensalidades · Sentinela da Fronteira</title>
    <link rel="icon" href="../assets/img/logo.png">
    <link rel="stylesheet" href="../assets/css/style.css">
    <meta name="csrf-token" content="<?= csrfToken() ?>">
</head>
<body>
<?php include '../includes/header.php'; ?>
<main class="container">
    <h1>💰 Mensalidades</h1>

    <?php if (isset($_GET['ok'])): ?>
        <div class="alert ok">✅ Mensalidade atualizada!</div>
    <?php endif; ?>
    <?php if ($msg): ?><div class="alert ok"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <?php if ($erro): ?><div class="alert erro">⚠️ <?= htmlspecialchars($erro) ?></div><?php endif; ?>

    <?php if (isCoordenador()): ?>
    <!-- ========== CADASTRO INDIVIDUAL ========== -->
    <details style="margin:20px 0;">
        <summary>➕ Cadastrar Mensalidade Individual</summary>
        <form method="POST" style="margin-top:15px; display:grid; grid-template-columns:1fr 1fr 1fr; gap:10px;">
            <?= csrfCampo() ?>
            <input type="hidden" name="acao" value="cadastrar">

            <label style="grid-column:1/-1;">Dançarino *
                <select name="usuario_id" required>
                    <option value="">Selecione...</option>
                    <?php foreach ($dancarinos as $d): ?>
                        <option value="<?= $d['id'] ?>">
                            <?= htmlspecialchars($d['nome']) ?> (<?= formatarCategoria($d['categoria']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>Mês de referência *
                <input type="month" name="mes_referencia" required>
            </label>
            <label>Valor (R$) *
                <input type="number" name="valor" step="0.01" min="0.01" placeholder="0,00" required>
            </label>
            <label>Status
                <select name="status">
                    <option value="pendente">Pendente</option>
                    <option value="pago">Pago</option>
                    <option value="atrasado">Atrasado</option>
                </select>
            </label>

            <label style="grid-column:1/-1;">Observação
                <input type="text" name="observacao" placeholder="Opcional">
            </label>

            <div style="grid-column:1/-1;">
                <button class="btn btn-primary" type="submit">💾 Salvar</button>
            </div>
        </form>
    </details>

    <!-- ========== GERAR EM LOTE ========== -->
    <details style="margin:20px 0;">
        <summary>⚡ Gerar Mensalidades em Lote</summary>
        <form method="POST" style="margin-top:15px; display:grid; grid-template-columns:1fr 1fr 1fr; gap:10px;">
            <?= csrfCampo() ?>
            <input type="hidden" name="acao" value="lote">

            <label>Mês de referência *
                <input type="month" name="mes_lote" required>
            </label>
            <label>Valor (R$) *
                <input type="number" name="valor_lote" step="0.01" min="0.01" placeholder="0,00" required>
            </label>
            <label>Categoria
                <select name="cat_lote">
                    <option value="">Todas</option>
                    <option value="pre_mirim">Pré-Mirim</option>
                    <option value="mirim">Mirim</option>
                    <option value="juvenil">Juvenil</option>
                    <option value="adulto">Adulto</option>
                </select>
            </label>

            <div style="grid-column:1/-1;">
                <button class="btn btn-success" type="submit"
                        onclick="return confirm('Gerar mensalidades para todos os dançarinos selecionados?')">
                    ⚡ Gerar em Lote
                </button>
            </div>
        </form>
    </details>
    <?php endif; ?>

    <!-- ========== FILTROS ========== -->
    <form method="GET" style="margin:20px 0; display:flex; gap:10px; flex-wrap:wrap; align-items:end;">
        <label style="flex:0 0 auto;">Mês
            <input type="month" name="mes" value="<?= htmlspecialchars($_GET['mes'] ?? '') ?>">
        </label>
        <label style="flex:0 0 auto;">Status
            <select name="status">
                <option value="">Todos</option>
                <option value="pendente" <?= ($_GET['status'] ?? '') === 'pendente' ? 'selected' : '' ?>>Pendente</option>
                <option value="pago"     <?= ($_GET['status'] ?? '') === 'pago'     ? 'selected' : '' ?>>Pago</option>
                <option value="atrasado" <?= ($_GET['status'] ?? '') === 'atrasado' ? 'selected' : '' ?>>Atrasado</option>
            </select>
        </label>
        <?php if (isCoordenador()): ?>
        <label style="flex:1;">Dançarino
            <select name="usuario_id">
                <option value="">Todos</option>
                <?php foreach ($dancarinos as $d): ?>
                    <option value="<?= $d['id'] ?>"
                        <?= ($_GET['usuario_id'] ?? '') == $d['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($d['nome']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <?php endif; ?>

        <button class="btn btn-primary">🔍 Filtrar</button>
        <a class="btn btn-ghost" href="mensalidades.php">Limpar</a>
    </form>

    <!-- ========== TABELA ========== -->
    <table>
        <thead>
            <tr>
                <th>Dançarino</th>
                <th>Categoria</th>
                <th>Mês</th>
                <th>Valor</th>
                <th>Status</th>
                <th>Pagamento</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($mensalidades as $m): ?>
            <tr>
                <td><?= htmlspecialchars($m['usuario_nome']) ?></td>
                <td><?= formatarCategoria($m['categoria']) ?></td>
                <td><?= date('m/Y', strtotime($m['mes_referencia'])) ?></td>
                <td><?= formatarMoeda($m['valor']) ?></td>
                <td>
                    <span style="background:<?= $cores[$m['status']] ?>;color:#fff;padding:4px 10px;border-radius:12px;font-size:.78rem;font-weight:600;">
                        <?= ucfirst($m['status']) ?>
                    </span>
                </td>
                <td><?= $m['data_pagamento'] ? date('d/m/Y', strtotime($m['data_pagamento'])) : '—' ?></td>
                <td>
                    <?php if ($m['status'] !== 'pago'): ?>
                        <button class="btn btn-success" onclick="pagarAjax(<?= $m['id'] ?>, this)">✅ Pagar</button>
                    <?php else: ?>
                        <span style="color:#4A7C59;">✔️</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$mensalidades): ?>
                <tr>
                    <td colspan="7" style="text-align:center;padding:24px;color:#8A8F8C;">
                        Nenhuma mensalidade encontrada.
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</main>

<?php include '../includes/footer.php'; ?>

<script src="../assets/js/api.js"></script>
<script>
async function pagarAjax(id, btn) {
    if (!confirm('Marcar esta mensalidade como PAGA?')) return;
    btn.disabled = true;
    try {
        await API.mensalidades.pagar(id);
        toast('Mensalidade paga!', 'ok');
        btn.outerHTML = '<span style="color:#4A7C59;">✔️</span>';
    } catch (e) {
        toast(e.message, 'erro');
        btn.disabled = false;
    }
}
</script>
</body>
</html>