<?php
/* ============================================================
   SCRIPT TEMPORÁRIO — Criar Coordenador
   ------------------------------------------------------------
   ⚠️  USO ÚNICO:
   1. Preencha os dados abaixo
   2. Acesse no navegador: /criar-coordenador.php
   3. APAGUE este arquivo depois!
   ============================================================ */

require_once 'config/database.php';
require_once 'includes/auth.php';

// ============================================================
// 👇 EDITE AQUI OS DADOS DO COORDENADOR
// ============================================================
$nome     = 'Coordenador Master';
$email    = 'antoniomongelo72@gmail.com';
$senha    = '123456';           // senha forte
$cpf      = '05500866095';          // opcional (CPF válido)
$telefone = '55996652006';        // opcional

// ============================================================
// PROCESSAMENTO — não mexa daqui pra baixo
// ============================================================

$email = strtolower(trim($email));
$cpf   = apenasDigitos($cpf);
$telefone = apenasDigitos($telefone);

$erros = [];
$sucesso = false;

// Validações
if (!$nome)                                  $erros[] = 'Nome é obrigatório.';
if (!emailValido($email))                    $erros[] = 'Email inválido.';
if (strlen($senha) < 6)                      $erros[] = 'Senha precisa ter 6+ caracteres.';
if ($cpf && !validarCPF($cpf))               $erros[] = 'CPF inválido.';
if ($telefone && !validarTelefone($telefone))$erros[] = 'Telefone inválido.';

// Verifica duplicatas
if (empty($erros)) {
    $chk = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
    $chk->execute([$email]);
    if ($chk->fetch()) $erros[] = 'Já existe um usuário com este email.';

    if ($cpf) {
        $chk = $pdo->prepare("SELECT id FROM usuarios WHERE cpf = ?");
        $chk->execute([$cpf]);
        if ($chk->fetch()) $erros[] = 'Já existe um usuário com este CPF.';
    }
}

// Insere
if (empty($erros)) {
    try {
        $hash = password_hash($senha, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("
            INSERT INTO usuarios (nome, email, senha, cpf, telefone, tipo, ativo)
            VALUES (?, ?, ?, ?, ?, 'coordenador', 1)
        ");
        $stmt->execute([
            $nome,
            $email,
            $hash,
            $cpf ?: null,
            $telefone ?: null,
        ]);

        $sucesso = true;
        $novoId = $pdo->lastInsertId();

    } catch (Exception $e) {
        $erros[] = 'Erro ao inserir: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Criar Coordenador · Sentinela</title>
    <link rel="icon" href="assets/img/logo.png">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body {
            background: linear-gradient(160deg, #2C3330 0%, #355C3F 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .box {
            background: #fff;
            border-radius: 14px;
            padding: 40px 36px;
            max-width: 520px;
            width: 100%;
            box-shadow: 0 25px 60px rgba(0,0,0,.3);
        }
        .box img { width: 100px; display: block; margin: 0 auto 16px; }
        .box h1 {
            text-align: center;
            color: #2C3330;
            font-size: 1.15rem;
            letter-spacing: .5px;
            text-transform: uppercase;
            margin-bottom: 6px;
        }
        .box .sub {
            text-align: center;
            color: #8A8F8C;
            font-size: .75rem;
            letter-spacing: 1.5px;
            margin-bottom: 26px;
            text-transform: uppercase;
        }
        .alerta-warn {
            background: #FBF5E8;
            border-left: 3px solid #C9A961;
            color: #7A5C1F;
            padding: 12px 16px;
            border-radius: 6px;
            font-size: .82rem;
            margin-bottom: 20px;
            line-height: 1.5;
        }
        .alerta-erro {
            background: #FBF3F3;
            border-left: 3px solid #B85C5C;
            color: #7A2626;
            padding: 12px 16px;
            border-radius: 6px;
            font-size: .86rem;
            margin-bottom: 16px;
        }
        .alerta-erro ul { margin: 6px 0 0 18px; }
        .alerta-ok {
            background: #F1F7F3;
            border-left: 3px solid #4A7C59;
            color: #2C5535;
            padding: 16px;
            border-radius: 6px;
            font-size: .9rem;
            margin-bottom: 16px;
            line-height: 1.6;
        }
        .dados-criados {
            background: #F7F8F7;
            padding: 14px 16px;
            border-radius: 6px;
            font-size: .85rem;
            margin: 12px 0;
            border: 1px solid #E4E7E4;
        }
        .dados-criados strong { color: #2C3330; }
        .btn {
            display: block;
            width: 100%;
            padding: 13px;
            background: #4A7C59;
            color: #fff;
            text-align: center;
            text-decoration: none;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            font-size: .9rem;
            cursor: pointer;
            transition: .2s;
            margin-top: 12px;
        }
        .btn:hover {
            background: #355C3F;
            transform: translateY(-1px);
        }
        .aviso-final {
            background: #FBF3F3;
            border: 1px dashed #B85C5C;
            color: #7A2626;
            padding: 14px;
            border-radius: 6px;
            font-size: .8rem;
            margin-top: 20px;
            text-align: center;
            line-height: 1.5;
        }
    </style>
</head>
<body>
    <div class="box">
        <img src="assets/img/logo.png" alt="Sentinela">
        <h1>Criar Coordenador</h1>
        <p class="sub">Script de Uso Único</p>

        <div class="alerta-warn">
            ⚠️ Este script cria <strong>um coordenador por vez</strong>.
            Edite as variáveis no topo do arquivo para alterar os dados.
            <strong>Apague o arquivo após usar.</strong>
        </div>

        <?php if (!empty($erros)): ?>
            <div class="alerta-erro">
                <strong>❌ Erros encontrados:</strong>
                <ul>
                    <?php foreach ($erros as $e): ?>
                        <li><?= htmlspecialchars($e) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($sucesso): ?>
            <div class="alerta-ok">
                <strong>✅ Coordenador criado com sucesso!</strong>
                <div class="dados-criados">
                    <strong>ID:</strong> <?= $novoId ?><br>
                    <strong>Nome:</strong> <?= htmlspecialchars($nome) ?><br>
                    <strong>Email:</strong> <?= htmlspecialchars($email) ?><br>
                    <strong>Senha:</strong> <?= htmlspecialchars($senha) ?><br>
                    <strong>URL de login:</strong> /auth/admin/login.php
                </div>
                <a href="auth/admin/login.php" class="btn">
                    🔓 Ir para o Login do Coordenador
                </a>
            </div>

            <div class="aviso-final">
                🚨 <strong>ATENÇÃO:</strong> apague o arquivo <code>criar-coordenador.php</code> agora!<br>
                Ele não deve ficar em produção.
            </div>
        <?php else: ?>
            <div class="dados-criados">
                <strong>📋 Dados que serão criados:</strong><br>
                <strong>Nome:</strong> <?= htmlspecialchars($nome) ?><br>
                <strong>Email:</strong> <?= htmlspecialchars($email) ?><br>
                <strong>Senha:</strong> <?= htmlspecialchars($senha) ?><br>
                <strong>CPF:</strong> <?= $cpf ? formatarCPF($cpf) : '—' ?><br>
                <strong>Telefone:</strong> <?= $telefone ? formatarTelefone($telefone) : '—' ?>
            </div>
            <p style="text-align:center;font-size:.82rem;color:#8A8F8C;margin-top:16px;">
                Confira os dados e clique abaixo para criar.
            </p>
            <form method="POST" style="margin-top:12px;">
                <button type="submit" class="btn">✅ Criar Coordenador</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>