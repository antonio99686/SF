<?php
/* ============================================================
   PIQUETE SENTINELA DA FRONTEIRA · COHAB II
   ------------------------------------------------------------
   Arquivo central de autenticação e helpers.
   
   Contém:
   - Sessão e controle de acesso (login, tipos, permissões)
   - Segurança: CSRF, bloqueio por tentativas, session fixation
   - Validações: CPF, telefone, email, idade
   - Formatações: CPF, telefone, moeda, data
   - Helpers utilitários
   ============================================================ */

// ============================================================
// 0) SESSÃO
// ============================================================
if (session_status() === PHP_SESSION_NONE) {
    // Configurações seguras de cookie de sessão
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// ============================================================
// 1) CONTROLE DE ACESSO
// ============================================================

function usuarioLogado(): bool {
    return isset($_SESSION['usuario_id']);
}

function isCoordenador(): bool {
    return usuarioLogado() && ($_SESSION['tipo'] ?? '') === 'coordenador';
}

function isDancarino(): bool {
    return usuarioLogado() && ($_SESSION['tipo'] ?? '') === 'dancarino';
}

function isResponsavel(): bool {
    return usuarioLogado() && ($_SESSION['tipo'] ?? '') === 'responsavel';
}

function ehMenorDeIdade(?string $dataNasc): bool {
    $idade = calcularIdade($dataNasc);
    return $idade !== null && $idade < 18;
}

/**
 * Redireciona para login se não estiver logado.
 * Respeita a origem (público vs admin) pela flag $_SESSION['ultimo_acesso'].
 */
function exigirLogin(): void {
    if (!usuarioLogado()) {
        $origem = $_SESSION['ultimo_acesso'] ?? 'publico';
        if ($origem === 'admin') {
            header('Location: /auth/admin/login.php');
        } else {
            header('Location: /auth/login.php');
        }
        exit;
    }
}

function exigirCoordenador(): void {
    exigirLogin();
    if (!isCoordenador()) {
        http_response_code(403);
        die('⛔ Acesso negado. Apenas coordenadores.');
    }
}

function exigirTipo(string ...$tipos): void {
    exigirLogin();
    if (!in_array($_SESSION['tipo'] ?? '', $tipos, true)) {
        http_response_code(403);
        die('⛔ Acesso negado.');
    }
}

/**
 * Redireciona para o dashboard após login bem-sucedido.
 */
function redirecionarAposLogin(): void {
    header('Location: /pages/dashboard.php');
    exit;
}

// ============================================================
// 2) CSRF (Cross-Site Request Forgery)
// ============================================================

/**
 * Gera (ou retorna) o token CSRF da sessão atual.
 */
function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Retorna um <input hidden> com o token CSRF.
 */
function csrfCampo(): string {
    return '<input type="hidden" name="csrf_token" value="' . csrfToken() . '">';
}

/**
 * Valida o token recebido. Encerra com 403 se inválido.
 * Aceita via POST ou header X-CSRF-Token (para AJAX).
 */
function csrfVerificar(): void {
    $enviado = $_POST['csrf_token']
        ?? $_SERVER['HTTP_X_CSRF_TOKEN']
        ?? '';

    if (!$enviado || !hash_equals($_SESSION['csrf_token'] ?? '', $enviado)) {
        http_response_code(403);
        die('⛔ Token de segurança inválido. Recarregue a página e tente novamente.');
    }
}

/**
 * Valida CSRF automaticamente em requisições POST.
 */
function csrfProtegerPost(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrfVerificar();
    }
}

// ============================================================
// 3) BLOQUEIO POR TENTATIVAS DE LOGIN
// ============================================================

const LOGIN_MAX_TENTATIVAS = 5;
const LOGIN_JANELA_MIN     = 15;

/**
 * Retorna o IP real do cliente (considerando proxy).
 */
