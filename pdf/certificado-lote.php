<?php
/* ============================================================
   CERTIFICADOS EM LOTE — um por página
   Uso: /pdf/certificado-lote.php?evento_id=45&categoria=mirim
   ============================================================ */

require_once __DIR__ . '/_base.php';
exigirCoordenador();

$evento_id = (int)($_GET['evento_id'] ?? 0);
$categoria = $_GET['categoria'] ?? null;

// Evento
$evento = null;
if ($evento_id) {
    $stmt = $pdo->prepare("SELECT * FROM eventos WHERE id = ?");
    $stmt->execute([$evento_id]);
    $evento = $stmt->fetch();
}

// Lista de dançarinos
$sql = "SELECT * FROM usuarios WHERE tipo = 'dancarino' AND ativo = 1";
$params = [];
if ($categoria) {
    $sql .= " AND categoria = ?";
    $params[] = $categoria;
}
$sql .= " ORDER BY nome";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$dancarinos = $stmt->fetchAll();

if (!$dancarinos) {
    die('Nenhum dançarino encontrado com esses filtros.');
}

$logo        = logoBase64();
$ano         = date('Y');
$tituloEvento= $evento ? $evento['titulo'] : 'Atividades do Grupo';
$dataEvento  = $evento ? formatarData($evento['data_evento']) : '—';
$localEvento = $evento ? $evento['local'] : 'Uruguaiana/RS';
$cargaHoraria= $evento ? '8 horas' : '40 horas';

$meses = [
    1=>'janeiro', 2=>'fevereiro', 3=>'março', 4=>'abril',
    5=>'maio', 6=>'junho', 7=>'julho', 8=>'agosto',
    9=>'setembro', 10=>'outubro', 11=>'novembro', 12=>'dezembro',
];
$dataExtenso = date('d') . ' de ' . $meses[(int)date('n')] . ' de ' . date('Y');
$cidade = 'Uruguaiana/RS';

// Monta a página de cada certificado
$paginas = '';
foreach ($dancarinos as $u) {
    $nome      = htmlspecialchars($u['nome'], ENT_QUOTES, 'UTF-8');
    $categoriaTxt = catLegivel($u['categoria'] ?? '');

    $paginas .= <<<HTML
    <div class="pagina">
        <div class="moldura">
            <div class="canto canto-tl"></div>
            <div class="canto canto-tr"></div>
            <div class="canto canto-bl"></div>
            <div class="canto canto-br"></div>
            
            <div class="moldura-interna">
                <img src="$logo" class="marca-agua" alt="">
                
                <div class="cabecalho">
                    <img src="$logo" alt="logo">
                    <div class="nome-grupo">PIQUETE SENTINELA DA FRONTEIRA</div>
                    <div class="sub">COHAB II · URUGUAIANA/RS · DESDE 1989</div>
                </div>
                
                <div class="titulo">CERTIFICADO</div>
                <div class="subtitulo">DE PARTICIPAÇÃO</div>
                
                <div class="corpo">
                    <p class="texto">Certificamos que</p>
                    <div class="nome-destaque">$nome</div>
                    <div class="categoria-badge">CATEGORIA: $categoriaTxt</div>
                    <p class="texto">
                        participou com dedicação e comprometimento
                        <strong>$tituloEvento</strong>, realizado em <strong>$dataEvento</strong>,
                        no município de <strong>$localEvento</strong>,
                        com carga horária total de <strong>$cargaHoraria</strong>,
                        demonstrando disciplina, respeito e amor à cultura gaúcha.
                    </p>
                </div>
                
                <div class="rodape">
                    <div class="assinatura">
                        <div class="linha-assinatura">
                            <div class="nome-coord">Coordenação do Grupo</div>
                            Piquete Sentinela da Fronteira
                        </div>
                    </div>
                    <div class="selo">
                        <div>OFICIAL</div>
                        <div class="ano">$ano</div>
                        <div>SENTINELA</div>
                    </div>
                    <div class="assinatura">
                        <div class="linha-assinatura">
                            <div class="nome-coord">$cidade</div>
                            $dataExtenso
                        </div>
                    </div>
                </div>
                
                <div class="frase-rodape">"Unindo forças é bem mais fácil"</div>
            </div>
        </div>
    </div>
HTML;
}

