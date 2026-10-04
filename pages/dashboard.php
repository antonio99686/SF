<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
exigirLogin();

$tipo = $_SESSION['tipo'];
$uid  = $_SESSION['usuario_id'];

$totalEventos     = $pdo->query("SELECT COUNT(*) FROM eventos")->fetchColumn();
$totalUsuarios    = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE ativo=1")->fetchColumn();
$pendentes        = $pdo->query("SELECT COUNT(*) FROM mensalidades WHERE status != 'pago'")->fetchColumn();
$vestimentasTotal = $pdo->query("SELECT COALESCE(SUM(quantidade),0) FROM vestimentas")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard · Sentinela da Fronteira</title>
    <link rel="icon" href="../assets/img/logo.png">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<?php include '../includes/header.php'; ?>

<main class="container">
    <h1>Olá, <?= htmlspecialchars($_SESSION['nome']) ?>! 👋</h1>
    <span class="badge"><?= ucfirst($tipo) ?></span>
    <?php if (!empty($_SESSION['categoria'])): ?>
        <span class="badge amarelo">
            <?= ucfirst(str_replace('_','-',$_SESSION['categoria'])) ?>
        </span>
    <?php endif; ?>

    <div class="grid-cards">
        <a href="eventos.php" class="card">
            <h3>🎉 Eventos</h3>
            <p><?= $totalEventos ?> cadastrados</p>
        </a>
        <a href="mensalidades.php" class="card destaque">
            <h3>💰 Mensalidades</h3>
            <p><?= $pendentes ?> pendentes</p>
        </a>
        <a href="vestimentas.php" class="card">
            <h3>👕 Vestimentas</h3>
            <p><?= $vestimentasTotal ?> em estoque</p>
        </a>
        <?php if (isCoordenador()): ?>
        <a href="usuarios.php" class="card">
            <h3>👥 Usuários</h3>
            <p><?= $totalUsuarios ?> ativos</p>
        </a>
        <a href="relatorio.php" class="card">
            <h3>📊 Relatório</h3>
            <p>Financeiro anual</p>
        </a>
        <?php endif; ?>
        <a href="perfil.php" class="card">
            <h3>👤 Meu Perfil</h3>
            <p>Ver e editar dados</p>
        </a>
    </div>
</main>

<?php include '../includes/footer.php'; ?>
</body>
</html>