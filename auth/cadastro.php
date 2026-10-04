<?php
require_once '../config/database.php';
require_once '../includes/auth.php';

if (usuarioLogado()) {
    header('Location: ../pages/dashboard.php');
    exit;
}

// 🔐 CSRF: valida em qualquer POST
csrfProtegerPost();

$msg = '';
$erro = '';

// Guarda os valores digitados para repovoar o form em caso de erro
$old = $_POST;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $tipo = $_POST['tipo'] ?? '';

        if (!in_array($tipo, ['dancarino', 'responsavel'], true)) {
            throw new Exception('Tipo de cadastro inválido.');
        }

        // ============ DADOS COMUNS ============
        $nome     = trim($_POST['nome'] ?? '');
        $email    = strtolower(trim($_POST['email'] ?? ''));
        $senha    = $_POST['senha'] ?? '';
        $confirm  = $_POST['senha_confirm'] ?? '';
        $cpf      = apenasDigitos($_POST['cpf'] ?? '');
        $nasc     = $_POST['data_nascimento'] ?: null;
        $telefone = apenasDigitos($_POST['telefone'] ?? '');
        $endereco = trim($_POST['endereco'] ?? '');

        // ---------- Validações básicas ----------
        if (!$nome)  throw new Exception('Informe o nome completo.');
        if (!$email) throw new Exception('Informe o email.');
        if (!emailValido($email)) throw new Exception('Email inválido.');
        if (strlen($senha) < 6)   throw new Exception('A senha deve ter no mínimo 6 caracteres.');
        if ($senha !== $confirm)  throw new Exception('As senhas não coincidem.');

        // ---------- CPF ----------
        if ($cpf && !validarCPF($cpf)) {
            throw new Exception('CPF inválido. Confira os números digitados.');
        }

        // ---------- Telefone ----------
        if ($telefone && !validarTelefone($telefone)) {
            throw new Exception('Telefone inválido. Informe DDD + número.');
        }

        // ---------- Email duplicado ----------
        $chk = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
        $chk->execute([$email]);
        if ($chk->fetch()) throw new Exception('Este email já está cadastrado.');

        // ---------- CPF duplicado (se informado) ----------
        if ($cpf) {
            $chk = $pdo->prepare("SELECT id FROM usuarios WHERE cpf = ?");
            $chk->execute([$cpf]);
            if ($chk->fetch()) throw new Exception('Este CPF já está cadastrado.');
        }

        $hash = password_hash($senha, PASSWORD_DEFAULT);

        // =====================================================
        // CADASTRO COMO RESPONSÁVEL
        // =====================================================
        if ($tipo === 'responsavel') {
            $stmt = $pdo->prepare("INSERT INTO usuarios 
                (nome,email,senha,cpf,data_nascimento,telefone,endereco,tipo) 
                VALUES (?,?,?,?,?,?,?,'responsavel')");
            $stmt->execute([$nome,$email,$hash,$cpf ?: null,$nasc,$telefone,$endereco]);

            $msg = 'Cadastro realizado! Faça login para continuar.';
        }

        // =====================================================
        // CADASTRO COMO DANÇARINO
        // =====================================================
        if ($tipo === 'dancarino') {
            $categoria = $_POST['categoria'] ?? '';
            if (!in_array($categoria, ['pre_mirim','mirim','juvenil','adulto'], true)) {
                throw new Exception('Selecione uma categoria válida.');
            }

            if (!$nasc) throw new Exception('Informe a data de nascimento.');
            $idade = calcularIdade($nasc);
            if ($idade === null || $idade < 0 || $idade > 120) {
                throw new Exception('Data de nascimento inválida.');
            }

            $responsavel_id = null;

            // ---- Se MENOR de 18 → precisa de responsável ----
            if ($idade < 18) {
                $modo_resp = $_POST['modo_responsavel'] ?? 'novo';

                if ($modo_resp === 'novo') {
                    $rnome     = trim($_POST['resp_nome'] ?? '');
                    $remail    = strtolower(trim($_POST['resp_email'] ?? ''));
                    $rsenha    = $_POST['resp_senha'] ?? '';
                    $rcpf      = apenasDigitos($_POST['resp_cpf'] ?? '');
                    $rtelefone = apenasDigitos($_POST['resp_telefone'] ?? '');

                    if (!$rnome)  throw new Exception('Informe o nome do responsável.');
                    if (!$remail) throw new Exception('Informe o email do responsável.');
                    if (!emailValido($remail)) throw new Exception('Email do responsável inválido.');
                    if (strlen($rsenha) < 6)   throw new Exception('A senha do responsável precisa de 6+ caracteres.');

                    if ($rcpf && !validarCPF($rcpf)) {
                        throw new Exception('CPF do responsável inválido.');
                    }
                    if ($rtelefone && !validarTelefone($rtelefone)) {
                        throw new Exception('Telefone do responsável inválido.');
                    }

                    // Email do responsável não pode coincidir com o do dançarino
                    if ($remail === $email) {
                        throw new Exception('O email do responsável deve ser diferente do email do dançarino.');
                    }

                    // Já existe como responsável?
                    $chk = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
                    $chk->execute([$remail]);
                    if ($chk->fetch()) throw new Exception('Email do responsável já cadastrado.');

                    // CPF do responsável duplicado?
                    if ($rcpf) {
                        $chk = $pdo->prepare("SELECT id FROM usuarios WHERE cpf = ?");
                        $chk->execute([$rcpf]);
                        if ($chk->fetch()) throw new Exception('CPF do responsável já cadastrado.');
                    }

                    // 🔁 Transação
                    if (!$pdo->inTransaction()) $pdo->beginTransaction();

                    $stmt = $pdo->prepare("INSERT INTO usuarios 
                        (nome,email,senha,cpf,telefone,tipo) 
                        VALUES (?,?,?,?,?,'responsavel')");
                    $stmt->execute([$rnome,$remail,password_hash($rsenha, PASSWORD_DEFAULT),$rcpf ?: null,$rtelefone]);
                    $responsavel_id = (int)$pdo->lastInsertId();

                    $stmt = $pdo->prepare("INSERT INTO usuarios 
                        (nome,email,senha,cpf,data_nascimento,telefone,endereco,tipo,categoria,responsavel_id) 
                        VALUES (?,?,?,?,?,?,?, 'dancarino', ?, ?)");
                    $stmt->execute([$nome,$email,$hash,$cpf ?: null,$nasc,$telefone,$endereco,$categoria,$responsavel_id]);

                    $pdo->commit();
                    $msg = 'Cadastro do dançarino e do responsável concluídos! Faça login para continuar.';

                } elseif ($modo_resp === 'existente') {
                    $remail = strtolower(trim($_POST['resp_email_existente'] ?? ''));
                    if (!$remail) throw new Exception('Informe o email do responsável.');
                    if (!emailValido($remail)) throw new Exception('Email do responsável inválido.');

                    $chk = $pdo->prepare("SELECT id FROM usuarios 
                                          WHERE email = ? AND tipo = 'responsavel' AND ativo = 1");
                    $chk->execute([$remail]);
                    $resp = $chk->fetch();
                    if (!$resp) {
                        throw new Exception('Responsável não encontrado. Peça para ele se cadastrar primeiro ou escolha "Cadastrar novo responsável".');
                    }
                    $responsavel_id = (int)$resp['id'];

                    $stmt = $pdo->prepare("INSERT INTO usuarios 
                        (nome,email,senha,cpf,data_nascimento,telefone,endereco,tipo,categoria,responsavel_id) 
                        VALUES (?,?,?,?,?,?,?, 'dancarino', ?, ?)");
                    $stmt->execute([$nome,$email,$hash,$cpf ?: null,$nasc,$telefone,$endereco,$categoria,$responsavel_id]);

                    $msg = 'Cadastro concluído e vinculado ao responsável!';
                } else {
                    throw new Exception('Escolha uma opção de responsável.');
                }

            } else {
                // Maior de 18 → cadastra direto, sem responsável
                $stmt = $pdo->prepare("INSERT INTO usuarios 
                    (nome,email,senha,cpf,data_nascimento,telefone,endereco,tipo,categoria) 
                    VALUES (?,?,?,?,?,?,?, 'dancarino', ?)");
                $stmt->execute([$nome,$email,$hash,$cpf ?: null,$nasc,$telefone,$endereco,$categoria]);
                $msg = 'Cadastro realizado! Faça login para continuar.';
            }
        }

        // Sucesso → limpa os valores antigos
        $old = [];

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $erro = $e->getMessage();
    }
}

// Helper: repovoa campo
function val($campo, $default = '') {
    global $old;
    return htmlspecialchars($old[$campo] ?? $default);
}
function sel($campo, $valor) {
    global $old;
    return ($old[$campo] ?? '') === $valor ? 'selected' : '';
}
function chk($campo, $valor) {
    global $old;
    return ($old[$campo] ?? '') === $valor ? 'checked' : '';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro · Sentinela da Fronteira</title>
    <link rel="icon" href="../assets/img/logo.png">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .cadastro-page {
            min-height: 100vh;
            padding: 30px 15px;
            background:
                radial-gradient(circle at 15% 20%, rgba(254,221,0,.20), transparent 45%),
                radial-gradient(circle at 85% 80%, rgba(227,6,19,.20), transparent 45%),
                linear-gradient(135deg, #006B29 0%, #009739 55%, #009FE3 100%);
            display: flex; align-items: flex-start; justify-content: center;
        }
        .cadastro-box {
            background: #fff;
            padding: 35px 32px;
            border-radius: 20px;
            box-shadow: 0 25px 60px rgba(0,0,0,.35);
            width: 100%; max-width: 780px;
            border-top: 6px solid var(--amarelo);
        }
        .cadastro-box h1 { color: var(--verde-esc); text-align: center; font-size: 1.5rem; }
        .cadastro-box .sub { text-align: center; color: #6D6E71; margin-bottom: 25px; font-size: .85rem; letter-spacing: 1px; }

        .tipo-selector { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin: 20px 0; }
        .tipo-card {
            border: 3px solid #e5e7eb; border-radius: 12px; padding: 18px;
            cursor: pointer; text-align: center; transition: .2s;
        }
        .tipo-card:hover { border-color: var(--azul); }
        .tipo-card.ativo { border-color: var(--verde); background: #f0fdf4; }
        .tipo-card .emoji { font-size: 2rem; }
        .tipo-card strong { display: block; margin-top: 6px; color: var(--verde-esc); }
        .tipo-card small { color: #6D6E71; font-size: .78rem; }

        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .form-grid .full { grid-column: 1/-1; }
        .form-grid label { font-size: .82rem; font-weight: 600; color: #6D6E71; display: flex; flex-direction: column; gap: 5px; }

        .section-title {
            grid-column: 1/-1;
            margin: 20px 0 5px;
            padding: 8px 12px;
            background: linear-gradient(90deg, var(--verde), var(--azul));
            color: #fff;
            border-radius: 8px;
            font-weight: 700;
            font-size: .9rem;
            letter-spacing: .5px;
        }
        .section-title.amarelo { background: linear-gradient(90deg, var(--amarelo-esc), var(--vermelho)); }

        .radio-linha { display:flex; gap:15px; margin-top:5px; flex-wrap:wrap; }
        .radio-linha label { flex-direction: row; align-items:center; gap:6px; font-weight:500; }

        /* Feedback visual de senha */
        .senha-forca { height: 4px; border-radius: 2px; margin-top: 4px; background: #e5e7eb; overflow: hidden; }
        .senha-forca::after {
            content:''; display:block; height:100%; width: var(--w, 0%);
            background: var(--c, #e5e7eb); transition: .3s;
        }

        @media(max-width:640px){
            .form-grid, .tipo-selector { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body class="cadastro-page">
    <div class="cadastro-box">
        <img src="../assets/img/logo.png" alt="Sentinela" style="width:110px;display:block;margin:0 auto 10px;">
        <h1>Criar Conta</h1>
        <p class="sub">PIQUETE SENTINELA DA FRONTEIRA · COHAB II</p>

        <?php if ($erro): ?>
            <div class="alert erro">⚠️ <?= htmlspecialchars($erro) ?></div>
        <?php endif; ?>
        <?php if ($msg): ?>
            <div class="alert ok">✅ <?= htmlspecialchars($msg) ?>
                <a href="login.php" style="color:#166534;font-weight:700;margin-left:8px;">Fazer login →</a>
            </div>
        <?php endif; ?>

        <?php if (!$msg): ?>
        <form method="POST" id="formCadastro" novalidate>
            <?= csrfCampo() ?>

            <!-- SELEÇÃO DE TIPO -->
            <div class="tipo-selector">
                <div class="tipo-card <?= ($old['tipo'] ?? 'dancarino') === 'dancarino' ? 'ativo' : '' ?>"
                     data-tipo="dancarino" onclick="selecionarTipo('dancarino')">
                    <div class="emoji">💃</div>
                    <strong>Dançarino(a)</strong>
                    <small>Sou do grupo e quero me cadastrar</small>
                </div>
                <div class="tipo-card <?= ($old['tipo'] ?? '') === 'responsavel' ? 'ativo' : '' ?>"
                     data-tipo="responsavel" onclick="selecionarTipo('responsavel')">
                    <div class="emoji">👨‍👩‍👧</div>
                    <strong>Responsável</strong>
                    <small>Sou pai/mãe/responsável legal</small>
                </div>
            </div>

            <input type="hidden" name="tipo" id="tipoInput"
                   value="<?= htmlspecialchars($old['tipo'] ?? 'dancarino') ?>">

            <!-- DADOS PESSOAIS -->
            <div class="form-grid">
                <div class="section-title">📋 Dados Pessoais</div>

                <label class="full">Nome completo *
                    <input type="text" name="nome" value="<?= val('nome') ?>" required>
                </label>
                <label>Email *
                    <input type="email" name="email" value="<?= val('email') ?>" required>
                </label>
                <label>CPF
                    <input type="text" name="cpf" data-mask="cpf"
                           value="<?= val('cpf') ?>" placeholder="000.000.000-00">
                </label>
                <label>Telefone (WhatsApp)
                    <input type="text" name="telefone" data-mask="tel"
                           value="<?= val('telefone') ?>" placeholder="(55) 99999-9999">
                </label>
                <label>Data de nascimento
                    <input type="date" name="data_nascimento" id="dataNasc"
                           value="<?= val('data_nascimento') ?>">
                </label>
                <label class="full">Endereço
                    <input type="text" name="endereco" value="<?= val('endereco') ?>"
                           placeholder="Rua, número, bairro">
                </label>
                <label>Senha * (mín. 6)
                    <input type="password" name="senha" id="senha" required minlength="6">
                    <div class="senha-forca" id="forca"></div>
                </label>
                <label>Confirmar senha *
                    <input type="password" name="senha_confirm" id="senhaConf" required minlength="6">
                </label>
            </div>

            <!-- CAMPOS EXCLUSIVOS DE DANÇARINO -->
            <div class="form-grid" id="blocoDancarino">
                <div class="section-title">💃 Dados do Dançarino</div>
                <label class="full">Categoria *
                    <select name="categoria" required>
                        <option value="">Selecione...</option>
                        <option value="pre_mirim" <?= sel('categoria','pre_mirim') ?>>Pré-Mirim</option>
                        <option value="mirim"     <?= sel('categoria','mirim') ?>>Mirim</option>
                        <option value="juvenil"   <?= sel('categoria','juvenil') ?>>Juvenil</option>
                        <option value="adulto"    <?= sel('categoria','adulto') ?>>Adulto</option>
                    </select>
                </label>
            </div>

            <!-- BLOCO RESPONSÁVEL -->
            <div class="form-grid" id="blocoResponsavel" style="display:none;">
                <div class="section-title amarelo">👨‍👩‍👧 Responsável (menor de idade)</div>

                <div class="full radio-linha">
                    <label>
                        <input type="radio" name="modo_responsavel" value="novo"
                               <?= chk('modo_responsavel','novo') ?: (empty($old['modo_responsavel']) ? 'checked' : '') ?>
                               onchange="modoResponsavel()">
                        Cadastrar novo responsável
                    </label>
                    <label>
                        <input type="radio" name="modo_responsavel" value="existente"
                               <?= chk('modo_responsavel','existente') ?>
                               onchange="modoResponsavel()">
                        Já tenho responsável cadastrado
                    </label>
                </div>

                <!-- Novo responsável -->
                <div id="respNovo" class="form-grid" style="grid-column:1/-1;">
                    <label>Nome do responsável *
                        <input type="text" name="resp_nome" value="<?= val('resp_nome') ?>">
                    </label>
                    <label>Email do responsável *
                        <input type="email" name="resp_email" value="<?= val('resp_email') ?>">
                    </label>
                    <label>CPF do responsável
                        <input type="text" name="resp_cpf" data-mask="cpf"
                               value="<?= val('resp_cpf') ?>" placeholder="000.000.000-00">
                    </label>
                    <label>Telefone do responsável
                        <input type="text" name="resp_telefone" data-mask="tel"
                               value="<?= val('resp_telefone') ?>" placeholder="(55) 99999-9999">
                    </label>
                    <label class="full">Senha do responsável * (mín. 6)
                        <input type="password" name="resp_senha" minlength="6">
                    </label>
                </div>

                <!-- Responsável existente -->
                <div id="respExistente" class="form-grid" style="grid-column:1/-1; display:none;">
                    <label class="full">Email do responsável já cadastrado *
                        <input type="email" name="resp_email_existente"
                               value="<?= val('resp_email_existente') ?>"
                               placeholder="email@exemplo.com">
                    </label>
                </div>
            </div>

            <button type="submit" style="
                width:100%;padding:14px;margin-top:20px;
                background:linear-gradient(135deg, var(--verde), var(--verde-esc));
                color:#fff;border:none;border-radius:10px;font-weight:700;
                font-size:1rem;cursor:pointer;letter-spacing:.5px;text-transform:uppercase;">
                ✅ Criar Conta
            </button>
        </form>

        <p style="text-align:center;margin-top:18px;font-size:.9rem;color:#6D6E71;">
            Já tem conta? <a href="login.php" style="color:var(--verde-esc);font-weight:700;">Fazer login</a>
        </p>
        <?php endif; ?>
    </div>

<script>
// ============================================
// 1) MÁSCARAS (CPF / Telefone)
// ============================================
document.querySelectorAll('[data-mask="cpf"]').forEach(el => {
    el.addEventListener('input', e => {
        let v = e.target.value.replace(/\D/g,'').slice(0,11);
        v = v.replace(/(\d{3})(\d)/, '$1.$2');
        v = v.replace(/(\d{3})(\d)/, '$1.$2');
        v = v.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
        e.target.value = v;
    });
});
document.querySelectorAll('[data-mask="tel"]').forEach(el => {
    el.addEventListener('input', e => {
        let v = e.target.value.replace(/\D/g,'').slice(0,11);
        if (v.length > 10) v = v.replace(/^(\d{2})(\d{5})(\d{4}).*/, '($1) $2-$3');
        else if (v.length > 6) v = v.replace(/^(\d{2})(\d{4})(\d{0,4}).*/, '($1) $2-$3');
        else if (v.length > 2) v = v.replace(/^(\d{2})(\d{0,5})/, '($1) $2');
        else v = v.replace(/^(\d*)/, '($1');
        e.target.value = v;
    });
});

// ============================================
// 2) ALTERNAR TIPO
// ============================================
function selecionarTipo(tipo) {
    document.querySelectorAll('.tipo-card').forEach(c => c.classList.remove('ativo'));
    document.querySelector(`.tipo-card[data-tipo="${tipo}"]`).classList.add('ativo');
    document.getElementById('tipoInput').value = tipo;

    const blocoDancer = document.getElementById('blocoDancarino');
    const blocoResp   = document.getElementById('blocoResponsavel');

    if (tipo === 'dancarino') {
        blocoDancer.style.display = '';
        checarIdade();
    } else {
        blocoDancer.style.display = 'none';
        blocoResp.style.display   = 'none';
    }
}

// ============================================
// 3) IDADE → MOSTRAR/OCULTAR RESPONSÁVEL
// ============================================
function checarIdade() {
    const nasc = document.getElementById('dataNasc').value;
    const blocoResp = document.getElementById('blocoResponsavel');
    const tipoAtual = document.getElementById('tipoInput').value;

    if (tipoAtual !== 'dancarino' || !nasc) {
        blocoResp.style.display = 'none';
        return;
    }

    const hoje = new Date();
    const dn   = new Date(nasc);
    let idade = hoje.getFullYear() - dn.getFullYear();
    const m = hoje.getMonth() - dn.getMonth();
    if (m < 0 || (m === 0 && hoje.getDate() < dn.getDate())) idade--;

    blocoResp.style.display = (idade < 18) ? '' : 'none';
    if (idade < 18) modoResponsavel();
}

function modoResponsavel() {
    const modo = document.querySelector('input[name="modo_responsavel"]:checked')?.value || 'novo';
    document.getElementById('respNovo').style.display      = modo === 'novo'      ? '' : 'none';
    document.getElementById('respExistente').style.display = modo === 'existente' ? '' : 'none';
}

document.getElementById('dataNasc').addEventListener('change', checarIdade);

// ============================================
// 4) FORÇA DA SENHA
// ============================================
document.getElementById('senha').addEventListener('input', e => {
    const s = e.target.value;
    let score = 0;
    if (s.length >= 6) score++;
    if (s.length >= 10) score++;
    if (/[A-Z]/.test(s)) score++;
    if (/[0-9]/.test(s)) score++;
    if (/[^A-Za-z0-9]/.test(s)) score++;

    const cores = ['#dc2626','#f59e0b','#f59e0b','#16a34a','#009739'];
    const larg  = ['20%','40%','60%','80%','100%'];
    const idx   = Math.max(0, score - 1);
    const bar   = document.getElementById('forca');
    bar.style.setProperty('--w', s ? larg[idx] : '0%');
    bar.style.setProperty('--c', s ? cores[idx] : '#e5e7eb');
});

// ============================================
// 5) VALIDAÇÕES NO SUBMIT
// ============================================
document.getElementById('formCadastro').addEventListener('submit', function(e) {
    const tipo = document.getElementById('tipoInput').value;
    const senha = document.getElementById('senha').value;
    const conf  = document.getElementById('senhaConf').value;

    if (senha !== conf) {
        e.preventDefault();
        alert('⚠️ As senhas não coincidem!');
        return false;
    }

    if (tipo === 'dancarino') {
        const nasc = document.getElementById('dataNasc').value;
        if (!nasc) {
            e.preventDefault();
            alert('⚠️ Informe a data de nascimento do dançarino.');
            return false;
        }

        // Recalcula idade no cliente
        const hoje = new Date();
        const dn = new Date(nasc);
        let idade = hoje.getFullYear() - dn.getFullYear();
        const m = hoje.getMonth() - dn.getMonth();
        if (m < 0 || (m === 0 && hoje.getDate() < dn.getDate())) idade--;

        if (idade < 18) {
            const modo = document.querySelector('input[name="modo_responsavel"]:checked')?.value || 'novo';
            if (modo === 'novo') {
                const rn = document.querySelector('[name="resp_nome"]').value.trim();
                const re = document.querySelector('[name="resp_email"]').value.trim();
                const rs = document.querySelector('[name="resp_senha"]').value;
                if (!rn || !re || !rs) {
                    e.preventDefault();
                    alert('⚠️ Preencha os dados do responsável (nome, email e senha).');
                    return false;
                }
            } else {
                const re = document.querySelector('[name="resp_email_existente"]').value.trim();
                if (!re) {
                    e.preventDefault();
                    alert('⚠️ Informe o email do responsável já cadastrado.');
                    return false;
                }
            }
        }
    }
});

// ============================================
// 6) INICIALIZAÇÃO
// ============================================
(function init() {
    const tipo = document.getElementById('tipoInput').value || 'dancarino';
    selecionarTipo(tipo);
    if (tipo === 'dancarino') {
        checarIdade();
        modoResponsavel();
    }
})();
</script>
</body>
</html>