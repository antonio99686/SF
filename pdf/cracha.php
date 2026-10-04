<?php
/* ============================================================
   GERADOR DE CRACHÁ INDIVIDUAL
   Uso: /pdf/cracha.php?id=123
   ============================================================ */

require_once __DIR__ . '/_base.php';
exigirLogin();

// ---------- Validação do ID ----------
$id = (int)($_GET['id'] ?? 0);

// Regra: coordenador vê qualquer um; dançarino/responsável só veem os seus
if (isCoordenador()) {
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ? AND ativo = 1");
    $stmt->execute([$id]);
} elseif (isDancarino()) {
    // Só pode gerar o seu próprio
    if ($id !== $_SESSION['usuario_id']) {
        http_response_code(403);
        die('⛔ Você só pode gerar seu próprio crachá.');
    }
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ? AND ativo = 1");
    $stmt->execute([$id]);
} else {
    // Responsável: pode gerar dos filhos
    $stmt = $pdo->prepare("
        SELECT * FROM usuarios 
        WHERE id = ? AND ativo = 1 
          AND responsavel_id = ?
    ");
    $stmt->execute([$id, $_SESSION['usuario_id']]);
}

$u = $stmt->fetch();
if (!$u) {
    http_response_code(404);
    die('Usuário não encontrado ou sem permissão.');
}

// ---------- Dados para o crachá ----------
$nome     = $u['nome'];
$categoria = catLegivel($u['categoria'] ?? '');
$idade    = idadeLegivel($u['data_nascimento'] ?? null);
$telefone = $u['telefone'] ? formatarTelefone($u['telefone']) : '';
$cpf      = $u['cpf'] ? formatarCPF($u['cpf']) : '';
$logo     = logoBase64();
$ano      = date('Y');
$app      = APP_NOME;
$sub      = APP_SUB;

// Foto (se existir em /uploads/fotos/{id}.jpg)
$fotoPath = __DIR__ . "/../uploads/fotos/{$u['id']}.jpg";
$fotoHtml = '';
if (file_exists($fotoPath)) {
    $foto64 = base64_encode(file_get_contents($fotoPath));
    $fotoHtml = "<img src='data:image/jpeg;base64,$foto64' 
                      style='width:110px;height:130px;object-fit:cover;
                             border-radius:8px;border:3px solid #fff;'>";
} else {
    // Iniciais
    $partes = explode(' ', trim($nome));
    $iniciais = strtoupper(substr($partes[0], 0, 1) . (isset($partes[1]) ? substr($partes[1], 0, 1) : ''));
    $fotoHtml = "<div style='width:110px;height:130px;border-radius:8px;
                              background:#FEDD00;color:#006B29;
                              font-size:42px;font-weight:bold;
                              display:flex;align-items:center;justify-content:center;
                              border:3px solid #fff;'>$iniciais</div>";
}

$html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 0; }
        body { margin: 0; padding: 0; font-family: 'DejaVu Sans', sans-serif; }
        
        .cracha {
            width: 340px;
            height: 210px;
            background: linear-gradient(135deg, #006B29 0%, #009739 55%, #009FE3 100%);
            border-radius: 14px;
            overflow: hidden;
            position: relative;
            color: white;
            box-shadow: 0 4px 12px rgba(0,0,0,.15);
        }
        
        /* Faixa superior */
        .faixa-topo {
            background: linear-gradient(90deg, #006B29, #009739);
            border-bottom: 4px solid #FEDD00;
            padding: 8px 14px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .faixa-topo img { width: 32px; height: 32px; }
        .faixa-topo .titulo {
            font-size: 11px;
            font-weight: bold;
            letter-spacing: .5px;
            line-height: 1.2;
        }
        .faixa-topo .titulo small {
            display: block;
            color: #FEDD00;
            font-size: 7px;
            letter-spacing: 1.5px;
            font-weight: normal;
        }
        
        /* Faixa lateral amarela */
        .faixa-lateral {
            position: absolute;
            top: 0; left: 0;
            width: 6px;
            height: 100%;
            background: #FEDD00;
        }
        
        /* Conteúdo */
        .conteudo {
            padding: 12px 14px 12px 20px;
            display: flex;
            gap: 12px;
        }
        
        .foto { flex-shrink: 0; }
        
        .info {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .info .nome {
            font-size: 15px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: .3px;
            margin-bottom: 3px;
            line-height: 1.1;
        }
        .info .categoria {
            display: inline-block;
            background: #FEDD00;
            color: #006B29;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 8px;
            font-weight: bold;
            letter-spacing: .5px;
            margin-bottom: 6px;
            width: fit-content;
        }
        .info .dado {
            font-size: 8px;
            opacity: .9;
            margin: 1px 0;
        }
        .info .dado strong { color: #FEDD00; font-weight: bold; }
        
        /* Rodapé */
        .rodape {
            position: absolute;
            bottom: 0; left: 0; right: 0;
            background: rgba(0,0,0,.25);
            padding: 4px 14px;
            font-size: 7px;
            text-align: center;
            letter-spacing: .5px;
        }
        .rodape .frase {
            color: #FEDD00;
            font-style: italic;
        }
        
        /* Marca d'água */
        .marca-agua {
            position: absolute;
            right: -30px;
            bottom: -30px;
            width: 140px;
            opacity: .06;
        }
    </style>
</head>
<body>
    <div class="cracha">
        <div class="faixa-lateral"></div>
        
        <div class="faixa-topo">
            <img src="$logo" alt="logo">
            <div class="titulo">
                $app
                <small>$sub</small>
            </div>
        </div>
        
        <div class="conteudo">
            <div class="foto">$fotoHtml</div>
            <div class="info">
                <div class="nome">$nome</div>
                <div class="categoria">$categoria</div>
                <div class="dado"><strong>Idade:</strong> $idade</div>
                <div class="dado"><strong>Cadastro:</strong> #{$u['id']}</div>
                <div class="dado"><strong>Validade:</strong> 31/12/$ano</div>
            </div>
        </div>
        
        <div class="rodape">
            <span class="frase">"Unindo forças é bem mais fácil"</span>
        </div>
    </div>
</body>
</html>
HTML;

// ---------- Gera o PDF ----------
$dompdf = novoPdf('landscape', [0, 0, 241.89, 340.16]); // 85.6mm x 120.3mm em pontos
$nomeArq = 'cracha_' . nomeArquivoSeguro($nome) . '.pdf';

renderizarPdf($dompdf, $html, $nomeArq, true);