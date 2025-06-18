<?php
$batFilePath = 'C:\\xampp\\htdocs\\ProjetoFinal\\fechar_kiosk.bat'; // **ATENÇÃO: Substitua pelo caminho real do seu .bat**

if ($_SERVER['REQUEST_URI'] === '/ProjetoFinal/Fechar.php') {
    echo "Tentando executar o script .bat...\n";
    set_time_limit(300); // Define o tempo limite para 300 segundos (5 minutos)
    ob_flush();
    flush();

    // Executa o arquivo .bat usando shell_exec
    $output = exec(escapeshellarg($batFilePath) . ' 2>&1');

    echo "<pre>";
    echo "Resultado da execução do .bat:\n";
    echo htmlspecialchars($output);
    echo "</pre>";
} else {
    // Mensagem padrão se a rota não for /executar_bat
    echo "Servidor local PHP rodando. Acesse http://localhost/executar_bat para tentar executar o .bat";
}
?>