<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visualização de Log</title>
    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
            background-image: linear-gradient(45deg, red, yellow);
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        .container {
            width: 90%;
            max-width: 900px;
            background-color: rgba(0, 0, 0, 0.9);
            margin: 20px;
            padding: 30px;
            border-radius: 15px;
            color: white;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.5);
        }

        h1 {
            text-align: center;
            margin-bottom: 20px;
            color: #ddd;
        }

        .log-section, .computer-info {
            margin-bottom: 20px;
            padding: 15px;
            border: 1px solid #333;
            border-radius: 8px;
            background-color: #222;
            white-space: pre-wrap;
            font-size: 1.1em;
            line-height: 1.6;
            overflow-x: auto;
        }

        .log-section strong {
            color: #ffcc00;
        }

        .computer-info strong {
            color: #00ffcc;
        }

        /* Classes CSS para as cores */
        .color-green { color: lime; }
        .color-yellow { color: yellow; }
        .color-red { color: red; }
        .color-default { color: white; }

        .return-button {
            background-color: rgb(172, 168, 168);
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            margin-top: 30px;
            display: block;
            width: fit-content;
            margin-left: auto;
            margin-right: auto;
            transition: background-color 0.3s ease;
        }

        .return-button:hover {
            background-color: rgb(140, 136, 136);
        }
    </style>
