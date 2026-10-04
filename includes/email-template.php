<?php
/**
 * Gera o HTML do email de recuperação de senha.
 *
 * @param string $nome      Nome do destinatário
 * @param string $link      URL completa para redefinir senha
 * @param int    $expiraMin Minutos até expirar (padrão 60)
 * @return string           HTML pronto
 */
function templateRecuperacaoSenha(string $nome, string $link, int $expiraMin = 60): string {
    $logo  = LOGO_PATH;
    $ano   = date('Y');
    $nome  = htmlspecialchars($nome, ENT_QUOTES, 'UTF-8');
    $link  = htmlspecialchars($link, ENT_QUOTES, 'UTF-8');
    $app   = APP_NOME;
    $sub   = APP_SUB;
    $frase = APP_FRASE;

    return <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperação de Senha</title>
</head>
<body style="margin:0;padding:0;background:#f4f6f9;font-family:'Segoe UI',Arial,sans-serif;color:#1F2937;">

    <!-- Pré-header (aparece ao lado do assunto) -->
    <div style="display:none;max-height:0;overflow:hidden;font-size:1px;color:#f4f6f9;">
        Redefina sua senha do {$app} — link válido por {$expiraMin} minutos.
    </div>

    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f4f6f9;padding:30px 15px;">
        <tr>
            <td align="center">

                <table width="600" cellpadding="0" cellspacing="0" border="0"
                       style="max-width:600px;width:100%;background:#ffffff;border-radius:16px;
                              overflow:hidden;box-shadow:0 10px 30px rgba(0,0,0,0.08);">

                    <!-- CABEÇALHO -->
                    <tr>
                        <td style="background:linear-gradient(135deg,#006B29 0%,#009739 55%,#009FE3 100%);
                                   padding:28px 30px;text-align:center;border-bottom:5px solid #FEDD00;">
                            <img src="{$logo}" alt="{$app}" width="80"
                                 style="display:block;margin:0 auto 10px;">
                            <h1 style="margin:0;color:#ffffff;font-size:20px;letter-spacing:1px;
                                       text-transform:uppercase;font-weight:800;">
                                {$app}
                            </h1>
                            <p style="margin:6px 0 0;color:#FEDD00;font-size:11px;letter-spacing:2px;">
                                {$sub}
                            </p>
                        </td>
                    </tr>

                    <!-- CORPO -->
                    <tr>
                        <td style="padding:35px 35px 25px;">
                            <h2 style="margin:0 0 15px;color:#006B29;font-size:22px;">
                                🔐 Recuperação de Senha
                            </h2>

                            <p style="font-size:15px;line-height:1.6;margin:0 0 15px;">
                                Olá, <strong>{$nome}</strong>!
                            </p>

                            <p style="font-size:15px;line-height:1.6;margin:0 0 25px;">
                                Recebemos um pedido para redefinir a senha da sua conta no
                                <strong>{$app}</strong>. Para criar uma nova senha, clique no botão abaixo:
                            </p>

                            <!-- BOTÃO -->
                            <table cellpadding="0" cellspacing="0" border="0" style="margin:0 auto 25px;">
                                <tr>
                                    <td align="center" bgcolor="#009739"
                                        style="border-radius:10px;box-shadow:0 6px 16px rgba(0,151,57,0.30);">
                                        <a href="{$link}"
                                           style="display:inline-block;padding:16px 40px;
                                                  color:#ffffff;text-decoration:none;
                                                  font-weight:800;font-size:15px;
                                                  letter-spacing:.5px;text-transform:uppercase;
                                                  border-radius:10px;">
                                            🔓 Redefinir Minha Senha
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <!-- AVISO -->
                            <table width="100%" cellpadding="0" cellspacing="0" border="0"
                                   style="background:#FFF8DB;border-left:5px solid #FEDD00;
                                          border-radius:8px;margin-bottom:25px;">
                                <tr>
                                    <td style="padding:14px 18px;font-size:13px;line-height:1.5;color:#7A5C00;">
                                        ⏱️ Este link expira em <strong>{$expiraMin} minutos</strong>.
                                        Se você não solicitou a recuperação, apenas ignore este email —
                                        sua senha continua segura.
                                    </td>
                                </tr>
                            </table>

                            <!-- LINK ALTERNATIVO -->
                            <p style="font-size:12px;color:#6D6E71;margin:0 0 8px;">
                                Se o botão não funcionar, copie e cole este link no navegador:
                            </p>
                            <p style="font-size:12px;color:#009FE3;word-break:break-all;margin:0 0 25px;
                                      background:#F5F7F5;padding:10px 12px;border-radius:6px;">
                                {$link}
                            </p>

                            <hr style="border:none;border-top:1px dashed #E5E7EB;margin:25px 0;">

                            <p style="font-size:12px;color:#A7A9AC;margin:0;line-height:1.5;">
                                Por segurança, este é um email automático. Não responda esta mensagem.<br>
                                Em caso de dúvidas, contate a coordenação do grupo.
                            </p>
                        </td>
                    </tr>

                    <!-- RODAPÉ -->
                    <tr>
                        <td style="background:#006B29;padding:22px 30px;text-align:center;
                                   border-top:5px solid #FEDD00;">
                            <img src="{$logo}" alt="{$app}" width="45"
                                 style="display:block;margin:0 auto 8px;opacity:.85;">
                            <p style="margin:0 0 4px;color:#ffffff;font-size:12px;font-weight:700;">
                                {$app} — {$sub}
                            </p>
                            <p style="margin:0;color:#FEDD00;font-size:11px;font-style:italic;">
                                {$frase}
                            </p>
                            <p style="margin:10px 0 0;color:rgba(255,255,255,.6);font-size:10px;">
                                &copy; {$ano} — Todos os direitos reservados
                            </p>
                        </td>
                    </tr>

                </table>

            </td>
        </tr>
    </table>

</body>
</html>
HTML;
}

