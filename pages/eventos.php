<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
exigirLogin();

// Cadastrar evento (só coordenador)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isCoordenador()) {
    $stmt = $pdo->prepare("INSERT INTO eventos (titulo, descricao, data_evento, local, categoria, criado_por) 
                           VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $_POST['titulo'], $_POST['descricao'], $_POST['data_evento'],
        $_POST['local'], $_POST['categoria'], $_SESSION['usuario_id']
    ]);
    header('Location: eventos.php');
    exit;
}

// Listar eventos conforme perfil
if (isCoordenador()) {
    $eventos = $pdo->query("SELECT * FROM eventos ORDER BY data_evento DESC")->fetchAll();
} elseif (isDancarino()) {
    $stmt = $pdo->prepare("SELECT * FROM eventos WHERE categoria IN ('todos', ?) ORDER BY data_evento DESC");
    $stmt->execute([$_SESSION['categoria']]);
    $eventos = $stmt->fetchAll();
} else {
    // Responsável: eventos dos filhos
    $stmt = $pdo->prepare("SELECT DISTINCT e.* FROM eventos e
        JOIN usuarios u ON u.responsavel_id = ?
        WHERE e.categoria IN ('todos', u.categoria)
        ORDER BY e.data_evento DESC");
    $stmt->execute([$_SESSION['usuario_id']]);
    $eventos = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Eventos</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<?php include '../includes/header.php'; ?>
<main class="container">
    <h1>Eventos</h1>

    <?php if (isCoordenador()): ?>
    <details style="margin:20px 0; background:#fff; padding:20px; border-radius:10px;">
        <summary style="cursor:pointer; font-weight:bold;">➕ Novo Evento</summary>
        <form method="POST" style="margin-top:15px;">
            <input type="text" name="titulo" placeholder="Título" required style="width:100%;padding:8px;margin:5px 0;">
            <textarea name="descricao" placeholder="Descrição" style="width:100%;padding:8px;margin:5px 0;"></textarea>
            <input type="datetime-local" name="data_evento" required style="width:100%;padding:8px;margin:5px 0;">
            <input type="text" name="local" placeholder="Local" style="width:100%;padding:8px;margin:5px 0;">
            <select name="categoria" style="width:100%;padding:8px;margin:5px 0;">
                <option value="todos">Todos</option>
                <option value="pre_mirim">Pré-Mirim</option>
                <option value="mirim">Mirim</option>
                <option value="juvenil">Juvenil</option>
                <option value="adulto">Adulto</option>
            </select>
            <button class="btn btn-primary" type="submit">Salvar</button>
        </form>
    </details>
    <?php endif; ?>

    <table>
        <tr><th>Título</th><th>Data</th><th>Local</th><th>Categoria</th></tr>
        <?php foreach ($eventos as $e): ?>
        <tr>
            <td><?= htmlspecialchars($e['titulo']) ?></td>
            <td><?= date('d/m/Y H:i', strtotime($e['data_evento'])) ?></td>
            <td><?= htmlspecialchars($e['local']) ?></td>
            <td><?= htmlspecialchars($e['categoria']) ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
</main>
<?php include '../includes/footer.php'; ?>
</body>
</html>