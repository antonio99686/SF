<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
exigirCoordenador();

// Filtro de período
$ano = (int)($_GET['ano'] ?? date('Y'));

// --- Total por mês ---
$stmt = $pdo->prepare("
    SELECT DATE_FORMAT(mes_referencia,'%Y-%m') AS mes,
           SUM(CASE WHEN status='pago' THEN valor ELSE 0 END) AS pago,
           SUM(CASE WHEN status='pendente' THEN valor ELSE 0 END) AS pendente,
           SUM(CASE WHEN status='atrasado' THEN valor ELSE 0 END) AS atrasado,
           COUNT(*) AS total
    FROM mensalidades
    WHERE YEAR(mes_referencia) = ?
    GROUP BY mes
    ORDER BY mes
");
$stmt->execute([$ano]);
$porMes = $stmt->fetchAll();

// --- Total por categoria ---
$stmt = $pdo->prepare("
    SELECT u.categoria,
           SUM(CASE WHEN m.status='pago' THEN m.valor ELSE 0 END) AS pago,
           SUM(CASE WHEN m.status!='pago' THEN m.valor ELSE 0 END) AS pendente
    FROM mensalidades m
    JOIN usuarios u ON u.id = m.usuario_id
    WHERE YEAR(m.mes_referencia) = ?
    GROUP BY u.categoria
");
$stmt->execute([$ano]);
$porCategoria = $stmt->fetchAll();

// --- Inadimplentes (pendentes ou atrasados) ---
$stmt = $pdo->prepare("
    SELECT u.id, u.nome, u.categoria, u.telefone,
           COUNT(m.id) AS qtd, SUM(m.valor) AS total
    FROM mensalidades m
    JOIN usuarios u ON u.id = m.usuario_id
    WHERE m.status != 'pago' AND YEAR(m.mes_referencia) = ?
    GROUP BY u.id
    ORDER BY total DESC
");
$stmt->execute([$ano]);
$inadimplentes = $stmt->fetchAll();

// --- Totais gerais ---
$stmt = $pdo->prepare("
    SELECT 
        SUM(CASE WHEN status='pago' THEN valor ELSE 0 END) AS total_pago,
        SUM(CASE WHEN status!='pago' THEN valor ELSE 0 END) AS total_pendente,
        COUNT(*) AS total_registros
    FROM mensalidades WHERE YEAR(mes_referencia) = ?
");
$stmt->execute([$ano]);
$totais = $stmt->fetch();

// --- Anos disponíveis ---
$anos = $pdo->query("SELECT DISTINCT YEAR(mes_referencia) AS ano FROM mensalidades ORDER BY ano DESC")->fetchAll();

// --- Valor máximo para escala do gráfico ---
$maxValor = 0;
foreach ($porMes as $m) {
    $maxValor = max($maxValor, $m['pago'], $m['pendente'], $m['atrasado']);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Relatório Financeiro - Sentinela</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .bar-wrap { display:flex; align-items:flex-end; gap:4px; height:180px; padding:10px; background:#fff; border-radius:10px; }
        .bar-col { flex:1; display:flex; flex-direction:column; align-items:center; gap:2px; }
        .bar { width:100%; border-radius:4px 4px 0 0; transition:.3s; }
        .bar-pago { background:#16a34a; }
        .bar-pend { background:#f59e0b; }
        .bar-atre { background:#dc2626; }
        .bar-label { font-size:.7rem; color:#666; }
        .legenda { display:flex; gap:20px; margin-top:10px; }
        .legenda span::before { content:'■ '; font-size:1.2rem; }
        .legenda .l-pago::before { color:#16a34a; }
        .legenda .l-pend::before { color:#f59e0b; }
        .legenda .l-atre::before { color:#dc2626; }
    </style>
</head>
<body>
<?php include '../includes/header.php'; ?>
<main class="container">
    <div style="display:flex;justify-content:space-between;align-items:center;">
        <h1>📊 Relatório Financeiro <?= $ano ?></h1>
        <form method="GET">
            <select name="ano" onchange="this.form.submit()" style="padding:8px;">
                <?php foreach ($anos as $a): ?>
                    <option value="<?= $a['ano'] ?>" <?= $a['ano']==$ano?'selected':'' ?>><?= $a['ano'] ?></option>
                <?php endforeach; ?>
                <?php if (!$anos): ?><option><?= date('Y') ?></option><?php endif; ?>
            </select>
        </form>
    </div>

    <!-- Cards resumo -->
    <div class="grid-cards" style="margin-top:20px;">
        <div class="card">
            <h3>💚 Total Recebido</h3>
            <p style="color:#16a34a;font-size:1.6rem;font-weight:bold;">
                R$ <?= number_format($totais['total_pago'] ?? 0, 2, ',', '.') ?>
            </p>
        </div>
        <div class="card">
            <h3>⚠️ Total Pendente</h3>
            <p style="color:#dc2626;font-size:1.6rem;font-weight:bold;">
                R$ <?= number_format($totais['total_pendente'] ?? 0, 2, ',', '.') ?>
            </p>
        </div>
        <div class="card">
            <h3>📄 Registros</h3>
            <p style="font-size:1.6rem;font-weight:bold;"><?= $totais['total_registros'] ?? 0 ?></p>
        </div>
    </div>

    <!-- Gráfico de barras -->
    <h2 style="margin-top:30px;">📈 Evolução Mensal</h2>
    <?php if ($porMes): ?>
    <div class="bar-wrap" style="margin-top:10px;">
        <?php foreach ($porMes as $m): 
            $h_pago = $maxValor > 0 ? ($m['pago']/$maxValor)*150 : 0;
            $h_pend = $maxValor > 0 ? ($m['pendente']/$maxValor)*150 : 0;
            $h_atre = $maxValor > 0 ? ($m['atrasado']/$maxValor)*150 : 0;
        ?>
        <div class="bar-col">
            <div class="bar bar-atre" style="height:<?= $h_atre ?>px;" title="Atrasado: R$ <?= number_format($m['atrasado'],2,',','.') ?>"></div>
            <div class="bar bar-pend" style="height:<?= $h_pend ?>px;" title="Pendente: R$ <?= number_format($m['pendente'],2,',','.') ?>"></div>
            <div class="bar bar-pago" style="height:<?= $h_pago ?>px;" title="Pago: R$ <?= number_format($m['pago'],2,',','.') ?>"></div>
            <span class="bar-label"><?= substr($m['mes'], -2) ?>/<?= substr($m['mes'], 2, 2) ?></span>
        </div>
        <?php endforeach; ?>
    </div>
    <div class="legenda">
        <span class="l-pago">Pago</span>
        <span class="l-pend">Pendente</span>
        <span class="l-atre">Atrasado</span>
    </div>
    <?php else: ?>
        <p style="padding:20px;background:#fff;border-radius:10px;">Sem dados para este ano.</p>
    <?php endif; ?>

    <!-- Por categoria -->
    <h2 style="margin-top:30px;">📂 Por Categoria</h2>
    <table style="margin-top:10px;">
        <tr><th>Categoria</th><th>Pago</th><th>Pendente</th><th>Total</th></tr>
        <?php foreach ($porCategoria as $c): 
            $tot = $c['pago'] + $c['pendente'];
        ?>
        <tr>
            <td><?= ucfirst(str_replace('_','-',$c['categoria'] ?? 'sem categoria')) ?></td>
            <td style="color:#16a34a;font-weight:bold;">R$ <?= number_format($c['pago'],2,',','.') ?></td>
            <td style="color:#dc2626;font-weight:bold;">R$ <?= number_format($c['pendente'],2,',','.') ?></td>
            <td>R$ <?= number_format($tot,2,',','.') ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$porCategoria): ?>
            <tr><td colspan="4" style="text-align:center;padding:15px;">Sem dados.</td></tr>
        <?php endif; ?>
    </table>

    <!-- Inadimplentes -->
    <h2 style="margin-top:30px;">⚠️ Inadimplentes</h2>
    <table style="margin-top:10px;">
        <tr><th>Nome</th><th>Categoria</th><th>Telefone</th><th>Mensalidades</th><th>Total Devido</th><th>Ações</th></tr>
        <?php foreach ($inadimplentes as $i): ?>
        <tr>
            <td><?= htmlspecialchars($i['nome']) ?></td>
            <td><?= ucfirst(str_replace('_','-',$i['categoria'] ?? '')) ?></td>
            <td><?= htmlspecialchars($i['telefone'] ?? '—') ?></td>
            <td><?= $i['qtd'] ?></td>
            <td style="color:#dc2626;font-weight:bold;">R$ <?= number_format($i['total'],2,',','.') ?></td>
            <td>
                <a class="btn btn-primary" href="perfil.php?id=<?= $i['id'] ?>">Ver Perfil</a>
                <a class="btn btn-success" href="mensalidades.php?usuario_id=<?= $i['id'] ?>">Cobrar</a>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$inadimplentes): ?>
            <tr><td colspan="6" style="text-align:center;padding:15px;">🎉 Nenhum inadimplente!</td></tr>
        <?php endif; ?>
    </table>

    <!-- Exportar CSV -->
    <div style="margin-top:30px;">
        <button class="btn btn-primary" onclick="exportarCSV()">📥 Exportar Inadimplentes (CSV)</button>
    </div>
</main>

<script>
function exportarCSV() {
    const rows = [
        ['Nome','Categoria','Telefone','Qtd','Total']
    ];
    document.querySelectorAll('table:last-of-type tr').forEach((tr, i) => {
        if (i === 0) return;
        const tds = tr.querySelectorAll('td');
        if (tds.length >= 5) {
            rows.push([...tds].slice(0,5).map(td => td.innerText.trim()));
        }
    });

    const csv = rows.map(r => r.map(c => `"${c}"`).join(',')).join('\n');
    const blob = new Blob(["\uFEFF"+csv], {type:'text/csv;charset=utf-8'});
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'inadimplentes_<?= $ano ?>.csv';
    a.click();
}
</script>
</body>
</html>