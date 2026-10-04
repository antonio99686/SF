<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
exigirLogin();

$tipo = $_SESSION['tipo'];
$uid  = $_SESSION['usuario_id'];

// ============================================================
// ESTATÍSTICAS PERSONALIZADAS POR PERFIL
// ============================================================

if (isCoordenador()) {
    // Coordenador vê tudo
    $totalEventos     = (int)$pdo->query("SELECT COUNT(*) FROM eventos")->fetchColumn();
    $totalUsuarios    = (int)$pdo->query("SELECT COUNT(*) FROM usuarios WHERE ativo=1")->fetchColumn();
    $totalDancarinos  = (int)$pdo->query("SELECT COUNT(*) FROM usuarios WHERE tipo='dancarino' AND ativo=1")->fetchColumn();
    $totalResponsaveis= (int)$pdo->query("SELECT COUNT(*) FROM usuarios WHERE tipo='responsavel' AND ativo=1")->fetchColumn();
    $pendentes        = (int)$pdo->query("SELECT COUNT(*) FROM mensalidades WHERE status != 'pago'")->fetchColumn();
    $pagasMes         = (int)$pdo->query("SELECT COUNT(*) FROM mensalidades WHERE status='pago' AND MONTH(data_pagamento)=MONTH(CURDATE()) AND YEAR(data_pagamento)=YEAR(CURDATE())")->fetchColumn();
    $vestimentasTotal = (int)$pdo->query("SELECT COALESCE(SUM(quantidade),0) FROM vestimentas")->fetchColumn();

    // Valor total pendente
    $valorPendente = (float)$pdo->query("SELECT COALESCE(SUM(valor),0) FROM mensalidades WHERE status != 'pago'")->fetchColumn();

    // Próximo evento
    $stmt = $pdo->query("SELECT titulo, data_evento FROM eventos WHERE data_evento >= NOW() ORDER BY data_evento LIMIT 1");
    $proximoEvento = $stmt->fetch();

    // Aniversariantes do mês
    $aniversariantes = $pdo->query("
        SELECT nome, data_nascimento 
        FROM usuarios 
        WHERE ativo=1 
          AND MONTH(data_nascimento) = MONTH(CURDATE())
        ORDER BY DAY(data_nascimento)
    ")->fetchAll();

} elseif (isDancarino()) {
    // Dançarino vê apenas suas informações
    $minhasMensalidades = (int)$pdo->query("SELECT COUNT(*) FROM mensalidades WHERE usuario_id = $uid AND status != 'pago'")->fetchColumn();
    $meuValorPendente   = (float)$pdo->query("SELECT COALESCE(SUM(valor),0) FROM mensalidades WHERE usuario_id = $uid AND status != 'pago'")->fetchColumn();
    $meusEventos        = (int)$pdo->query("SELECT COUNT(*) FROM eventos WHERE categoria IN ('todos', '{$_SESSION['categoria']}') AND data_evento >= NOW()")->fetchColumn();
    $minhasVestimentas  = (int)$pdo->query("SELECT COUNT(*) FROM vestimenta_entregas WHERE usuario_id = $uid AND devolvido = 0")->fetchColumn();

} else {
    // Responsável vê os filhos
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE responsavel_id = ? AND ativo=1");
    $stmt->execute([$uid]);
    $meusFilhos = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM mensalidades 
        WHERE usuario_id IN (SELECT id FROM usuarios WHERE responsavel_id = ?) 
          AND status != 'pago'
    ");
    $stmt->execute([$uid]);
    $pendentesFilhos = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(valor),0) FROM mensalidades 
        WHERE usuario_id IN (SELECT id FROM usuarios WHERE responsavel_id = ?) 
          AND status != 'pago'
    ");
    $stmt->execute([$uid]);
    $valorPendenteFilhos = (float)$stmt->fetchColumn();
}

// Dados do usuário logado
$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
$stmt->execute([$uid]);
$eu = $stmt->fetch();

