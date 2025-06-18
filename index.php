<?php
session_start();
include('identificador_maquina.php');
include('config.php'); // Conexão com o banco de dados

// --- Adicione estas linhas para depuração, REMOVA EM PRODUÇÃO ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// --- Fim das linhas de depuração ---

$erroAluno = "";
$erroAdm = "";
$turmas_opcoes = []; // Inicializa o array para as opções de turma

// Busca dinamicamente as tabelas de turma
$result_turmas = $conn->query("SHOW TABLES");
if ($result_turmas) {
    while ($row = $result_turmas->fetch_row()) {
        $nome_tabela = $row[0];
        // Considera como turma as tabelas que não são de administração, logs, máquinas ou usuários
        if (!in_array($nome_tabela, ['administrador', 'logs', 'maquinas', 'users', 'registro'])) {
            $turmas_opcoes[] = strtoupper($nome_tabela); // Adiciona a opção em maiúsculo para exibição
        }
    }
    $result_turmas->free_result(); // Libera a memória do resultado
} else {
    // Se houver um erro na consulta, exibe uma mensagem (útil para depuração)
    $erroAluno = "Erro ao buscar as turmas: " . $conn->error;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $login_type = $_POST['login_type'];

    if ($login_type === 'aluno') {
        $password = $_POST['Numero'];
        $turma_selecionada = strtolower($_POST['turma']); // Obtém a turma selecionada e converte para minúsculo

        // Verifica se a turma selecionada existe nas tabelas encontradas
        $tabelas_validas = array_map('strtolower', $turmas_opcoes); // Converte para minúsculo para comparação
        if (!in_array($turma_selecionada, $tabelas_validas)) {
            $erroAluno = "Turma inválida.";
        } else {
            $turma = mysqli_real_escape_string($conn, $turma_selecionada); // Usa a turma em minúsculo para a consulta
            $sql = "SELECT * FROM `$turma` WHERE nome = ?";
            $stmt = $conn->prepare($sql);

            if ($stmt) {
                $stmt->bind_param("s", $username);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows > 0) {
                    $user = $result->fetch_assoc();

                    if ((string)$password === (string)$user['Numero']) {
                        $sessionToken = bin2hex(random_bytes(32));

                        $updateSql = "UPDATE `$turma` SET session_token = ? WHERE id = ?";
                        $updateStmt = $conn->prepare($updateSql);
                        $updateStmt->bind_param("si", $sessionToken, $user['id']);
                        $updateStmt->execute();

                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['session_token'] = $sessionToken;
                        $_SESSION['turma'] = $turma;

                        $uuid = $_COOKIE['uuid'] ?? null;  // Pega o UUID do cookie

                        // --- REGISTRO DO LOGIN ---
                        $nomeAluno = $user['nome'];
                        $dataHora = date("Y-m-d H:i:s");

                        $registroStmt = $conn->prepare("INSERT INTO Registro (Nome, Turma, `data/hr`, uuid) VALUES (?, ?, ?, ?)");
                        $registroStmt->bind_param("ssss", $nomeAluno, $turma, $dataHora, $uuid);
                        $registroStmt->execute();

                        // Redireciona após o login
                        header("Location: http://localhost/ProjetoFinal/Fechar.php");
                        exit();
                    } else {
                        $erroAluno = "Nome ou número de chamada incorreto.";
                    }
                } else {
                    $erroAluno = "Nome ou número de chamada não encontrados.";
                }
            } else {
                $erroAluno = "Erro na preparação da consulta: " . $conn->error;
            }
        }

    } elseif (isset($_POST['username']) && isset($_POST['password'])) {
        $username = $_POST['username'];
        $password = $_POST['password'];

        // Para usuários administradores
        $stmt = $conn->prepare("SELECT id, adm, senha FROM administrador WHERE adm = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            //password_verify()
            if (password_verify($password, $user['senha'])) {
                // Login de administrador bem-sucedido
                $_SESSION['Administrador'] = $user['adm'];
                $_SESSION['user_id'] = $user['id']; // Opcional, para identificar o ADM logado
                header("Location: admin_dashboard.php");
                exit();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Tela de Acesso</title>
    <style>
        /* Estilos CSS (mantidos da versão anterior) */
        body {
            font-family: Arial, Helvetica, sans-serif;
            background-image: linear-gradient(45deg, red, yellow);
            margin: 0;
            padding: 0;
        }

        .container {
            width: 90%;
            max-width: 500px;
            background-color: rgba(0,0,0,0.9);
            margin: 80px auto;
            padding: 40px;
            border-radius: 15px;
            color: white;
        }

        input, button {
            padding: 12px;
            width: 100%;
            margin-top: 10px;
            border: none;
            border-radius: 10px;
            font-size: 15px;
        }

        button {
            background-color: dodgerblue;
            color: white;
            cursor: pointer;
        }

        button:hover {
            background-color: deepskyblue;
        }

        .tabs {
            display: flex;
            margin-bottom: 20px;
            gap: 10px;
        }

        .tab-btn {
            flex: 1;
            padding: 10px;
            border-radius: 10px;
            border: none;
            background-color: gray;
            color: white;
            cursor: pointer;
        }

        .tab-btn.active {
            background-color: darkblue;
        }

        .form-section {
            display: none;
        }

        .form-section.active {
            display: block;
        }

        .erro {
            background-color: crimson;
            padding: 10px;
            margin-top: 10px;
            border-radius: 10px;
            text-align: center;
        }

        .admin-area {
            background: white;
            color: black;
            padding: 30px;
            margin-top: 40px;
            border-radius: 15px;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="tabs">
        <button class="tab-btn active" onclick="showTab('aluno')">Aluno</button>
        <button class="tab-btn" onclick="showTab('adm')">Administrador</button>
    </div>

    <div id="form-aluno" class="form-section active">
        <?php if (!empty($erroAluno)) echo "<div class='erro'>$erroAluno</div>"; ?>
        <form method="POST">
            <input type="hidden" name="login_type" value="aluno">
            <input type="text" name="username" placeholder="Nome do Aluno" autocomplete="off"required>
            <input type="password" name="Numero" placeholder="Número de Chamada" autocomplete="off"required>
            <label for="turma" style="margin-top: 15px; display: block; font-size: 14px;">Selecione sua turma:</label>
            <select name="turma" id="turma" required style="padding: 12px; width: 100%; border: none; border-radius: 10px; font-size: 15px; margin-top: 5px;">
                <option value="" disabled selected>Escolha uma turma</option>
                <?php
                if (!empty($turmas_opcoes)) {
                    foreach ($turmas_opcoes as $turma_nome): ?>
                        <option value="<?= htmlspecialchars(strtolower($turma_nome)) ?>"><?= htmlspecialchars($turma_nome) ?></option>
                    <?php endforeach;
                }
                ?>
            </select>
            <button type="submit">Entrar</button>
        </form>
    </div>

    <div id="form-adm" class="form-section">
        <?php if (!empty($erroAdm)) echo "<div class='erro'>$erroAdm</div>"; ?>
        <form method="POST">
            <input type="hidden" name="login_type" value="adm">
            <input type="text" name="username" placeholder="Usuário ADM" autocomplete="off"required>
            <input type="password" name="password" placeholder="Senha ADM"autocomplete="off"required>
            <button type="submit">Entrar</button>
        </form>
    </div>
</div>

<script>
    function showTab(tab) {
        document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
        document.querySelectorAll('.form-section').forEach(section => section.classList.remove('active'));

        document.querySelector(`#form-${tab}`).classList.add('active');
        event.target.classList.add('active');
    }
    document.addEventListener('contextmenu', event => event.preventDefault());
</script>

</body>
</html>