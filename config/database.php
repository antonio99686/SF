<?php
/* ============================================================
   CONEXÃO COM O BANCO DE DADOS
   ------------------------------------------------------------
   IMPORTANTE: NÃO inicie sessão aqui. O arquivo includes/auth.php
   já cuida disso. Este arquivo só faz a conexão PDO.
   ============================================================ */

// ------------------------------------------------------------
// CONFIGURAÇÕES — ajuste conforme seu ambiente
// ------------------------------------------------------------
$DB_HOST    = 'localhost';
$DB_NAME    = 'SF';
$DB_USER    = 'root';
$DB_PASS    = '';           // em produção, use senha forte
$DB_PORT    = 3306;
$DB_CHARSET = 'utf8mb4';

// ------------------------------------------------------------
// Detecção de ambiente (dev / produção)
// ------------------------------------------------------------
$emProducao = (
    ($_SERVER['HTTP_HOST'] ?? '') !== 'localhost'
    && ($_SERVER['HTTP_HOST'] ?? '') !== '127.0.0.1'
);

// Em produção, erros não são exibidos
if ($emProducao) {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(E_ALL);       // continua registrando, mas não exibindo
} else {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
}

// ------------------------------------------------------------
// Conexão PDO
// ------------------------------------------------------------
$dsn = "mysql:host=$DB_HOST;port=$DB_PORT;dbname=$DB_NAME;charset=$DB_CHARSET";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,      // prepared statements reais
    PDO::ATTR_PERSISTENT         => false,
];

try {
    $pdo = new PDO($dsn, $DB_USER, $DB_PASS, $options);

    // Define o timezone do Brasil após conectar
    // (evita a constante obsoleta PDO::MYSQL_ATTR_INIT_COMMAND do PHP 8.5)
    $pdo->exec("SET time_zone = '-03:00'");

} catch (PDOException $e) {
    // Loga o erro real, mas mostra mensagem genérica ao usuário
    error_log('[DB ERROR] ' . $e->getMessage());

    http_response_code(500);
    if ($emProducao) {
        die('⚠️ Sistema temporariamente indisponível. Tente novamente em instantes.');
    } else {
        die('⚠️ Erro na conexão: ' . $e->getMessage());
    }
}

// ------------------------------------------------------------
// Constantes úteis do sistema
// ------------------------------------------------------------
define('BASE_URL', $emProducao
    ? 'https://seudominio.com.br'
    : 'http://localhost/SF'          // 👈 ajustado para o nome da sua pasta
);

define('APP_NOME',     'Piquete Sentinela da Fronteira');
define('APP_SUB',      'COHAB II · Uruguaiana/RS');
define('APP_FUNDACAO', '17/06/1989');
define('APP_FRASE',    '"Unindo forças é bem mais fácil"');

define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('LOGO_PATH',  BASE_URL . '/assets/img/logo.png');