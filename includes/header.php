<?php
$menu = [
    'dashboard.php'    => '🏠 Início',
    'eventos.php'      => '🎉 Eventos',
    'mensalidades.php' => '💰 Mensalidades',
    'vestimentas.php'  => '👕 Vestimentas',
    'perfil.php'       => '👤 Perfil',
];
if (isCoordenador()) {
    $menu['usuarios.php']  = '👥 Usuários';
    $menu['relatorio.php'] = '📊 Relatório';
}

// URL de logout depende de quem está logado
$urlLogout = isCoordenador() ? '../auth/admin/logout.php' : '../auth/logout.php';
?>
<header class="topo">
    <a href="dashboard.php" class="marca">
        <img src="../assets/img/logo.png" alt="Sentinela da Fronteira">
        <div class="texto">
            <strong>Piquete Sentinela</strong>
            <small>DA FRONTEIRA · COHAB II</small>
        </div>
    </a>
    <nav>
        <?php foreach ($menu as $url => $label): ?>
            <a href="<?= $url ?>"><?= $label ?></a>
        <?php endforeach; ?>
        <a href="<?= $urlLogout ?>" class="sair">🚪 Sair</a>
    </nav>
</header>