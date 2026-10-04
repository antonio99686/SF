<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
exigirCoordenador();

$msg = '';
$erro = '';

// --- EXCLUIR (soft delete) ---
if (isset($_GET['desativar'])) {
    $stmt = $pdo->prepare("UPDATE usuarios SET ativo = 0 WHERE id = ?");
    $stmt->execute([(int)$_GET['desativar']]);
    header('Location: usuarios.php?ok=desativado');
    exit;
}

if (isset($_GET['ativar'])) {
    $stmt = $pdo->prepare("UPDATE usuarios SET ativo = 1 WHERE id = ?");
    $stmt->execute([(int)$_GET['ativar']]);
    header('Location: usuarios.php?ok=ativado');
    exit;
}

// --- CADASTRAR / EDITAR ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id             = $_POST['id'] ?? null;
    $nome           = trim($_POST['nome']);
    $email          = trim($_POST['email']);
    $cpf            = trim($_POST['cpf']);
    $data_nasc      = $_POST['data_nascimento'] ?: null;
    $telefone       = trim($_POST['telefone']);
    $endereco       = trim($_POST['endereco']);
    $tipo           = $_POST['tipo'];
    $categoria      = $_POST['categoria'] ?: null;
    $responsavel_id = $_POST['responsavel_id'] ?: null;
    $senha          = $_POST['senha'] ?? '';

    // Regras: categoria só para dançarino; responsavel_id só para dançarino menor
    if ($tipo !== 'dancarino') {
        $categoria = null;
        $responsavel_id = null;
    }

    // Se for dançarino maior de idade, limpa responsável
    if ($tipo === 'dancarino' && $data_nasc) {
        $idade = (new DateTime($data_nasc))->diff(new DateTime())->y;
        if ($idade >= 18) $responsavel_id = null;
    }

    try {
        if ($id) {
            // Atualizar
            if ($senha) {
                $hash = password_hash($senha, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE usuarios SET nome=?, email=?, cpf=?, data_nascimento=?, telefone=?, endereco=?, tipo=?, categoria=?, responsavel_id=?, senha=? WHERE id=?");
                $stmt->execute([$nome,$email,$cpf,$data_nasc,$telefone,$endereco,$tipo,$categoria,$responsavel_id,$hash,$id]);
            } else {
                $stmt = $pdo->prepare("UPDATE usuarios SET nome=?, email=?, cpf=?, data_nascimento=?, telefone=?, endereco=?, tipo=?, categoria=?, responsavel_id=? WHERE id=?");
                $stmt->execute([$nome,$email,$cpf,$data_nasc,$telefone,$endereco,$tipo,$categoria,$responsavel_id,$id]);
            }
            $msg = 'Usuário atualizado com sucesso!';
        } else {
            // Criar
            if (!$senha) throw new Exception('Senha obrigatória para novo usuário.');
            $hash = password_hash($senha, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO usuarios (nome,email,senha,cpf,data_nascimento,telefone,endereco,tipo,categoria,responsavel_id) VALUES (?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute([$nome,$email,$hash,$cpf,$data_nasc,$telefone,$endereco,$tipo,$categoria,$responsavel_id]);
            $msg = 'Usuário cadastrado com sucesso!';
        }
    } catch (Exception $e) {
        $erro = 'Erro: ' . $e->getMessage();
    }
}

// --- FILTROS ---
$busca = trim($_GET['busca'] ?? '');
$filtroTipo = $_GET['tipo'] ?? '';
$filtroCat  = $_GET['categoria'] ?? '';

$sql = "SELECT u.*, r.nome AS responsavel_nome 
        FROM usuarios u 
        LEFT JOIN usuarios r ON r.id = u.responsavel_id 
        WHERE 1=1";
$params = [];

if ($busca) {
    $sql .= " AND (u.nome LIKE ? OR u.email LIKE ? OR u.cpf LIKE ?)";
    $params[] = "%$busca%"; $params[] = "%$busca%"; $params[] = "%$busca%";
}
if ($filtroTipo) { $sql .= " AND u.tipo = ?"; $params[] = $filtroTipo; }
if ($filtroCat)  { $sql .= " AND u.categoria = ?"; $params[] = $filtroCat; }
$sql .= " ORDER BY u.nome";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$usuarios = $stmt->fetchAll();

// Lista de responsáveis para o select
$responsaveis = $pdo->query("SELECT id, nome FROM usuarios WHERE tipo='responsavel' AND ativo=1 ORDER BY nome")->fetchAll();

// Edição
$editando = null;
if (isset($_GET['editar'])) {
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id=?");
    $stmt->execute([(int)$_GET['editar']]);
    $editando = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Usuários - Sentinela</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<?php include '../includes/header.php'; ?>
<main class="container">
    <h1>👥 Gestão de Usuários</h1>

    <?php if (isset($_GET['ok'])): ?>
        <div class="alert ok">Ação realizada com sucesso!</div>
    <?php endif; ?>
    <?php if ($msg): ?><div class="alert ok"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <?php if ($erro): ?><div class="alert erro"><?= htmlspecialchars($erro) ?></div><?php endif; ?>

    <!-- FORMULÁRIO -->
    <details <?= $editando ? 'open' : '' ?> style="margin:20px 0; background:#fff; padding:20px; border-radius:10px;">
        <summary style="cursor:pointer; font-weight:bold;">
            <?= $editando ? '✏️ Editando: '.htmlspecialchars($editando['nome']) : '➕ Cadastrar Novo Usuário' ?>
        </summary>
        <form method="POST" style="margin-top:15px; display:grid; grid-template-columns:1fr 1fr; gap:10px;">
            <input type="hidden" name="id" value="<?= $editando['id'] ?? '' ?>">

            <input type="text" name="nome" placeholder="Nome completo *" required
                   value="<?= htmlspecialchars($editando['nome'] ?? '') ?>">
            <input type="email" name="email" placeholder="Email *" required
                   value="<?= htmlspecialchars($editando['email'] ?? '') ?>">
            <input type="text" name="cpf" placeholder="CPF"
                   value="<?= htmlspecialchars($editando['cpf'] ?? '') ?>">
            <input type="date" name="data_nascimento" placeholder="Data nascimento"
                   value="<?= $editando['data_nascimento'] ?? '' ?>">
            <input type="text" name="telefone" placeholder="Telefone"
                   value="<?= htmlspecialchars($editando['telefone'] ?? '') ?>">
            <input type="text" name="endereco" placeholder="Endereço"
                   value="<?= htmlspecialchars($editando['endereco'] ?? '') ?>">

            <select name="tipo" id="tipoSelect" required onchange="toggleCampos()">
                <option value="">Tipo *</option>
                <option value="coordenador"  <?= ($editando['tipo']??'')==='coordenador'?'selected':'' ?>>Coordenador</option>
                <option value="dancarino"    <?= ($editando['tipo']??'')==='dancarino'?'selected':'' ?>>Dançarino</option>
                <option value="responsavel"  <?= ($editando['tipo']??'')==='responsavel'?'selected':'' ?>>Responsável</option>
            </select>

            <select name="categoria" id="catSelect">
                <option value="">Categoria (dançarino)</option>
                <option value="pre_mirim" <?= ($editando['categoria']??'')==='pre_mirim'?'selected':'' ?>>Pré-Mirim</option>
                <option value="mirim"     <?= ($editando['categoria']??'')==='mirim'?'selected':'' ?>>Mirim</option>
                <option value="juvenil"   <?= ($editando['categoria']??'')==='juvenil'?'selected':'' ?>>Juvenil</option>
                <option value="adulto"    <?= ($editando['categoria']??'')==='adulto'?'selected':'' ?>>Adulto</option>
            </select>

            <select name="responsavel_id" id="respSelect">
                <option value="">Responsável (se menor de idade)</option>
                <?php foreach ($responsaveis as $r): ?>
                    <option value="<?= $r['id'] ?>" <?= ($editando['responsavel_id']??'')==$r['id']?'selected':'' ?>>
                        <?= htmlspecialchars($r['nome']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <input type="password" name="senha" placeholder="<?= $editando ? 'Nova senha (deixe vazio p/ manter)' : 'Senha *' ?>"
                   <?= $editando ? '' : 'required' ?>>

            <div style="grid-column:1/-1; display:flex; gap:10px;">
                <button class="btn btn-primary" type="submit">💾 Salvar</button>
                <?php if ($editando): ?>
                    <a class="btn" href="usuarios.php" style="background:#666;color:#fff;">Cancelar</a>
                <?php endif; ?>
            </div>
        </form>
    </details>

    <!-- FILTROS -->
    <form method="GET" style="margin:20px 0; display:flex; gap:10px; flex-wrap:wrap;">
        <input type="text" name="busca" placeholder="Buscar por nome, email, CPF"
               value="<?= htmlspecialchars($busca) ?>" style="flex:1;padding:8px;">
        <select name="tipo" style="padding:8px;">
            <option value="">Todos os tipos</option>
            <option value="coordenador" <?= $filtroTipo==='coordenador'?'selected':'' ?>>Coordenador</option>
            <option value="dancarino"   <?= $filtroTipo==='dancarino'?'selected':'' ?>>Dançarino</option>
            <option value="responsavel" <?= $filtroTipo==='responsavel'?'selected':'' ?>>Responsável</option>
        </select>
        <select name="categoria" style="padding:8px;">
            <option value="">Todas as categorias</option>
            <option value="pre_mirim" <?= $filtroCat==='pre_mirim'?'selected':'' ?>>Pré-Mirim</option>
            <option value="mirim"     <?= $filtroCat==='mirim'?'selected':'' ?>>Mirim</option>
            <option value="juvenil"   <?= $filtroCat==='juvenil'?'selected':'' ?>>Juvenil</option>
            <option value="adulto"    <?= $filtroCat==='adulto'?'selected':'' ?>>Adulto</option>
        </select>
        <button class="btn btn-primary">🔍 Filtrar</button>
        <a class="btn" href="usuarios.php" style="background:#999;color:#fff;">Limpar</a>
    </form>

    <!-- TABELA -->
    <table>
        <tr>
            <th>Nome</th><th>Email</th><th>Tipo</th><th>Categoria</th>
            <th>Responsável</th><th>Status</th><th>Ações</th>
        </tr>
        <?php foreach ($usuarios as $u): ?>
        <tr style="<?= !$u['ativo'] ? 'opacity:.5;' : '' ?>">
            <td><?= htmlspecialchars($u['nome']) ?></td>
            <td><?= htmlspecialchars($u['email']) ?></td>
            <td><span class="badge"><?= $u['tipo'] ?></span></td>
            <td><?= $u['categoria'] ? ucfirst(str_replace('_','-',$u['categoria'])) : '—' ?></td>
            <td><?= htmlspecialchars($u['responsavel_nome'] ?? '—') ?></td>
            <td><?= $u['ativo'] ? '✅ Ativo' : '⛔ Inativo' ?></td>
            <td>
                <a class="btn btn-primary" href="?editar=<?= $u['id'] ?>">✏️</a>
                <?php if ($u['ativo']): ?>
                    <a class="btn btn-danger" href="?desativar=<?= $u['id'] ?>"
                       onclick="return confirm('Desativar este usuário?')">⛔</a>
                <?php else: ?>
                    <a class="btn btn-success" href="?ativar=<?= $u['id'] ?>">✅</a>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$usuarios): ?>
            <tr><td colspan="7" style="text-align:center;padding:20px;">Nenhum usuário encontrado.</td></tr>
        <?php endif; ?>
    </table>
</main>

<script>
function toggleCampos() {
    const tipo = document.getElementById('tipoSelect').value;
    const cat  = document.getElementById('catSelect');
    const resp = document.getElementById('respSelect');
    const isDancer = tipo === 'dancarino';
    cat.disabled  = !isDancer;
    resp.disabled = !isDancer;
    if (!isDancer) { cat.value = ''; resp.value = ''; }
}
document.addEventListener('DOMContentLoaded', toggleCampos);
</script>
</body>
</html>