</head>
<body>
    <div class="container">

        <div class="computer-info">
            <pre class="log-output"><?php
                // --- DEPURACAO: Habilite para ver erros de PHP (ainda recomendado durante o desenvolvimento) ---
                ini_set('display_errors', 1);
                ini_set('display_startup_errors', 1);
                error_reporting(E_ALL);
                // --- FIM DEPURACAO ---

                $bat_path = 'C:\\xampp\\htdocs\\ProjetoFinal\\info_sistema.bat';
                $info_sistema_output = '';
                $message_if_empty = '<span class="color-yellow"</span>'; // Mensagem padrão
                if (file_exists($bat_path)) {
                    // Tenta executar o .bat e captura a saída. Erros de execução vão para $info_sistema_output.
                    $info_sistema_output = shell_exec("\"$bat_path\" 2>&1");

                    // Se a saída for nula (função desabilitada/erro grave) OU estiver vazia (script não gerou saída),
                    // exibe a mensagem genérica.
                    if ($info_sistema_output === null || empty(trim($info_sistema_output))) {
                        echo $message_if_empty;
                    } else {
                        // Se houver saída, processa e exibe.
                        $lines = explode("\n", $info_sistema_output);

                        foreach ($lines as $line) {
                            $line = trim($line);
                            if ($line === '') {
                                echo "<br>";
                                continue;
                            }

                            $color_class = 'color-default';
                            $tooltip = 'Informação do Sistema';

                            // --- REGRAS DE CLASSIFICAÇÃO PARA INFO_SISTEMA.BAT ---
                            if (preg_match('/(erro|falha critica|ameaça detectada|invasão|permissão negada|acesso negado|offline|indisponível|corrompido|disco cheio|processo travado|falha de hardware)/i', $line)) {
                                $color_class = 'color-red';
                                $tooltip = 'Alerta: Informação crítica ou de segurança!';
                            }
                            elseif (preg_match('/(aviso|problema|desconhecido|quase cheio|atenção|uso alto|timeout|conexão perdida|desconectado|limite atingido|serviço parado|reiniciar)/i', $line)) {
                                $color_class = 'color-yellow';
                                $tooltip = 'Atenção: Informação que requer análise.';
                            }
                            elseif (preg_match('/(ativado|online|conectado|sucesso|sem problemas|OK|executando|memoria disponivel|atualizado|protegido|IP Address|Default Gateway|OS Name|Total Physical Memory|Processadores|Modelo do Sistema)/i', $line)) {
                                $color_class = 'color-green';
                                $tooltip = 'Info: Operação ou dado normal e seguro.';
                            }
                            else {
                                $color_class = 'color-default';
                                $tooltip = 'Info do Sistema: Padrão.';
                            }

                            echo '<span class="' . $color_class . '" title="' . htmlspecialchars($tooltip) . '">' . htmlspecialchars($line) . '</span><br>';
                        }
                    }
                } else {
                    // Se o arquivo .bat nem existir, exibe a mensagem genérica.
                    echo $message_if_empty;
                }
            ?></pre>
        </div>

        <div class="log-section">
            <strong>Conteúdo do Log:</strong>
            <pre class="log-output"><?php
                $log_path = "C:\\temp\\log_sessao.txt";
                if (file_exists($log_path)) {
                    $logs_content = file_get_contents($log_path);
                    $lines = explode("\n", $logs_content);

                    foreach ($lines as $line) {
                        $line = trim($line);
                        if ($line === '') {
                            echo "<br>";
                            continue;
                        }

                        $color_class = 'color-default';
                        $tooltip = 'Log Padrão';

                        // --- REGRAS DE CLASSIFICAÇÃO PARA LOG_SESSAO.TXT ---
                        if (preg_match('/(acesso negado critico|falha de segurança|intrusão|malware|vírus|exploit|ransomware|erro fatal|tentativa de força bruta|código malicioso|arquivo infectado|conexão não autorizada|serviço critico parado|brecha de segurança|processo suspeito|desconhecido com privilégios)/i', $line)) {
                            $color_class = 'color-red';
                            $tooltip = 'ALERTA CRÍTICO: Possível ameaça ou falha grave de segurança!';
                        }
                        elseif (preg_match('/(aviso|alerta|tentativa de login falha|processo desconhecido|uso excessivo de cpu|conexão suspeita|configuração alterada|serviço parado|arquivo corrompido|perda de dados|timeout|rejeitado|falha ao carregar|reconexão|processo com alto consumo)/i', $line)) {
                            $color_class = 'color-yellow';
                            $tooltip = 'ATENÇÃO: Requer sua análise. Pode indicar anomalia ou problema.';
                        }
                        elseif (preg_match('/(login bem-sucedido|iniciado|finalizado|operacao concluida|pacote recebido|conexao estabelecida|atualizacao realizada|verificacao ok|informacao|registrado com sucesso|processo iniciado|dados lidos|sessao ativa|backup concluido|System Idle Process|System|Registry|smss.exe|csrss.exe|wininit.exe|services.exe|lsass.exe|winlogon.exe|svchost.exe|fontdrvhost.exe|dwm.exe|atiesrxx.exe|amdfendrsr.exe|Memory Compression|spoolsv.exe|httpd.exe|MsMpEng.exe|MpDefenderCoreService.exe|OfficeClickToRun.exe|mysqld.exe|conhost.exe|dllhost.exe|SearchIndexer.exe|AggregatorHost.exe|sihost.exe|taskhostw.exe|ctfmon.exe|explorer.exe|SearchApp.exe|RuntimeBroker.exe|StartMenuExperienceHost.e|msedgewebview2.exe)/i', $line)) {
                            $color_class = 'color-green';
                            $tooltip = 'INFO: Operação normal e segura.';
                        }
                        else {
                            $color_class = 'color-default';
                            $tooltip = 'Log genérico, sem classificação específica.';
                        }

                        echo '<span class="' . $color_class . '" title="' . htmlspecialchars($tooltip) . '">' . htmlspecialchars($line) . '</span><br>';
                    }
                } else {
                    echo '<span class="color-yellow">Nenhum log de sessão encontrado no caminho especificado: ' . htmlspecialchars($log_path) . '</span>';
                }
            ?></pre>
        </div>
        <form method="POST" action="http://192.168.1.5/ProjetoFinal/admin_dashboard.php" style="text-align: center;">
            <button type="submit" class="return-button">Retornar</button>
        </form>
    </div>
</body>
</html>