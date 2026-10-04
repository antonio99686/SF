<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
exigirLogin();

$uid = $_SESSION['usuario_id'];

// Coordenador pode ver outro perfil
$perfil_id = (isCoordenador() && isset($_GET['id'])) ? (int)$_GET['id'] : $uid;

// Buscar dados do perfil
$stmt = $pdo->prepare("SELECT u.*, r.nome AS responsavel_nome, r.telefone AS responsavel_tel
                       FROM usuarios u
                       LEFT JOIN usuarios r ON r.id = u.responsavel_id
                       WHERE u.id = ?");
$stmt->execute([$perfil_id]);
$perfil = $stmt->fetch();

if (!$perfil) {
    die('Usuário não encontrado.');
}

// --- Atualizar dados próprios ---
$msg = ''; $erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $perfil_id === $uid) {
    $nome     = trim($_POST['nome']);
    $telefone = trim($_POST['telefone']);
    $endereco = trim($_POST['endereco']);
    $senha    = $_POST['senha'] ?? '';

    try {
        if ($senha) {
            $hash = password_hash($senha, PASSWORD_DEFAULT);
            $upd = $pdo->prepare("UPDATE usuarios SET nome=?, telefone=?, endereco=?, senha=? WHERE id=?");
            $upd->execute([$nome, $telefone, $endereco, $hash, $uid]);
        } else {
            $upd = $pdo->prepare("UPDATE usuarios SET nome=?, telefone=?, endereco=? WHERE id=?");
            $upd->execute([$nome, $telefone, $endereco, $uid]);
        }
        $_SESSION['nome'] = $nome;
        $msg = 'Dados atualizados!';
        // Recarregar
        $stmt->execute([$perfil_id]);
        $perfil = $stmt->fetch();
    } catch (Exception $e) { $erro = $e->getMessage(); }
}

// --- Histórico: Mensalidades ---
$stmt = $pdo->prepare("SELECT * FROM mensalidades WHERE usuario_id = ? ORDER BY mes_referencia DESC LIMIT 12");
$stmt->execute([$perfil_id]);
$historicoMensalidades = $stmt->fetchAll();

