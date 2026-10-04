<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
exigirLogin();

// Dançarinos e responsáveis veem um painel simplificado
$dançarinos = [];
$eventos = [];

if (isCoordenador()) {
    $dançarinos = $pdo->query("
        SELECT id, nome, categoria 
        FROM usuarios 
        WHERE tipo = 'dancarino' AND ativo = 1 
        ORDER BY nome
    ")->fetchAll();

    $eventos = $pdo->query("
        SELECT id, titulo, data_evento 
        FROM eventos 
        ORDER BY data_evento DESC
    ")->fetchAll();

} elseif (isResponsavel()) {
    // Só os filhos
    $stmt = $pdo->prepare("
        SELECT id, nome, categoria 
        FROM usuarios 
        WHERE tipo = 'dancarino' AND ativo = 1 
          AND responsavel_id = ? 
        ORDER BY nome
    ");
    $stmt->execute([$_SESSION['usuario_id']]);
    $dançarinos = $stmt->fetchAll();

} else {
    // Dançarino → só ele mesmo
    $stmt = $pdo->prepare("
        SELECT id, nome, categoria 
        FROM usuarios 
        WHERE id = ?
    ");
    $stmt->execute([$_SESSION['usuario_id']]);
    $dançarinos = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Documentos · Sentinela da Fronteira</title>
    <link rel="icon" href="../assets/img/logo.png">
    <link rel="stylesheet" href="../assets/css/style.css">
    <meta name="csrf-token" content="<?= csrfToken() ?>">
</head>
<body>
<?php include '../includes/header.php'; ?>
<main class="container">
    <h1>🖨️ Documentos</h1>
    <p style="color:#6D6E71;margin-bottom:25px;">
        Gere crachás, certificados e listas de presença em PDF.
    </p>

    <!-- ============ CRACHÁS ============ -->
    <section style="background:#fff;padding:25px;border-radius:12px;box-shadow:0 4px 15px rgba(0,0,0,.08);margin-bottom:25px;border-top:5px solid var(--verde);">
        <h2>🎫 Crachás</h2>
        <p style="color:#6D6E71;font-size:.9rem;margin:8px 0 15px;">
            Gere o crachá individual de um dançarino.
        </p>

        <div style="display:grid;grid-template-columns:1fr auto;gap:10px;align-items:end;">
            <label>Selecione o dançarino
                <select id="crachaUsuario">
                    <option value="">Escolha...</option>
                    <?php foreach ($dançarinos as $d): ?>
                        <option value="<?= $d['id'] ?>">
                            <?= htmlspecialchars($d['nome']) ?> (<?= formatarCategoria($d['categoria']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button class="btn btn-primary" onclick="gerarCracha()">🎫 Gerar PDF</button>
        </div>
    </section>

    <?php if (isCoordenador()): ?>
    <!-- ============ CERTIFICADOS ============ -->
    <section style="background:#fff;padding:25px;border-radius:12px;box-shadow:0 4px 15px rgba(0,0,0,.08);margin-bottom:25px;border-top:5px solid var(--amarelo);">
        <h2>🎓 Certificados</h2>
        <p style="color:#6D6E71;font-size:.9rem;margin:8px 0 15px;">
            Emita certificados de participação. Vincule a um evento (opcional).
        </p>

        <div style="display:grid;grid-template-columns:1fr 1fr auto;gap:10px;align-items:end;">
            <label>Dançarino
                <select id="certUsuario">
                    <option value="">Escolha...</option>
                    <?php foreach ($dançarinos as $d): ?>
                        <option value="<?= $d['id'] ?>">
                            <?= htmlspecialchars($d['nome']) ?> (<?= formatarCategoria($d['categoria']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Evento (opcional)
                <select id="certEvento">
                    <option value="">Sem evento específico</option>
                    <?php foreach ($eventos as $e): ?>
                        <option value="<?= $e['id'] ?>">
                            <?= htmlspecialchars($e['titulo']) ?> — <?= formatarData($e['data_evento']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button class="btn btn-warn" onclick="gerarCertificado()">🎓 Gerar</button>
        </div>

        <hr style="margin:20px 0;border:none;border-top:1px dashed #e5e7eb;">

        <p style="color:#6D6E71;font-size:.9rem;margin-bottom:10px;">
            <strong>Emissão em lote:</strong> gera certificados para todos os dançarinos de uma categoria.
        </p>
        <div style="display:grid;grid-template-columns:1fr 1fr auto;gap:10px;align-items:end;">
            <label>Evento
                <select id="loteEvento">
                    <option value="">Sem evento</option>
                    <?php foreach ($eventos as $e): ?>
                        <option value="<?= $e['id'] ?>">
                            <?= htmlspecialchars($e['titulo']) ?> — <?= formatarData($e['data_evento']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Categoria
                <select id="loteCategoria">
                    <option value="">Todas</option>
                    <option value="pre_mirim">Pré-Mirim</option>
                    <option value="mirim">Mirim</option>
                    <option value="juvenil">Juvenil</option>
                    <option value="adulto">Adulto</option>
                </select>
            </label>
            <button class="btn btn-success" onclick="gerarLoteCertificados()">📚 Gerar Lote</button>
        </div>
    </section>

    <!-- ============ LISTA DE PRESENÇA ============ -->
    <section style="background:#fff;padding:25px;border-radius:12px;box-shadow:0 4px 15px rgba(0,0,0,.08);margin-bottom:25px;border-top:5px solid var(--azul);">
        <h2>📋 Lista de Presença</h2>
        <p style="color:#6D6E71;font-size:.9rem;margin:8px 0 15px;">
            Gere uma folha para imprimir e colher assinaturas.
        </p>

        <div style="display:grid;grid-template-columns:1fr 1fr auto;gap:10px;align-items:end;">
            <label>Evento (opcional)
                <select id="listaEvento">
                    <option value="">Ensaio / Sem evento</option>
                    <?php foreach ($eventos as $e): ?>
                        <option value="<?= $e['id'] ?>">
                            <?= htmlspecialchars($e['titulo']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Categoria
                <select id="listaCategoria">
                    <option value="">Todas</option>
                    <option value="pre_mirim">Pré-Mirim</option>
                    <option value="mirim">Mirim</option>
                    <option value="juvenil">Juvenil</option>
                    <option value="adulto">Adulto</option>
                </select>
            </label>
            <button class="btn btn-info" onclick="gerarLista()">📋 Gerar</button>
        </div>
    </section>
    <?php endif; ?>
</main>

<?php include '../includes/footer.php'; ?>

<script src="../assets/js/api.js"></script>
<script>
function gerarCracha() {
    const id = document.getElementById('crachaUsuario').value;
    if (!id) return toast('Selecione um dançarino.', 'erro');
    window.open(`../pdf/cracha.php?id=${id}`, '_blank');
}

function gerarCertificado() {
    const id = document.getElementById('certUsuario').value;
    if (!id) return toast('Selecione um dançarino.', 'erro');
    const ev = document.getElementById('certEvento').value;
    window.open(`../pdf/certificado.php?id=${id}&evento_id=${ev}`, '_blank');
}

function gerarLoteCertificados() {
    const ev  = document.getElementById('loteEvento').value;
    const cat = document.getElementById('loteCategoria').value;
    if (!confirm('Gerar certificados em lote? Isso pode demorar um pouco.')) return;
    window.open(`../pdf/certificado-lote.php?evento_id=${ev}&categoria=${cat}`, '_blank');
}

function gerarLista() {
    const ev  = document.getElementById('listaEvento').value;
    const cat = document.getElementById('listaCategoria').value;
    window.open(`../pdf/lista-presenca.php?evento_id=${ev}&categoria=${cat}`, '_blank');
}
</script>
</body>
</html>