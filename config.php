<?php


    $servername = "127.0.0.1";
    $username = "root";  
    $password = "1234";      
    $dbname = "teste2"; 

    $conn = new mysqli($servername, $username, $password, $dbname);

    if ($conn->connect_error) {
        echo "Falha na conexão: (" . $conn->connect_error . ")";
    }else{
        echo "C";//Indica que a conexão com  o banco de dados foi realizada com Exito e será representada como CC
    }

    // Configura o fuso horário padrão para o Brasil (São Paulo)
date_default_timezone_set('America/Sao_Paulo');

// Opcional, mas recomendado: Definir o charset da conexão para UTF-8
$conn->set_charset("utf8mb4");
?>