$html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 0; }
        body { margin: 0; font-family: 'DejaVu Sans', sans-serif; }
        
        .pagina {
            width: 100%;
            height: 100%;
            padding: 20px;
            background: #F5F7F5;
            box-sizing: border-box;
            page-break-after: always;
        }
        .pagina:last-child { page-break-after: auto; }
        
        .moldura {
            width: 100%;
            height: 100%;
            border: 10px double #009739;
            padding: 20px;
            box-sizing: border-box;
            position: relative;
            background: white;
            background-image: 
                radial-gradient(circle at 15% 15%, rgba(254,221,0,.08), transparent 40%),
                radial-gradient(circle at 85% 85%, rgba(0,159,227,.08), transparent 40%);
        }
        .moldura-interna {
            border: 2px solid #FEDD00;
            height: 100%;
            padding: 25px 40px;
            box-sizing: border-box;
            position: relative;
        }
        .canto { position: absolute; width: 40px; height: 40px; border: 4px solid #009739; }
        .canto-tl { top: 8px; left: 8px; border-right: none; border-bottom: none; }
        .canto-tr { top: 8px; right: 8px; border-left: none; border-bottom: none; }
        .canto-bl { bottom: 8px; left: 8px; border-right: none; border-top: none; }
        .canto-br { bottom: 8px; right: 8px; border-left: none; border-top: none; }
        .cabecalho { text-align: center; margin-bottom: 8px; }
        .cabecalho img { width: 70px; }
        .cabecalho .nome-grupo { font-size: 13px; font-weight: bold; color: #006B29; letter-spacing: 2px; margin-top: 4px; }
        .cabecalho .sub { font-size: 8px; color: #6D6E71; letter-spacing: 3px; }
        .titulo { text-align: center; font-size: 38px; font-weight: bold; color: #009739; letter-spacing: 6px; margin: 15px 0 5px; text-shadow: 2px 2px 0 #FEDD00; }
        .subtitulo { text-align: center; font-size: 12px; color: #6D6E71; letter-spacing: 3px; margin-bottom: 20px; }
        .corpo { text-align: center; padding: 0 30px; }
        .texto { font-size: 12px; line-height: 1.9; color: #1F2937; text-align: justify; text-align-last: center; }
        .nome-destaque { font-size: 24px; font-weight: bold; color: #006B29; letter-spacing: 2px; text-transform: uppercase; border-bottom: 2px solid #FEDD00; display: inline-block; padding: 0 25px 5px; margin: 8px 0; }
        .categoria-badge { display: inline-block; background: #FEDD00; color: #006B29; padding: 3px 15px; border-radius: 12px; font-size: 9px; font-weight: bold; letter-spacing: 1px; margin: 5px 0; }
        .rodape { position: absolute; bottom: 20px; left: 40px; right: 40px; display: flex; justify-content: space-between; align-items: flex-end; }
        .assinatura { text-align: center; width: 220px; }
        .linha-assinatura { border-top: 1.5px solid #1F2937; padding-top: 4px; margin-top: 35px; font-size: 9px; color: #1F2937; }
        .assinatura .nome-coord { font-size: 10px; font-weight: bold; color: #006B29; }
        .selo { width: 90px; height: 90px; border-radius: 50%; border: 3px solid #FEDD00; background: linear-gradient(135deg, #006B29, #009739); color: white; text-align: center; display: flex; flex-direction: column; justify-content: center; align-items: center; font-size: 7px; letter-spacing: 1px; }
        .selo .ano { font-size: 18px; font-weight: bold; color: #FEDD00; }
        .frase-rodape { position: absolute; bottom: 8px; left: 0; right: 0; text-align: center; font-size: 8px; color: #6D6E71; font-style: italic; }
        .marca-agua { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 400px; opacity: .03; }
    </style>
</head>
<body>
    $paginas
</body>
</html>
HTML;

$dompdf = novoPdf('landscape', 'A4');
$nomeArq = 'certificados_' . date('Ymd_His') . '.pdf';
renderizarPdf($dompdf, $html, $nomeArq, true);  