function ipCliente(): string {
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($ips[0]);
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * Verifica se o email/IP está bloqueado.
 * @return array{0:bool,1:int} [bloqueado, minutosRestantes]
 */
function loginEstaBloqueado(PDO $pdo, string $email): array {
    $ip = ipCliente();

    $stmt = $pdo->prepare("
        SELECT COUNT(*) AS tentativas, MIN(criado_em) AS primeira
        FROM login_tentativas
        WHERE sucesso = 0
          AND criado_em > NOW() - INTERVAL ? MINUTE
          AND (email = ? OR ip = ?)
    ");
    $stmt->execute([LOGIN_JANELA_MIN, $email, $ip]);
    $r = $stmt->fetch();

    if ((int)$r['tentativas'] >= LOGIN_MAX_TENTATIVAS) {
        $primeira = new DateTime($r['primeira']);
        $agora    = new DateTime();
        $minutos  = LOGIN_JANELA_MIN - (int)$primeira->diff($agora)->i;
        return [true, max(1, $minutos)];
    }
    return [false, 0];
}

/**
 * Registra uma tentativa de login.
 */
function registrarTentativa(PDO $pdo, string $email, bool $sucesso): void {
    $pdo->prepare("INSERT INTO login_tentativas (email, ip, sucesso) VALUES (?,?,?)")
        ->execute([$email, ipCliente(), $sucesso ? 1 : 0]);

    if ($sucesso) {
        // Sucesso → limpa tentativas anteriores
        $pdo->prepare("DELETE FROM login_tentativas 
                       WHERE (email = ? OR ip = ?) AND sucesso = 0")
            ->execute([$email, ipCliente()]);
    }
}

/**
 * Quantas tentativas ainda restam.
 */
function tentativasRestantes(PDO $pdo, string $email): int {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM login_tentativas
        WHERE sucesso = 0
          AND criado_em > NOW() - INTERVAL ? MINUTE
          AND (email = ? OR ip = ?)
    ");
    $stmt->execute([LOGIN_JANELA_MIN, $email, ipCliente()]);
    return max(0, LOGIN_MAX_TENTATIVAS - (int)$stmt->fetchColumn());
}

// ============================================================
// 4) VALIDAÇÕES
// ============================================================

/**
 * Valida CPF brasileiro (com dígitos verificadores).
 */
function validarCPF(?string $cpf): bool {
    $cpf = preg_replace('/\D/', '', (string)$cpf);

    if (strlen($cpf) !== 11) return false;
    if (preg_match('/^(\d)\1{10}$/', $cpf)) return false;

    for ($t = 9; $t < 11; $t++) {
        $soma = 0;
        for ($i = 0; $i < $t; $i++) {
            $soma += (int)$cpf[$i] * (($t + 1) - $i);
        }
        $digito = ((10 * $soma) % 11) % 10;
        if ((int)$cpf[$t] !== $digito) return false;
    }
    return true;
}

/**
 * Valida email.
 */
function emailValido(?string $email): bool {
    return filter_var((string)$email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Valida telefone (10 ou 11 dígitos após limpeza).
 */
function validarTelefone(?string $tel): bool {
    $tel = preg_replace('/\D/', '', (string)$tel);
    return strlen($tel) === 10 || strlen($tel) === 11;
}

/**
 * Valida CEP (8 dígitos).
 */
function validarCEP(?string $cep): bool {
    $cep = preg_replace('/\D/', '', (string)$cep);
    return strlen($cep) === 8;
}

/**
 * Calcula idade a partir de uma data de nascimento (Y-m-d).
 * Retorna null se a data for inválida.
 */
function calcularIdade(?string $dataNasc): ?int {
    if (!$dataNasc) return null;
    try {
        $dn = new DateTime($dataNasc);
        $hoje = new DateTime();
        return $dn->diff($hoje)->y;
    } catch (Exception $e) {
        return null;
    }
}

// ============================================================
// 5) FORMATAÇÕES
// ============================================================

/**
 * Remove tudo que não é dígito.
 */
function apenasDigitos(?string $str): string {
    return preg_replace('/\D/', '', (string)$str);
}

/**
 * Formata CPF: 000.000.000-00
 */
function formatarCPF(?string $cpf): string {
    $cpf = apenasDigitos($cpf);
    if (strlen($cpf) !== 11) return $cpf;
    return substr($cpf,0,3).'.'.substr($cpf,3,3).'.'.substr($cpf,6,3).'-'.substr($cpf,9,2);
}

/**
 * Formata telefone: (99) 99999-9999 ou (99) 9999-9999
 */
function formatarTelefone(?string $tel): string {
    $tel = apenasDigitos($tel);
    if (strlen($tel) === 11) {
        return '('.substr($tel,0,2).') '.substr($tel,2,5).'-'.substr($tel,7,4);
    }
    if (strlen($tel) === 10) {
        return '('.substr($tel,0,2).') '.substr($tel,2,4).'-'.substr($tel,6,4);
    }
    return $tel;
}

/**
 * Formata CEP: 00000-000
 */
function formatarCEP(?string $cep): string {
    $cep = apenasDigitos($cep);
    if (strlen($cep) !== 8) return $cep;
    return substr($cep,0,5).'-'.substr($cep,5,3);
}

/**
 * Formata valor em R$ (com vírgula).
 */
function formatarMoeda($valor): string {
    return 'R$ ' . number_format((float)$valor, 2, ',', '.');
}

/**
 * Formata data para d/m/Y ou d/m/Y H:i.
 */
function formatarData(?string $data, bool $comHora = false): string {
    if (!$data) return '—';
    try {
        $dt = new DateTime($data);
        return $dt->format($comHora ? 'd/m/Y H:i' : 'd/m/Y');
    } catch (Exception $e) {
        return $data;
    }
}

/**
 * Formata categoria para exibição: pre_mirim → Pré-Mirim
 */
function formatarCategoria(?string $cat): string {
    $mapa = [
        'pre_mirim' => 'Pré-Mirim',
        'mirim'     => 'Mirim',
        'juvenil'   => 'Juvenil',
        'adulto'    => 'Adulto',
        'todos'     => 'Todos',
    ];
    return $mapa[$cat] ?? ucfirst((string)$cat);
}

/**
 * Formata status de mensalidade.
 */
function formatarStatusMensalidade(?string $st): string {
    $mapa = [
        'pendente' => '⏳ Pendente',
        'pago'     => '✅ Pago',
        'atrasado' => '⚠️ Atrasado',
    ];
    return $mapa[$st] ?? ucfirst((string)$st);
}

// ============================================================
// 6) HELPERS GERAIS
// ============================================================

/**
 * Escapa HTML para exibição segura.
 */
function e(?string $str): string {
    return htmlspecialchars((string)$str, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Lê JSON do corpo da requisição (para APIs).
 */
function bodyJson(): array {
    $raw = file_get_contents('php://input');
    $dados = json_decode($raw, true);
    return is_array($dados) ? $dados : [];
}

/**
 * Responde JSON e encerra (para APIs).
 */
function jsonOut(array $dados, int $code = 200): void {
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    http_response_code($code);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Responde erro em JSON.
 */
function jsonErro(string $msg, int $code = 400): void {
    jsonOut(['ok' => false, 'erro' => $msg], $code);
}

/**
 * Gera um token aleatório seguro (hex).
 */
function gerarToken(int $bytes = 32): string {
    return bin2hex(random_bytes($bytes));
}

/**
 * Destrói a sessão de forma completa.
 */
function destruirSessao(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }
    session_destroy();
}