/**
 * Template de boas-vindas após cadastro.
 */
function templateBoasVindas(string $nome, string $emailLogin): string {
    $logo  = LOGO_PATH;
    $app   = APP_NOME;
    $sub   = APP_SUB;
    $frase = APP_FRASE;
    $ano   = date('Y');
    $url   = BASE_URL . '/auth/login.php';

    $nome       = htmlspecialchars($nome, ENT_QUOTES, 'UTF-8');
    $emailLogin = htmlspecialchars($emailLogin, ENT_QUOTES, 'UTF-8');

    return <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head><meta charset="UTF-8"><title>Bem-vindo(a)!</title></head>
<body style="margin:0;background:#f4f6f9;font-family:'Segoe UI',Arial,sans-serif;color:#1F2937;">
    <table width="100%" cellpadding="0" cellspacing="0" style="padding:30px 15px;">
        <tr><td align="center">
            <table width="600" cellpadding="0" cellspacing="0"
                   style="max-width:600px;background:#fff;border-radius:16px;overflow:hidden;
                          box-shadow:0 10px 30px rgba(0,0,0,0.08);">
                <tr>
                    <td style="background:linear-gradient(135deg,#006B29,#009739,#009FE3);
                               padding:28px;text-align:center;border-bottom:5px solid #FEDD00;">
                        <img src="{$logo}" width="80" style="display:block;margin:0 auto 10px;">
                        <h1 style="margin:0;color:#fff;font-size:20px;letter-spacing:1px;">
                            🎉 Bem-vindo(a)!
                        </h1>
                    </td>
                </tr>
                <tr>
                    <td style="padding:35px;">
                        <p style="font-size:15px;line-height:1.6;">
                            Olá, <strong>{$nome}</strong>!
                        </p>
                        <p style="font-size:15px;line-height:1.6;">
                            Sua conta no <strong>{$app}</strong> foi criada com sucesso.
                            Use o email abaixo para fazer login:
                        </p>
                        <p style="background:#F5F7F5;padding:12px 16px;border-radius:8px;
                                  font-size:14px;border-left:4px solid #009739;">
                            ✉️ <strong>{$emailLogin}</strong>
                        </p>
                        <table cellpadding="0" cellspacing="0" style="margin:25px auto 10px;">
                            <tr>
                                <td align="center" bgcolor="#009739"
                                    style="border-radius:10px;box-shadow:0 6px 16px rgba(0,151,57,.3);">
                                    <a href="{$url}"
                                       style="display:inline-block;padding:14px 36px;color:#fff;
                                              text-decoration:none;font-weight:800;
                                              text-transform:uppercase;border-radius:10px;">
                                        Acessar o Sistema
                                    </a>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td style="background:#006B29;padding:20px;text-align:center;
                               border-top:5px solid #FEDD00;">
                        <p style="margin:0;color:#FEDD00;font-size:12px;font-style:italic;">
                            {$frase}
                        </p>
                        <p style="margin:8px 0 0;color:rgba(255,255,255,.6);font-size:10px;">
                            &copy; {$ano} {$app} — {$sub}
                        </p>
                    </td>
                </tr>
            </table>
        </td></tr>
    </table>
</body>
</html>
HTML;
}