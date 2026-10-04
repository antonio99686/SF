<?php
require_once '../config/database.php';
require_once '../includes/auth.php';

// 🔴 MARCA ESTA ÁREA COMO PÚBLICA (dançarino / responsável)
$areaAdmin = false;

// Se já logado, redireciona conforme o tipo
if (usuarioLogado()) {
    if (isCoordenador()) {
        // Coordenador logado tentando abrir a área pública → manda pra área dele
        header('Location: admin/login.php');
    } else {
        header('Location: ../../pages/dashboard.php');
    }
    exit;
}

$erro  = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 🔐 Valida token CSRF
    csrfProtegerPost();

    $email = strtolower(trim($_POST['email'] ?? ''));
    $senha = $_POST['senha'] ?? '';

    // 1) Bloqueio por tentativas
    [$bloqueado, $minutos] = loginEstaBloqueado($pdo, $email);

    if ($bloqueado) {
        $erro = "⛔ Muitas tentativas. Tente novamente em {$minutos} minuto(s).";

    } elseif ($email && $senha) {

        // 🔴 FILTRO: apenas dançarino ou responsável ativos
        $stmt = $pdo->prepare("
            SELECT * FROM usuarios 
            WHERE email = ? 
              AND ativo = 1 
              AND tipo IN ('dancarino', 'responsavel')
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($senha, $user['senha'])) {

            // Sucesso
            registrarTentativa($pdo, $email, true);

            // 🛡️ Regenera o ID da sessão (evita session fixation)
            session_regenerate_id(true);

            $_SESSION['usuario_id']    = $user['id'];
            $_SESSION['nome']          = $user['nome'];
            $_SESSION['tipo']          = $user['tipo'];
            $_SESSION['categoria']     = $user['categoria'];
            $_SESSION['ultimo_acesso'] = 'publico';

            redirecionarAposLogin();

        } else {
            // Falha — registra tentativa
            registrarTentativa($pdo, $email, false);
            $restam = tentativasRestantes($pdo, $email);

            // Mensagem genérica + dica se o email for de coordenador
            $dicaCoord = '';
            $chk = $pdo->prepare("SELECT tipo FROM usuarios WHERE email = ? AND ativo = 1");
            $chk->execute([$email]);
            $possivelCoord = $chk->fetch();
            if ($possivelCoord && $possivelCoord['tipo'] === 'coordenador') {
                $dicaCoord = ' <a href="admin/login.php" style="color:#E30613;font-weight:700;">Área do Coordenador →</a>';
            }

            $erro = "Email ou senha incorretos. Tentativas restantes: {$restam}.{$dicaCoord}";
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
    <title>Login · Sentinela da Fronteira</title>
    <link rel="icon" href="../assets/img/logo.png">
    <link rel="stylesheet" href="../assets/css/style.css">
    <!-- 🔐 Token CSRF disponível para o JS -->
    <meta name="csrf-token" content="<?= csrfToken() ?>">
</head>
<body class="login-page">
    <div class="login-box">
        <img src="../assets/img/logo.png" alt="Sentinela da Fronteira" class="logo-login">
        <h1>Sentinela da Fronteira</h1>
        <p class="sub">ÁREA DO DANÇARINO / RESPONSÁVEL</p>

        <?php if ($erro): ?>
            <div class="alert erro"><?= $erro ?></div>
        <?php endif; ?>

        <form method="POST" autocomplete="off">
            <?= csrfCampo() ?>

            <input type="email" name="email"
                   placeholder="✉️ Seu email"
                   value="<?= htmlspecialchars($email) ?>"
                   required autofocus>

            <input type="password" name="senha"
                   placeholder="🔒 Sua senha"
                   required>

            <button type="submit">Entrar</button>
        </form>

        <div class="login-links">
            <p style="text-align:right;margin-top:-8px;">
                <a href="esqueci-senha.php"
                   style="font-size:.82rem;color:var(--verde);">Esqueci minha senha</a>
            </p>

            <p>Não tem conta ainda?
                <a href="cadastro.php" class="link-cadastro">Cadastre-se aqui</a>
            </p>
            <p>
                <a href="admin/login.php" class="link-admin">🔒 Área do Coordenador</a>
            </p>
        </div>

        <p class="frase-login">"Unindo forças é bem mais fácil"</p>
    </div>

    <script src="../assets/js/api.js"></script>
</body>
</html>