// Hora do dia para saudação
$hora = (int)date('H');
if ($hora < 12)      $saudacao = 'Bom dia';
elseif ($hora < 18)  $saudacao = 'Boa tarde';
else                 $saudacao = 'Boa noite';

$primeiroNome = explode(' ', trim($eu['nome']))[0];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard · Sentinela da Fronteira</title>
    <link rel="icon" href="../assets/img/logo.png">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        /* ============================================
           CARDS DE ESTATÍSTICA
           ============================================ */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 14px;
            margin: 28px 0;
        }
        .stat {
            background: var(--cinza-card);
            border-radius: var(--radius);
            padding: 20px;
            border: 1px solid var(--cinza-borda);
            box-shadow: var(--sombra);
            display: flex;
            align-items: center;
            gap: 14px;
            transition: .2s;
            text-decoration: none;
            color: inherit;
        }
        .stat:hover {
            transform: translateY(-2px);
            box-shadow: var(--sombra-hover);
            border-color: var(--verde-suave);
        }
        .stat .icone {
            width: 46px;
            height: 46px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            flex-shrink: 0;
        }
        .stat .icone.verde   { background: var(--verde-claro); color: var(--verde-escuro); }
        .stat .icone.areia   { background: var(--areia-clara); color: #7A5C1F; }
        .stat .icone.azul    { background: #E5EEF5; color: #355C7C; }
        .stat .icone.violeta { background: #EDE8F5; color: #5C4A85; }
        .stat .icone.rosa    { background: #F5E5EE; color: #8A3D66; }
        .stat .icone.vermelho{ background: #F5E5E5; color: #8A2E2E; }

        .stat .conteudo {
            display: flex;
            flex-direction: column;
            gap: 2px;
            min-width: 0;
        }
        .stat .label {
            font-size: .74rem;
            color: var(--cinza-suave);
            text-transform: uppercase;
            letter-spacing: .6px;
            font-weight: 600;
        }
        .stat .valor {
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--cinza-escuro);
            line-height: 1;
        }
        .stat .sub {
            font-size: .72rem;
            color: var(--cinza-suave);
            margin-top: 3px;
        }

        /* ============================================
           CABEÇALHO DE BOAS-VINDAS
           ============================================ */
        .bem-vindo {
            background: linear-gradient(135deg, #F1F7F3 0%, #FFFFFF 100%);
            border: 1px solid var(--cinza-borda);
            border-left: 4px solid var(--verde-suave);
            border-radius: var(--radius);
            padding: 24px 28px;
            margin-bottom: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
        }
        .bem-vindo h1 {
            margin: 0 0 8px;
            font-size: 1.4rem;
            color: var(--cinza-escuro);
        }
        .bem-vindo .info {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
        }
        .bem-vindo .avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: var(--verde-suave);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: 700;
            flex-shrink: 0;
            border: 3px solid #fff;
            box-shadow: 0 4px 12px rgba(74,124,89,.25);
        }
        .bem-vindo .lado-esq {
            display: flex;
            align-items: center;
            gap: 18px;
        }

        /* ============================================
           SEÇÃO — AÇÕES RÁPIDAS
           ============================================ */
        .secao-titulo {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 32px 0 14px;
            font-size: .78rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--cinza-suave);
            font-weight: 700;
        }
        .secao-titulo::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--cinza-borda);
        }

        .cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(230px, 1fr));
            gap: 14px;
        }
        .card-acao {
            background: var(--cinza-card);
            border-radius: var(--radius);
            padding: 22px;
            border: 1px solid var(--cinza-borda);
            box-shadow: var(--sombra);
            text-decoration: none;
            color: var(--cinza-texto);
            transition: .2s;
            display: flex;
            flex-direction: column;
            gap: 10px;
            position: relative;
            overflow: hidden;
        }
        .card-acao::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 3px;
            height: 100%;
            background: var(--verde-suave);
            transition: .2s;
        }
        .card-acao:hover {
            transform: translateY(-3px);
            box-shadow: var(--sombra-hover);
        }
        .card-acao:hover::before {
            width: 5px;
            background: var(--areia);
        }
        .card-acao .cabecalho {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }
        .card-acao .cabecalho .icone {
            font-size: 1.6rem;
        }
        .card-acao .cabecalho .seta {
            color: var(--cinza-suave);
            font-size: 1rem;
            transition: .2s;
        }
        .card-acao:hover .cabecalho .seta {
            color: var(--verde-suave);
            transform: translateX(4px);
        }
        .card-acao h3 {
            font-size: .96rem;
            font-weight: 600;
            color: var(--cinza-escuro);
            margin: 0;
        }
        .card-acao p {
            font-size: .82rem;
            color: var(--cinza-suave);
            margin: 0;
            line-height: 1.4;
        }

        /* ============================================
           PAINEL LATERAL — PRÓXIMO EVENTO / ANIVERSARIANTES
           ============================================ */
        .duas-colunas {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-top: 14px;
        }
        .painel {
            background: var(--cinza-card);
            border-radius: var(--radius);
            padding: 20px 22px;
            border: 1px solid var(--cinza-borda);
            box-shadow: var(--sombra);
        }
        .painel h4 {
            margin: 0 0 14px;
            font-size: .88rem;
            color: var(--cinza-escuro);
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
        }
        .painel .vazio {
            color: var(--cinza-suave);
            font-size: .84rem;
            font-style: italic;
            padding: 12px 0;
            text-align: center;
        }
        .lista-simples {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .lista-simples li {
            padding: 10px 0;
            border-bottom: 1px solid var(--cinza-borda);
            font-size: .86rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
        }
        .lista-simples li:last-child { border-bottom: none; }
        .lista-simples .nome { color: var(--cinza-texto); font-weight: 500; }
        .lista-simples .data {
            color: var(--cinza-suave);
            font-size: .78rem;
            background: var(--cinza-bg);
            padding: 2px 8px;
            border-radius: 10px;
        }

        @media (max-width: 720px) {
            .duas-colunas { grid-template-columns: 1fr; }
            .bem-vindo { flex-direction: column; align-items: flex-start; }
        }
    </style>
</head>
<body>
<?php include '../includes/header.php'; ?>

<main class="container">

    <!-- ========== BOAS-VINDAS ========== -->
    <div class="bem-vindo">
        <div class="lado-esq">
            <div class="avatar">
                <?= strtoupper(substr($primeiroNome, 0, 1)) ?>
            </div>
            <div>
                <h1><?= $saudacao ?>, <?= htmlspecialchars($primeiroNome) ?>! 👋</h1>
                <div class="info">
                    <span class="badge"><?= ucfirst($tipo) ?></span>
                    <?php if (!empty($_SESSION['categoria'])): ?>
                        <span class="badge amarelo"><?= formatarCategoria($_SESSION['categoria']) ?></span>
                    <?php endif; ?>
                    <span style="font-size:.78rem;color:var(--cinza-suave);">
                        <?= date('d/m/Y') ?>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- ========== ESTATÍSTICAS (por perfil) ========== -->
    <?php if (isCoordenador()): ?>
        <div class="stats-grid">
            <a href="usuarios.php" class="stat">
                <div class="icone verde">👥</div>
                <div class="conteudo">
                    <span class="label">Usuários Ativos</span>
                    <span class="valor"><?= $totalUsuarios ?></span>
                    <span class="sub"><?= $totalDancarinos ?> dançarinos</span>
                </div>
            </a>

            <a href="eventos.php" class="stat">
                <div class="icone azul">🎉</div>
                <div class="conteudo">
                    <span class="label">Eventos</span>
                    <span class="valor"><?= $totalEventos ?></span>
                    <span class="sub">cadastrados</span>
                </div>
            </a>

            <a href="mensalidades.php?status=pendente" class="stat">
                <div class="icone areia">⏳</div>
                <div class="conteudo">
                    <span class="label">Pendentes</span>
                    <span class="valor"><?= $pendentes ?></span>
                    <span class="sub"><?= formatarMoeda($valorPendente) ?></span>
                </div>
            </a>

            <a href="mensalidades.php?status=pago" class="stat">
                <div class="icone verde">✅</div>
                <div class="conteudo">
                    <span class="label">Pagas no mês</span>
                    <span class="valor"><?= $pagasMes ?></span>
                    <span class="sub">mensalidades</span>
                </div>
            </a>

            <a href="vestimentas.php" class="stat">
                <div class="icone violeta">👕</div>
                <div class="conteudo">
                    <span class="label">Estoque</span>
                    <span class="valor"><?= $vestimentasTotal ?></span>
                    <span class="sub">peças disponíveis</span>
                </div>
            </a>

            <a href="relatorio.php" class="stat">
                <div class="icone rosa">📊</div>
                <div class="conteudo">
                    <span class="label">Relatório</span>
                    <span class="valor"><?= date('Y') ?></span>
                    <span class="sub">análise financeira</span>
                </div>
            </a>
        </div>

        <!-- ========== PAINÉIS LATERAIS ========== -->
        <div class="duas-colunas">
            <div class="painel">
                <h4>📅 Próximo Evento</h4>
                <?php if ($proximoEvento): ?>
                    <div style="padding: 8px 0;">
                        <div style="font-weight:600;color:var(--cinza-escuro);margin-bottom:6px;">
                            <?= htmlspecialchars($proximoEvento['titulo']) ?>
                        </div>
                        <div style="font-size:.82rem;color:var(--cinza-suave);">
                            🗓️ <?= formatarData($proximoEvento['data_evento'], true) ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="vazio">Nenhum evento agendado</div>
                <?php endif; ?>
            </div>

            <div class="painel">
                <h4>🎂 Aniversariantes do Mês</h4>
                <?php if ($aniversariantes): ?>
                    <ul class="lista-simples">
                        <?php foreach (array_slice($aniversariantes, 0, 5) as $a): ?>
                            <li>
                                <span class="nome"><?= htmlspecialchars($a['nome']) ?></span>
                                <span class="data">
                                    <?= date('d/m', strtotime($a['data_nascimento'])) ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <div class="vazio">Nenhum aniversariante este mês</div>
                <?php endif; ?>
            </div>
        </div>

    <?php elseif (isDancarino()): ?>
        <div class="stats-grid">
            <a href="mensalidades.php" class="stat">
                <div class="icone <?= $minhasMensalidades > 0 ? 'areia' : 'verde' ?>">
                    <?= $minhasMensalidades > 0 ? '⏳' : '✅' ?>
                </div>
                <div class="conteudo">
                    <span class="label">Minhas Mensalidades</span>
                    <span class="valor"><?= $minhasMensalidades ?></span>
                    <span class="sub"><?= $minhasMensalidades > 0 ? formatarMoeda($meuValorPendente) . ' pendente' : 'tudo em dia!' ?></span>
                </div>
            </a>

            <a href="eventos.php" class="stat">
                <div class="icone azul">🎉</div>
                <div class="conteudo">
                    <span class="label">Próximos Eventos</span>
                    <span class="valor"><?= $meusEventos ?></span>
                    <span class="sub">na minha categoria</span>
                </div>
            </a>

            <a href="vestimentas.php" class="stat">
                <div class="icone violeta">👕</div>
                <div class="conteudo">
                    <span class="label">Minhas Vestimentas</span>
                    <span class="valor"><?= $minhasVestimentas ?></span>
                    <span class="sub">em uso</span>
                </div>
            </a>

            <a href="perfil.php" class="stat">
                <div class="icone verde">👤</div>
                <div class="conteudo">
                    <span class="label">Meu Perfil</span>
                    <span class="valor">Ver</span>
                    <span class="sub">dados pessoais</span>
                </div>
            </a>
        </div>

    <?php else: // Responsável ?>
        <div class="stats-grid">
            <a href="perfil.php" class="stat">
                <div class="icone verde">👨‍👩‍👧</div>
                <div class="conteudo">
                    <span class="label">Meus Filhos</span>
                    <span class="valor"><?= $meusFilhos ?></span>
                    <span class="sub">cadastrados</span>
                </div>
            </a>

            <a href="mensalidades.php" class="stat">
                <div class="icone <?= $pendentesFilhos > 0 ? 'areia' : 'verde' ?>">
                    <?= $pendentesFilhos > 0 ? '⏳' : '✅' ?>
                </div>
                <div class="conteudo">
                    <span class="label">Pendências</span>
                    <span class="valor"><?= $pendentesFilhos ?></span>
                    <span class="sub"><?= $pendentesFilhos > 0 ? formatarMoeda($valorPendenteFilhos) : 'tudo em dia!' ?></span>
                </div>
            </a>

            <a href="eventos.php" class="stat">
                <div class="icone azul">🎉</div>
                <div class="conteudo">
                    <span class="label">Eventos</span>
                    <span class="valor">Ver</span>
                    <span class="sub">agenda do grupo</span>
                </div>
            </a>

            <a href="perfil.php" class="stat">
                <div class="icone violeta">👤</div>
                <div class="conteudo">
                    <span class="label">Meu Perfil</span>
                    <span class="valor">Editar</span>
                    <span class="sub">dados pessoais</span>
                </div>
            </a>
        </div>
    <?php endif; ?>

    <!-- ========== AÇÕES RÁPIDAS ========== -->
    <div class="secao-titulo">AÇÕES RÁPIDAS</div>
    <div class="cards-grid">
        <a href="eventos.php" class="card-acao">
            <div class="cabecalho">
                <span class="icone">🎉</span>
                <span class="seta">→</span>
            </div>
            <h3>Eventos</h3>
            <p>Ver agenda e confirmar presença</p>
        </a>

        <a href="mensalidades.php" class="card-acao">
            <div class="cabecalho">
                <span class="icone">💰</span>
                <span class="seta">→</span>
            </div>
            <h3>Mensalidades</h3>
            <p>Consultar pagamentos e pendências</p>
        </a>

        <a href="vestimentas.php" class="card-acao">
            <div class="cabecalho">
                <span class="icone">👕</span>
                <span class="seta">→</span>
            </div>
            <h3>Vestimentas</h3>
            <p>Controle de peças e entregas</p>
        </a>

        <a href="documentos.php" class="card-acao">
            <div class="cabecalho">
                <span class="icone">🖨️</span>
                <span class="seta">→</span>
            </div>
            <h3>Documentos</h3>
            <p>Crachás, certificados e listas</p>
        </a>

        <?php if (isCoordenador()): ?>
        <a href="usuarios.php" class="card-acao">
            <div class="cabecalho">
                <span class="icone">👥</span>
                <span class="seta">→</span>
            </div>
            <h3>Usuários</h3>
            <p>Gerenciar cadastros do grupo</p>
        </a>

        <a href="relatorio.php" class="card-acao">
            <div class="cabecalho">
                <span class="icone">📊</span>
                <span class="seta">→</span>
            </div>
            <h3>Relatório</h3>
            <p>Análise financeira anual</p>
        </a>
        <?php endif; ?>

        <a href="perfil.php" class="card-acao">
            <div class="cabecalho">
                <span class="icone">👤</span>
                <span class="seta">→</span>
            </div>
            <h3>Meu Perfil</h3>
            <p>Ver e editar meus dados</p>
        </a>
    </div>

</main>

<?php include '../includes/footer.php'; ?>
</body>
</html>