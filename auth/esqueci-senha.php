<?php
/* ============================================================
   RECUPERAÇÃO DE SENHA — Sentinela da Fronteira
   ------------------------------------------------------------
   Fluxo:
   1. Usuário informa email
   2. Sistema gera token único e salva em recuperacao_senha
   3. Envia email com link contendo o token
   4. Usuário clica e vai para redefinir-senha.php
   ============================================================ */

require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/mailer.php';
require_once '../includes/email-template.php';

// Se já logado, manda pro dashboard
if (usuarioLogado()) {
    redirecionarAposLogin();
}

// 🔐 Valida CSRF em POST
csrfProtegerPost();

$msg  = '';
$erro = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));

    // ---------- Validações básicas ----------
    if (!$email) {
        $erro = 'Informe seu email.';
    } elseif (!emailValido($email)) {
        $erro = 'Email inválido. Confira e tente novamente.';
    } else {
        // ---------- Bloqueio contra abuso (rate limit) ----------
        // Evita que alguém fique disparando recuperação para o mesmo email
        $ip = ipCliente();

        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM recuperacao_senha r
            JOIN usuarios u ON u.id = r.usuario_id
            WHERE u.email = ? AND r.criado_em > NOW() - INTERVAL 5 MINUTE
        ");
        $stmt->execute([$email]);
        $recentes = (int)$stmt->fetchColumn();

        if ($recentes >= 3) {
            $erro = '⏳ Você solicitou recentemente. Aguarde alguns minutos antes de tentar novamente.';
        } else {
            // ---------- Busca o usuário ----------
            $stmt = $pdo->prepare("SELECT id, nome FROM usuarios WHERE email = ? AND ativo = 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            // ⚠️ Sempre mostramos a MESMA mensagem (evita descobrir emails cadastrados)
            $msg = 'Se este email estiver cadastrado, enviaremos as instruções em instantes. Verifique também a caixa de spam.';

            if ($user) {
                try {
                    // ---------- Gera token ----------
                    $token  = gerarToken(32);            // 64 caracteres hex
                    $expira = date('Y-m-d H:i:s', strtotime('+1 hour'));

                    // Invalida tokens antigos deste usuário
                    $pdo->prepare("
                        UPDATE recuperacao_senha 
                        SET usado = 1 
                        WHERE usuario_id = ? AND usado = 0
                    ")->execute([$user['id']]);

                    // Insere novo token
                    $pdo->prepare("
                        INSERT INTO recuperacao_senha (usuario_id, token, expira_em) 
                        VALUES (?, ?, ?)
                    ")->execute([$user['id'], $token, $expira]);

                    // ---------- Monta o link ----------
                    $link = BASE_URL . '/auth/redefinir-senha.php?token=' . urlencode($token);

                    // ---------- Gera HTML do email ----------
                    $html = templateRecuperacaoSenha($user['nome'], $link, 60);

                    // ---------- Envia ----------
                    $enviado = enviarEmail(
                        $email,
                        '🔐 Recuperação de Senha — ' . APP_NOME,
                        $html
                    );

                    if (!$enviado) {
                        error_log("[ESQUECI-SENHA] Falha ao enviar email para {$email}");
                        // Não mostramos o erro real para o usuário (segurança)
                    }

                } catch (Exception $e) {
                    error_log('[ESQUECI-SENHA] ' . $e->getMessage());
                    // Silencioso — mantém a mensagem genérica
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Senha · Sentinela da Fronteira</title>
    <link rel="icon" href="../assets/img/logo.png">
    <link rel="stylesheet" href="../assets/css/style.css">
    <meta name="csrf-token" content="<?= csrfToken() ?>">
</head>
<body class="login-page">
    <div class="login-box">
        <img src="../assets/img/logo.png" alt="Sentinela da Fronteira" class="logo-login">

        <h1>Recuperar Senha</h1>
        <p class="sub">DIGITE SEU EMAIL CADASTRADO</p>

        <?php if ($erro): ?>
            <div class="alert erro">⚠️ <?= $erro ?></div>
        <?php endif; ?>

        <?php if ($msg): ?>
            <div class="alert ok">
                ✅ <?= htmlspecialchars($msg) ?>
            </div>
            <div style="margin: 20px 0; padding: 16px; background:#F5F7F5; border-radius:10px; font-size:.88rem; color:#6D6E71; line-height:1.6;">
                <strong style="color:var(--verde-esc);">📬 Próximos passos:</strong><br>
                1. Abra seu email<br>
                2. Procure por <em>"Recuperação de Senha"</em><br>
                3. Clique no botão <em>"Redefinir Minha Senha"</em><br>
                4. Crie uma nova senha com pelo menos 6 caracteres
            </div>
        <?php endif; ?>

        <?php if (!$msg): ?>
        <form method="POST" autocomplete="off">
            <?= csrfCampo() ?>

            <input type="email" name="email"
                   placeholder="✉️ Seu email cadastrado"
                   value="<?= htmlspecialchars($email) ?>"
                   required autofocus>

            <button type="submit">📧 Enviar link de recuperação</button>
        </form>
        <?php endif; ?>

        <div class="login-links">
            <p>
                <a href="login.php" class="link-voltar">← Voltar para o login</a>
            </p>
            <p style="font-size:.82rem; color:#A7A9AC;">
                É coordenador?
                <a href="admin/login.php" style="color:var(--vermelho); font-weight:700;">
                    Área do Coordenador
                </a>
            </p>
        </div>

        <p class="frase-login">"Unindo forças é bem mais fácil"</p>
    </div>

    <script src="../assets/js/api.js"></script>
</body>
</html>