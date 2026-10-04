<?php
// Carregar .env
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linha) {
        if (str_starts_with(trim($linha), '#')) continue;
        [$k, $v] = array_pad(explode('=', $linha, 2), 2, '');
        $_ENV[trim($k)] = trim($v);
    }
}

return [
    'host'      => $_ENV['MAIL_HOST'] ?? 'smtp.gmail.com',
    'port'      => (int)($_ENV['MAIL_PORT'] ?? 587),
    'seguranca' => $_ENV['MAIL_SEC'] ?? 'tls',
    'usuario'   => $_ENV['MAIL_USER'] ?? '',
    'senha'     => $_ENV['MAIL_PASS'] ?? '',
    'nome'      => 'Piquete Sentinela da Fronteira',
    'from'      => $_ENV['MAIL_FROM'] ?? $_ENV['MAIL_USER'] ?? '',
    'reply'     => $_ENV['MAIL_USER'] ?? '',
    'debug'     => 0,
];