<?php
/* ============================================================
   HELPERS PARA GERAÇÃO DE PDF
   ============================================================ */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

require_once __DIR__ . '/../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Retorna a logo em base64 (para usar em HTML de PDF).
 */
function logoBase64(): string {
    $caminho = __DIR__ . '/../assets/img/logo.png';
    if (!file_exists($caminho)) {
        return '';
    }
    $tipo = mime_content_type($caminho);
    $dados = base64_encode(file_get_contents($caminho));
    return "data:$tipo;base64,$dados";
}

/**
 * Cria uma instância do Dompdf já configurada.
 */
function novoPdf(string $orientacao = 'portrait', string $papel = 'A4'): Dompdf {
    $options = new Options();
    $options->set('isRemoteEnabled', true);   // permite carregar imagens locais
    $options->set('isHtml5ParserEnabled', true);
    $options->set('defaultFont', 'DejaVu Sans');
    $options->set('chroot', realpath(__DIR__ . '/..'));  // raiz permitida

    $dompdf = new Dompdf($options);
    $dompdf->setPaper($papel, $orientacao);
    return $dompdf;
}

/**
 * Renderiza o HTML no PDF e envia para o navegador (download ou inline).
 */
function renderizarPdf(Dompdf $dompdf, string $html, string $nomeArquivo, bool $download = true): void {
    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->render();

    $dompdf->stream($nomeArquivo, [
        'Attachment' => $download ? 1 : 0,
    ]);
    exit;
}

/**
 * Sanitiza nome para nome de arquivo.
 */
function nomeArquivoSeguro(string $nome): string {
    $nome = preg_replace('/[^A-Za-z0-9_\- ]/', '', $nome);
    $nome = preg_replace('/\s+/', '_', trim($nome));
    return $nome ?: 'arquivo';
}

/**
 * Formata categoria legível.
 */
function catLegivel(?string $cat): string {
    return formatarCategoria($cat);
}

/**
 * Calcula idade formatada: "12 anos" ou "—".
 */
function idadeLegivel(?string $dataNasc): string {
    $idade = calcularIdade($dataNasc);
    return $idade !== null ? "$idade anos" : '—';
}