// --- Histórico: Eventos (via categoria) ---
$stmt = $pdo->prepare("SELECT * FROM eventos 
                       WHERE categoria IN ('todos', ?) 
                       ORDER BY data_evento DESC LIMIT 10");
$stmt->execute([$perfil['categoria'] ?? 'todos']);
$historicoEventos = $stmt->fetchAll();

// --- Histórico: Vestimentas ---
$stmt = $pdo->prepare("SELECT e.*, v.nome AS vestimenta_nome, v.tamanho 
                       FROM vestimenta_entregas e
                       JOIN vestimentas v ON v.id = e.vestimenta_id
                       WHERE e.usuario_id = ?
                       ORDER BY e.data_entrega DESC");
$stmt->execute([$perfil_id]);
$historicoVestimentas = $stmt->fetchAll();

// --- Totais financeiros ---
$stmt = $pdo->prepare("SELECT 
    SUM(CASE WHEN status='pago' THEN valor ELSE 0 END) AS pago,
    SUM(CASE WHEN status!='pago' THEN valor ELSE 0 END) AS pendente
    FROM mensalidades WHERE usuario_id = ?");
$stmt->execute([$perfil_id]);
$fin = $stmt->fetch();

// --- Filhos (se responsável) ---
$filhos = [];
if ($perfil['tipo'] === 'responsavel') {
    $stmt = $pdo->prepare("SELECT id, nome, categoria FROM usuarios WHERE responsavel_id = ? AND ativo=1");
    $stmt->execute([$perfil_id]);
    $filhos = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Perfil - Sentinela</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<?php include '../includes/header.php'; ?>
<main class="container">
    <h1>👤 Perfil de <?= htmlspecialchars($perfil['nome']) ?></h1>
    <span class="badge"><?= ucfirst($perfil['tipo']) ?></span>
    <?php if ($perfil['categoria']): ?>
        <span class="badge" style="background:#16a34a;"><?= ucfirst(str_replace('_','-',$perfil['categoria'])) ?></span>
    <?php endif; ?>

    <?php if ($msg): ?><div class="alert ok"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <?php if ($erro): ?><div class="alert erro"><?= htmlspecialchars($erro) ?></div><?php endif; ?>

    <!-- Cards financeiros -->
    <div class="grid-cards" style="margin-top:20px;">
        <div class="card">
            <h3>💰 Pago</h3>
            <p style="color:#16a34a;font-size:1.4rem;font-weight:bold;">
                R$ <?= number_format($fin['pago'] ?? 0, 2, ',', '.') ?>
            </p>
        </div>
        <div class="card">
            <h3>⚠️ Pendente</h3>
            <p style="color:#dc2626;font-size:1.4rem;font-weight:bold;">
                R$ <?= number_format($fin['pendente'] ?? 0, 2, ',', '.') ?>
            </p>
        </div>
    </div>

    <!-- Dados pessoais -->
    <h2 style="margin-top:30px;">📋 Dados Pessoais</h2>
    <form method="POST" style="background:#fff;padding:20px;border-radius:10px;margin-top:10px;display:grid;grid-template-columns:1fr 1fr;gap:10px;">
        <label>Nome
            <input type="text" name="nome" value="<?= htmlspecialchars($perfil['nome']) ?>" 
                   <?= $perfil_id !== $uid ? 'disabled' : '' ?> style="width:100%;padding:8px;">
        </label>
        <label>Email
            <input type="email" value="<?= htmlspecialchars($perfil['email']) ?>" disabled style="width:100%;padding:8px;background:#f0f0f0;">
        </label>
        <label>CPF
            <input type="text" value="<?= htmlspecialchars($perfil['cpf'] ?? '—') ?>" disabled style="width:100%;padding:8px;background:#f0f0f0;">
        </label>
        <label>Data Nascimento
            <input type="text" value="<?= $perfil['data_nascimento'] ? date('d/m/Y', strtotime($perfil['data_nascimento'])) : '—' ?>" disabled style="width:100%;padding:8px;background:#f0f0f0;">
        </label>
        <label>Telefone
            <input type="text" name="telefone" value="<?= htmlspecialchars($perfil['telefone'] ?? '') ?>"
                   <?= $perfil_id !== $uid ? 'disabled' : '' ?> style="width:100%;padding:8px;">
        </label>
        <label>Endereço
            <input type="text" name="endereco" value="<?= htmlspecialchars($perfil['endereco'] ?? '') ?>"
                   <?= $perfil_id !== $uid ? 'disabled' : '' ?> style="width:100%;padding:8px;">
        </label>
        <?php if ($perfil_id === $uid): ?>
            <label style="grid-column:1/-1;">Nova Senha (deixe vazio para manter)
                <input type="password" name="senha" placeholder="••••••••" style="width:100%;padding:8px;">
            </label>
            <div style="grid-column:1/-1;">
                <button class="btn btn-primary" type="submit">💾 Salvar</button>
            </div>
        <?php endif; ?>
    </form>

    <?php if ($perfil['responsavel_nome']): ?>
    <div class="alert ok" style="margin-top:15px;">
        👨‍👦 Responsável: <strong><?= htmlspecialchars($perfil['responsavel_nome']) ?></strong>
        <?= $perfil['responsavel_tel'] ? ' — ' . htmlspecialchars($perfil['responsavel_tel']) : '' ?>
    </div>
    <?php endif; ?>

    <!-- Filhos -->
    <?php if ($filhos): ?>
    <h2 style="margin-top:30px;">👨‍👩‍👧 Filhos Vinculados</h2>
    <table style="margin-top:10px;">
        <tr><th>Nome</th><th>Categoria</th><th>Ações</th></tr>
        <?php foreach ($filhos as $f): ?>
        <tr>
            <td><?= htmlspecialchars($f['nome']) ?></td>
            <td><?= ucfirst(str_replace('_','-',$f['categoria'] ?? '')) ?></td>
            <td><a class="btn btn-primary" href="perfil.php?id=<?= $f['id'] ?>">Ver</a></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php endif; ?>

    <!-- Histórico Mensalidades -->
    <h2 style="margin-top:30px;">💰 Últimas Mensalidades</h2>
    <table style="margin-top:10px;">
        <tr><th>Mês</th><th>Valor</th><th>Status</th><th>Pagamento</th></tr>
        <?php foreach ($historicoMensalidades as $m): ?>
        <tr>
            <td><?= date('m/Y', strtotime($m['mes_referencia'])) ?></td>
            <td>R$ <?= number_format($m['valor'], 2, ',', '.') ?></td>
            <td><?= ucfirst($m['status']) ?></td>
            <td><?= $m['data_pagamento'] ? date('d/m/Y', strtotime($m['data_pagamento'])) : '—' ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$historicoMensalidades): ?>
            <tr><td colspan="4" style="text-align:center;padding:15px;">Nenhuma mensalidade registrada.</td></tr>
        <?php endif; ?>
    </table>

    <!-- Histórico Vestimentas -->
    <h2 style="margin-top:30px;">👕 Vestimentas Recebidas</h2>
    <table style="margin-top:10px;">
        <tr><th>Peça</th><th>Tamanho</th><th>Data</th><th>Status</th></tr>
        <?php foreach ($historicoVestimentas as $v): ?>
        <tr>
            <td><?= htmlspecialchars($v['vestimenta_nome']) ?></td>
            <td><?= htmlspecialchars($v['tamanho'] ?: '—') ?></td>
            <td><?= $v['data_entrega'] ? date('d/m/Y', strtotime($v['data_entrega'])) : '—' ?></td>
            <td><?= $v['devolvido'] ? '✅ Devolvido' : '📦 Em uso' ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$historicoVestimentas): ?>
            <tr><td colspan="4" style="text-align:center;padding:15px;">Nenhuma vestimenta recebida.</td></tr>
        <?php endif; ?>
    </table>
    <?php include '../includes/footer.php'; ?>
</main>
</body>
</html>