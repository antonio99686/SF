<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

// 🔴 MARCA ESTA ÁREA COMO ADMIN (usado pela lógica de bloqueio abaixo)
$areaAdmin = true;

// Se já logado como coordenador, vai direto pro dashboard.
// Se estiver logado como outro tipo, encerra a sessão para não confundir.
if (usuarioLogado()) {
    if (isCoordenador()) {
        header('Location: ../../pages/dashboard.php');
        exit;
    } else {
        // Estava logado como dançarino/responsável → desloga
        session_destroy();
        session_start();
    }
}

$erro = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 🔐 Valida CSRF
    csrfProtegerPost();

    $email = strtolower(trim($_POST['email'] ?? ''));
    $senha = $_POST['senha'] ?? '';

    // 1) Bloqueio por tentativas
    [$bloqueado, $minutos] = loginEstaBloqueado($pdo, $email);

    if ($bloqueado) {
        $erro = "⛔ Muitas tentativas. Tente novamente em {$minutos} minuto(s).";
    } elseif ($email && $senha) {

        // 🔴 FILTRO CRÍTICO: apenas coordenadores ativos podem entrar aqui
        $stmt = $pdo->prepare("
            SELECT * FROM usuarios 
            WHERE email = ? AND ativo = 1 AND tipo = 'coordenador'
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($senha, $user['senha'])) {

            // Sucesso
            registrarTentativa($pdo, $email, true);

            $_SESSION['usuario_id']    = $user['id'];
            $_SESSION['nome']          = $user['nome'];
            $_SESSION['tipo']          = $user['tipo'];
            $_SESSION['categoria']     = $user['categoria'];
            $_SESSION['ultimo_acesso'] = 'admin';

            redirecionarAposLogin();

        } else {
            // Falha
            registrarTentativa($pdo, $email, false);
            $restam = tentativasRestantes($pdo, $email);

            // Mensagem genérica (não revela se o email existe)
            $erro = "Credenciais inválidas. Tentativas restantes: {$restam}.";
        }

    } else {
        $erro = 'Preencha todos os campos.';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Área do Coordenador · Sentinela da Fronteira</title>
    <link rel="icon" href="../../assets/img/logo.png">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <!-- 🔐 Token CSRF disponível para o JS -->
    <meta name="csrf-token" content="<?= csrfToken() ?>">
</head>
<body class="login-page admin-login-page">
    <div class="login-box admin-box">
        <img src="../../assets/img/logo.png" alt="Sentinela da Fronteira" class="logo-login">

        <div class="selo-admin">🔒 ACESSO RESTRITO</div>

        <h1>Área do Coordenador</h1>
        <p class="sub">PIQUETE SENTINELA DA FRONTEIRA</p>

        <?php if ($erro): ?>
            <div class="alert erro"><?= $erro ?></div>
        <?php endif; ?>

        <form method="POST" autocomplete="off">
            <?= csrfCampo() ?>

            <input type="email" name="email"
                   placeholder="✉️ Email do coordenador"
                   value="<?= htmlspecialchars($email) ?>"
                   required autofocus>

            <input type="password" name="senha"
                   placeholder="🔒 Senha"
                   required>

            <button type="submit" class="btn-admin">Acessar Painel</button>
        </form>

        <div class="login-links">
            <p>
                <a href="login.php" class="link-voltar">
                    ← Voltar para área comum
                </a>
            </p>
        </div>

        <p class="frase-login">
            Acesso monitorado · Uso exclusivo da coordenação
        </p>
    </div>

    <script src="../../assets/js/api.js"></script>
</body>
</html>