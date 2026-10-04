<?php
/* ============================================================
   LISTA DE PRESENÇA (para imprimir e assinar)
   Uso: /pdf/lista-presenca.php?evento_id=45&categoria=mirim
   ============================================================ */

require_once __DIR__ . '/_base.php';
exigirCoordenador();

$evento_id = (int)($_GET['evento_id'] ?? 0);
$categoria = $_GET['categoria'] ?? null;

$evento = null;
if ($evento_id) {
    $stmt = $pdo->prepare("SELECT * FROM eventos WHERE id = ?");
    $stmt->execute([$evento_id]);
    $evento = $stmt->fetch();
}

// Filtro de dançarinos
$sql = "SELECT id, nome, categoria, data_nascimento 
        FROM usuarios 
        WHERE tipo = 'dancarino' AND ativo = 1";
$params = [];
if ($categoria) {
    $sql .= " AND categoria = ?";
    $params[] = $categoria;
}
$sql .= " ORDER BY categoria, nome";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$dancarinos = $stmt->fetchAll();

$logo       = logoBase64();
$titulo     = $evento ? $evento['titulo'] : 'Ensaio / Atividade';
$dataEvento = $evento ? formatarData($evento['data_evento'], true) : date('d/m/Y');
$local      = $evento ? $evento['local'] : '—';
$total      = count($dancarinos);

// Monta as linhas
$linhas = '';
$i = 1;
foreach ($dancarinos as $d) {
    $cat = catLegivel($d['categoria']);
    $linhas .= "
        <tr>
            <td style='text-align:center;'>{$i}</td>
            <td style='font-size:11px;'>{$d['nome']}</td>
            <td style='text-align:center;font-size:9px;'>{$cat}</td>
            <td style='height:22px;'></td>
            <td style='height:22px;'></td>
        </tr>";
    $i++;
}

// Linhas extras se menos de 20
while ($i <= 25) {
    $linhas .= "
        <tr>
            <td style='text-align:center;color:#ccc;'>$i</td>
            <td></td><td></td><td></td><td></td>
        </tr>";
    $i++;
}

$html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 15mm 12mm; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: #1F2937; }
        
        .cabecalho {
            border-bottom: 4px solid #FEDD00;
            padding-bottom: 10px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .cabecalho img { width: 55px; }
        .cabecalho .titulo {
            font-size: 15px;
            font-weight: bold;
            color: #006B29;
            letter-spacing: 1px;
        }
        .cabecalho .sub {
            font-size: 8px;
            color: #6D6E71;
            letter-spacing: 2px;
        }
        
        .info-evento {
            background: #F5F7F5;
            padding: 10px 14px;
            border-radius: 8px;
            border-left: 5px solid #009739;
            margin-bottom: 15px;
            font-size: 10px;
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
        }
        .info-evento strong { color: #006B29; }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        table thead th {
            background: #009739;
            color: white;
            padding: 8px 6px;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: .5px;
            border: 1px solid #006B29;
        }
        table tbody td {
            border: 1px solid #ddd;
            padding: 5px 6px;
            vertical-align: middle;
        }
        table tbody tr:nth-child(even) {
            background: #FAFCFA;
        }
        
        .rodape {
            margin-top: 20px;
            padding-top: 10px;
            border-top: 2px dashed #ddd;
            display: flex;
            justify-content: space-between;
            font-size: 8px;
            color: #6D6E71;
        }
        
        .assinatura-coord {
            margin-top: 30px;
            text-align: center;
            width: 250px;
            margin-left: auto;
            font-size: 9px;
        }
        .linha {
            border-top: 1px solid #1F2937;
            padding-top: 4px;
            color: #6D6E71;
        }
    </style>
</head>
<body>
    <div class="cabecalho">
        <img src="$logo">
        <div>
            <div class="titulo">PIQUETE SENTINELA DA FRONTEIRA</div>
            <div class="sub">COHAB II · URUGUAIANA/RS · LISTA DE PRESENÇA</div>
        </div>
    </div>
    
    <div class="info-evento">
        <div><strong>📌 Evento:</strong> $titulo</div>
        <div><strong>📅 Data:</strong> $dataEvento</div>
        <div><strong>📍 Local:</strong> $local</div>
        <div><strong>👥 Total:</strong> $total dançarinos</div>
    </div>
    
    <table>
        <thead>
            <tr>
                <th width="6%">#</th>
                <th width="40%">Nome</th>
                <th width="14%">Categoria</th>
                <th width="20%">Assinatura</th>
                <th width="20%">Observação</th>
            </tr>
        </thead>
        <tbody>
            $linhas
        </tbody>
    </table>
    
    <div class="assinatura-coord">
        <div class="linha">
            Coordenação do Grupo
        </div>
    </div>
    
    <div class="rodape">
        <span>Emitido em: <?= date('d/m/Y H:i') ?></span>
        <span>"Unindo forças é bem mais fácil"</span>
    </div>
</body>
</html>
HTML;

$dompdf = novoPdf('portrait', 'A4');
$nomeArq = 'lista_presenca_' . date('Ymd') . '.pdf';
renderizarPdf($dompdf, $html, $nomeArq, true);