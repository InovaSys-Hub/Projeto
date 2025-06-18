<?php
session_start();
include('config.php'); // Conexão com o banco de dados

if (!isset($_SESSION['Administrador'])) {
    header("Location: index.php");
    exit();
}

// --- Variáveis para a Lógica de Busca ---
$resultados_busca = [];
$erroBusca = "";
$turmas_opcoes_busca = []; // Para popular o dropdown de turmas na busca

// Busca dinamicamente as tabelas de turma para o filtro de busca
$result_turmas_busca = $conn->query("SHOW TABLES");
if ($result_turmas_busca) {
    while ($row_busca = $result_turmas_busca->fetch_row()) {
        $nome_tabela_busca = $row_busca[0];
        // Adicione 'registro' para não aparecer como turma na busca de alunos
        if (!in_array($nome_tabela_busca, ['administrador', 'logs', 'maquinas', 'users', 'registro'])) {
            $turmas_opcoes_busca[] = strtoupper($nome_tabela_busca);
        }
    }
    $result_turmas_busca->free_result();
} else {
    $erroBusca = "Erro ao buscar as turmas disponíveis para a busca: " . $conn->error;
}

// Lógica de Busca de Registros de Login (usando GET)
if (isset($_GET['realizar_busca_registro'])) {
    $turma_selecionada_busca = $_GET['turma_busca'] ?? '';
    $data_inicio_busca = $_GET['data_inicio_busca'] ?? '';
    $data_fim_busca = $_GET['data_fim_busca'] ?? '';

    // Validação básica dos inputs
    if (empty($turma_selecionada_busca) && empty($data_inicio_busca) && empty($data_fim_busca)) {
        $erroBusca = "Por favor, selecione uma turma ou um período de datas para realizar a busca de registros.";
    } else {
        // Constrói a query SQL dinamicamente com JOIN
        $sql_busca = "SELECT
                            R.id,
                            R.Nome,
                            R.Turma,
                            R.`data/hr`,
                            M.nome AS NomeMaquina
                        FROM
                            Registro AS R
                        LEFT JOIN
                            maquinas AS M ON R.uuid = M.uuid
                        WHERE 1=1"; // Cláusula WHERE base para adicionar condições

        $params_busca = [];
        $types_busca = "";

        if (!empty($turma_selecionada_busca)) {
            $sql_busca .= " AND R.Turma = ?";
            $params_busca[] = $turma_selecionada_busca;
            $types_busca .= "s";
        }

        if (!empty($data_inicio_busca)) {
            $sql_busca .= " AND DATE(R.`data/hr`) >= ?";
            $params_busca[] = $data_inicio_busca;
            $types_busca .= "s";
        }

        if (!empty($data_fim_busca)) {
            $sql_busca .= " AND DATE(R.`data/hr`) <= ?";
            $params_busca[] = $data_fim_busca;
            $types_busca .= "s";
        }

        // Adicionar ordenação para os resultados da busca
        $sql_busca .= " ORDER BY R.`data/hr` DESC";

        $stmt_busca = $conn->prepare($sql_busca);

        if ($stmt_busca) {
            if (!empty($params_busca)) {
                // bind_param precisa dos parâmetros como referência
                $stmt_busca->bind_param($types_busca, ...$params_busca);
            }
            $stmt_busca->execute();
            $result_exec_busca = $stmt_busca->get_result();

            if ($result_exec_busca->num_rows > 0) {
                while ($row_resultado = $result_exec_busca->fetch_assoc()) {
                    $resultados_busca[] = $row_resultado;
                }
            } else {
                $erroBusca = "Nenhum registro encontrado com os critérios de busca especificados.";
            }
            $stmt_busca->close();
        } else {
            $erroBusca = "Erro na preparação da consulta de busca: " . $conn->error;
        }
    }
}


// Consultar todas as turmas (tabelas) no banco de dados
$turmas = [];
$result_tables = $conn->query("SHOW TABLES");
while ($row_table = $result_tables->fetch_row()) {
    $turmas[] = $row_table[0]; // Adiciona o nome da tabela à lista de turmas
}

$alunos_por_turma = [];

foreach ($turmas as $turma) {
    if (in_array($turma, ['administrador', 'logs', 'maquinas', 'users', 'registro'])) {
        continue;
    }

    $result_alunos = $conn->query("SELECT * FROM `$turma`");
    if ($result_alunos) {
        $alunos_por_turma[$turma] = $result_alunos->fetch_all(MYSQLI_ASSOC);
    } else {
        $alunos_por_turma[$turma] = [];
    }
}

// Criar nova turma (nova tabela)
if (isset($_POST['nova_turma'])) {
    $nova_turma = strtolower(trim($_POST['nome_nova_turma']));

    if (!empty($nova_turma) && preg_match('/^[a-z0-9_]+$/', $nova_turma)) {
        $sql = "CREATE TABLE IF NOT EXISTS `$nova_turma` (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nome VARCHAR(100),
            Numero VARCHAR(10),
            session_token VARCHAR(255)
        )";
        if ($conn->query($sql)) {
            $_SESSION['message'] = "Turma " . strtoupper($nova_turma) . " criada com sucesso!";
            $_SESSION['message_type'] = "success";
            header("Location: admin_dashboard.php?active_tab=tabAlunosTurmas");
            exit();
        } else {
            $_SESSION['message'] = "Erro ao criar turma: " . $conn->error;
            $_SESSION['message_type'] = "error";
            header("Location: admin_dashboard.php?active_tab=tabAlunosTurmas");
            exit();
        }
    } else {
        $_SESSION['message'] = "Nome da turma inválido. Use apenas letras minúsculas, números e sublinhados.";
        $_SESSION['message_type'] = "error";
        header("Location: admin_dashboard.php?active_tab=tabAlunosTurmas");
        exit();
    }
}

// Lógica para remover turma
if (isset($_POST['remover_turma']) && isset($_POST['turma_para_remover'])) {
    $turma_para_remover = mysqli_real_escape_string($conn, $_POST['turma_para_remover']);
    $sql_remover_turma = "DROP TABLE `$turma_para_remover`";

    if ($conn->query($sql_remover_turma) === TRUE) {
        $_SESSION['message'] = "Turma " . strtoupper($turma_para_remover) . " removida com sucesso!";
        $_SESSION['message_type'] = "success";
        header("Location: admin_dashboard.php?active_tab=tabAlunosTurmas");
        exit();
    } else {
        $_SESSION['message'] = "Erro ao remover a turma " . strtoupper($turma_para_remover) . ": " . $conn->error;
        $_SESSION['message_type'] = "error";
        header("Location: admin_dashboard.php?active_tab=tabAlunosTurmas");
        exit();
    }
}

// Cadastrar novo aluno
if (isset($_POST['cadastrar_aluno'])) {
    $turma = $_POST['turma'];
    $nome = $_POST['nome'];
    $numero = $_POST['numero'];

    if (!empty($turma) && !empty($nome) && !empty($numero)) {
        $stmt = $conn->prepare("INSERT INTO `$turma` (nome, Numero) VALUES (?, ?)");
        $stmt->bind_param("ss", $nome, $numero);
        if ($stmt->execute()) {
            $_SESSION['message'] = "Aluno cadastrado com sucesso!";
            $_SESSION['message_type'] = "success";
            header("Location: admin_dashboard.php?active_tab=tabAlunosTurmas");
            exit();
        } else {
            $_SESSION['message'] = "Erro ao cadastrar aluno: " . $stmt->error;
            $_SESSION['message_type'] = "error";
            header("Location: admin_dashboard.php?active_tab=tabAlunosTurmas");
            exit();
        }
        $stmt->close();
    } else {
        $_SESSION['message'] = "Todos os campos são obrigatórios.";
        $_SESSION['message_type'] = "error";
        header("Location: admin_dashboard.php?active_tab=tabAlunosTurmas");
        exit();
    }
}

// Lógica para remover aluno
if (isset($_POST['remover_aluno'])) {
    $turma = $_POST['turma'];
    $id_aluno = $_POST['id_aluno'];

    $stmt = $conn->prepare("DELETE FROM `$turma` WHERE id = ?");
    $stmt->bind_param("i", $id_aluno);
    if ($stmt->execute()) {
        $_SESSION['message'] = "Aluno removido com sucesso!";
        $_SESSION['message_type'] = "success";
        header("Location: admin_dashboard.php?active_tab=tabAlunosTurmas");
        exit();
    } else {
        $_SESSION['message'] = "Erro ao remover aluno: " . $stmt->error;
        $_SESSION['message_type'] = "error";
        header("Location: admin_dashboard.php?active_tab=tabAlunosTurmas");
        exit();
    }
    $stmt->close();
}

// Lógica para renomear aluno
if (isset($_POST['renomear_aluno'])) {
    $turma = $_POST['turma'];
    $id_aluno = $_POST['id_aluno'];
    $novo_nome = $_POST['novo_nome'];

    if (!empty($novo_nome)) {
        $stmt = $conn->prepare("UPDATE `$turma` SET nome = ? WHERE id = ?");
        $stmt->bind_param("si", $novo_nome, $id_aluno);
        if ($stmt->execute()) {
            $_SESSION['message'] = "Aluno renomeado com sucesso!";
            $_SESSION['message_type'] = "success";
            header("Location: admin_dashboard.php?active_tab=tabAlunosTurmas");
            exit();
        } else {
            $_SESSION['message'] = "Erro ao renomear aluno: " . $stmt->error;
            $_SESSION['message_type'] = "error";
            header("Location: admin_dashboard.php?active_tab=tabAlunosTurmas");
            exit();
        }
        $stmt->close();
    } else {
        $_SESSION['message'] = "O novo nome não pode ser vazio.";
        $_SESSION['message_type'] = "error";
        header("Location: admin_dashboard.php?active_tab=tabAlunosTurmas");
        exit();
    }
}

// --- Lógica para Gerenciar Usuários ---
if (isset($_POST['cadastrar_novo_usuario'])) {
    $novo_usuario = $_POST['novo_usuario'];
    $nova_senha = $_POST['nova_senha'];
    $confirmar_senha = $_POST['confirmar_senha'];
    $senha_atual_admin = $_POST['senha_atual_admin'];
    $usuario_atual_admin = $_POST['usuario_atual_admin'];

    // 1. Validar se os campos do novo usuário estão preenchidos
    if (empty($novo_usuario) || empty($nova_senha) || empty($confirmar_senha)) {
        $_SESSION['message'] = 'Preencha todos os campos do novo usuário.';
        $_SESSION['message_type'] = 'error';
    }
    // 2. Validar se a nova senha e a confirmação são iguais
    else if ($nova_senha !== $confirmar_senha) {
        $_SESSION['message'] = 'A nova senha e a confirmação não coincidem.';
        $_SESSION['message_type'] = 'error';
    }
    // 3. Validar credenciais do administrador atual para autorização
    else {
        $stmt_check_admin = $conn->prepare("SELECT senha FROM administrador WHERE adm = ?");
        $stmt_check_admin->bind_param("s", $usuario_atual_admin);
        $stmt_check_admin->execute();
        $result_check_admin = $stmt_check_admin->get_result();

        if ($result_check_admin->num_rows > 0) {
            $admin_row = $result_check_admin->fetch_assoc();
            $hashed_password_admin = $admin_row['senha'];

            if (password_verify($senha_atual_admin, $hashed_password_admin)) {
                $hashed_nova_senha = password_hash($nova_senha, PASSWORD_DEFAULT);
                $stmt_insert_user = $conn->prepare("INSERT INTO administrador (adm, senha) VALUES (?, ?)");
                $stmt_insert_user->bind_param("ss", $novo_usuario, $hashed_nova_senha);

                if ($stmt_insert_user->execute()) {
                    $_SESSION['message'] = "Novo usuário cadastrado com sucesso!";
                    $_SESSION['message_type'] = "success";
                } else {
                    if ($conn->errno == 1062) {
                        $_SESSION['message'] = "Erro: O nome de usuário \"" . htmlspecialchars($novo_usuario) . "\" já existe. Escolha outro nome.";
                    } else {
                        $_SESSION['message'] = "Erro ao cadastrar novo usuário: " . $stmt_insert_user->error;
                    }
                    $_SESSION['message_type'] = "error";
                }
                $stmt_insert_user->close();
            } else {
                $_SESSION['message'] = 'Credenciais do administrador atual incorretas. Verifique seu usuário e senha.';
                $_SESSION['message_type'] = 'error';
            }
        } else {
            $_SESSION['message'] = 'Usuário administrador atual não encontrado. Verifique o usuário digitado.';
            $_SESSION['message_type'] = 'error';
        }
        $stmt_check_admin->close();
    }
    header("Location: admin_dashboard.php?active_tab=tabUsuarios"); // Redireciona no final do bloco de cadastro de usuário
    exit();
}

// Lógica para remover usuário ADM
if (isset($_POST['remover_usuario_adm'])) {
    $usuario_para_remover = $_POST['usuario_para_remover_input']; // Alterado para input de texto
    $senha_atual_admin = $_POST['senha_atual_admin_remocao'];
    $usuario_atual_admin = $_POST['usuario_atual_admin_remocao'];

    // 1. Validar se os campos estão preenchidos
    if (empty($usuario_para_remover) || empty($senha_atual_admin) || empty($usuario_atual_admin)) {
        $_SESSION['message'] = 'Preencha todos os campos para remover o usuário.';
        $_SESSION['message_type'] = 'error';
    }
    // 2. Validar credenciais do administrador atual para autorização
    else {
        $stmt_check_admin = $conn->prepare("SELECT senha FROM administrador WHERE adm = ?");
        $stmt_check_admin->bind_param("s", $usuario_atual_admin);
        $stmt_check_admin->execute();
        $result_check_admin = $stmt_check_admin->get_result();

        if ($result_check_admin->num_rows > 0) {
            $admin_row = $result_check_admin->fetch_assoc();
            $hashed_password_admin = $admin_row['senha'];

            if (password_verify($senha_atual_admin, $hashed_password_admin)) {
                // Previne que o próprio admin logado seja removido
                if ($usuario_para_remover === $_SESSION['Administrador']) {
                    $_SESSION['message'] = 'Você não pode remover sua própria conta enquanto estiver logado.';
                    $_SESSION['message_type'] = 'error';
                } else {
                    $stmt_delete_user = $conn->prepare("DELETE FROM administrador WHERE adm = ?");
                    $stmt_delete_user->bind_param("s", $usuario_para_remover);

                    if ($stmt_delete_user->execute()) {
                        if ($stmt_delete_user->affected_rows > 0) {
                            $_SESSION['message'] = "Usuário '" . htmlspecialchars($usuario_para_remover) . "' removido com sucesso!";
                            $_SESSION['message_type'] = "success";
                        } else {
                            $_SESSION['message'] = "Usuário '" . htmlspecialchars($usuario_para_remover) . "' não encontrado.";
                            $_SESSION['message_type'] = "error";
                        }
                    } else {
                        $_SESSION['message'] = "Erro ao remover usuário: " . $stmt_delete_user->error;
                        $_SESSION['message_type'] = "error";
                    }
                    $stmt_delete_user->close();
                }
            } else {
                $_SESSION['message'] = 'Credenciais do administrador atual incorretas. Verifique seu usuário e senha.';
                $_SESSION['message_type'] = 'error';
            }
        } else {
            $_SESSION['message'] = 'Usuário administrador atual não encontrado. Verifique o usuário digitado.';
            $_SESSION['message_type'] = 'error';
        }
        $stmt_check_admin->close();
    }
    header("Location: admin_dashboard.php?active_tab=tabUsuarios");
    exit();
}

// --- Fim da Lógica para Gerenciar Usuários ---


// --- Lógica para gerenciamento de máquinas ---

// Renomear máquina
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['renomear'])) {
    $id = $_POST['id'];
    $novo_nome = $_POST['novo_nome'];

    $stmt = $conn->prepare("UPDATE maquinas SET nome = ? WHERE id = ?");
    $stmt->bind_param("si", $novo_nome, $id);
    if ($stmt->execute()) {
        $_SESSION['message'] = "Máquina renomeada com sucesso!";
        $_SESSION['message_type'] = "success";
        header("Location: admin_dashboard.php?active_tab=tabMaquinas");
        exit();
    } else {
        $_SESSION['message'] = "Erro ao renomear máquina: " . $stmt->error;
        $_SESSION['message_type'] = "error";
        header("Location: admin_dashboard.php?active_tab=tabMaquinas");
        exit();
    }
    $stmt->close();
}
//remover maquina
if (isset($_POST['remover_maquina'])) {
    $id_maquina = $_POST['id_maquina'];

    $stmt = $conn->prepare("DELETE FROM maquinas WHERE id = ?");
    $stmt->bind_param("i", $id_maquina);
    if ($stmt->execute()) {
         $_SESSION['message'] = "Máquina removida com sucesso!";
         $_SESSION['message_type'] = "success";
         header("Location: admin_dashboard.php?active_tab=tabMaquinas");
         exit();
    } else {
        $_SESSION['message'] = "Erro ao remover máquina: " . $stmt->error;
        $_SESSION['message_type'] = "error";
        header("Location: admin_dashboard.php?active_tab=tabMaquinas");
        exit();
    }
    $stmt->close();
}

// Obter máquinas
$maquinas = [];
$result_maquinas = $conn->query("SELECT * FROM maquinas");
if ($result_maquinas) {
    while ($row = $result_maquinas->fetch_assoc()) {
        $maquinas[] = $row;
    }
} else {
    error_log("Erro ao buscar máquinas: " . $conn->error);
}

// A lista de usuários ADM não é mais necessária para o dropdown de remoção,
// mas pode ser útil para outras partes, então a mantemos por enquanto,
// mas ela não será usada no formulário de remoção.
$usuarios_adm = [];
$result_adm_users = $conn->query("SELECT adm FROM administrador");
if ($result_adm_users) {
    while ($row_adm = $result_adm_users->fetch_assoc()) {
        $usuarios_adm[] = $row_adm['adm'];
    }
    $result_adm_users->free_result();
} else {
    // Tratar erro, se necessário
}

?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Painel do Administrador</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-image: linear-gradient(45deg, red, yellow);
            margin: 0;
            padding: 0;
        }

        .refresh-button {
            background-color: #6c757d; /* Cor cinza para o botão de atualizar */
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            margin-top: 15px; /* Espaçamento acima do botão */
            margin-bottom: 20px; /* Espaçamento abaixo do botão */
            display: block; /* Para que ocupe a largura total e fique abaixo do título */
            width: fit-content; /* Ajusta a largura ao conteúdo */
            margin-left: auto; /* Centraliza o botão */
            margin-right: auto; /* Centraliza o botão */
            transition: background-color 0.3s ease;
        }

        .refresh-button:hover {
            background-color: #5a6268;
        }

        .container {
            width: 90%;
            max-width: 1200px; /* Aumentado para acomodar as abas */
            margin: 40px auto;
            background-color: rgba(0, 0, 0, 0.9);
            color: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.5); /* Adiciona uma sombra para destaque */
        }

        h1 {
            color: white; /* Título principal em branco para contraste */
            text-align: center;
            margin-bottom: 30px;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.7);
        }

        h2, h3 {
            color: gold; /* Títulos de seções em dourado */
            border-bottom: 1px solid rgba(255, 215, 0, 0.3); /* Linha sutil abaixo dos títulos */
            padding-bottom: 10px;
            margin-top: 25px;
            margin-bottom: 20px;
        }

        /* Estilo para a nova seção de gerenciamento de usuários */
        .user-management h3 {
            color: rgb(255, 251, 0); /* Subtítulo de gerenciamento de usuários em amarelo */
        }

        /* Estilo para esconder/mostrar o formulário */
        .user-form-hidden {
            display: none; /* Esconde o formulário por padrão */
            opacity: 0;
            transition: opacity 0.5s ease-in-out;
        }

        .user-form-visible {
            display: block; /* Mostra o formulário */
            opacity: 1;
        }

        .user-management input[type="text"],
        .user-management input[type="password"] {
            width: calc(100% - 10px);
            margin-bottom: 10px;
            padding: 8px;
            border-radius: 6px;
            border: 1px solid #555;
            background-color: #333;
            color: white;
        }

        .user-management button {
            background-color: #007bff;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .user-management button:hover {
            background-color: #0056b3; /* Tom mais escuro de azul ao passar o mouse */
        }

        /* Estilo para o botão que mostra o formulário */
        .toggle-user-form-button {
            background-color: #007bff; /* Azul para o botão de mostrar */
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            margin-bottom: 20px;
            transition: background-color 0.3s ease;
        }

        .toggle-user-form-button:hover {
            background-color: #0056b3;
        }

        .maquina {
            background-color: #222;
            padding: 15px;
            margin: 15px 0;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
        }

        .log {
            background-color: #333;
            padding: 10px;
            margin: 8px 0;
            border-left: 4px solid #888;
            border-radius: 8px;
            font-size: 14px;
        }

        /* Ajusta inputs e selects para que ocupem a largura total do seu contêiner */
        input[type="text"],
        input[type="password"],
        input[type="date"], /* Adicionado para os inputs de data */
        select {
            width: calc(100% - 20px); /* Ajusta para preencher o formulário, considerando o padding */
            padding: 10px;
            border-radius: 6px;
            border: 1px solid #555;
            margin-top: 5px;
            margin-bottom: 10px; /* Adiciona espaçamento entre os campos */
            background-color: #333;
            color: white;
            box-sizing: border-box; /* Garante que padding e border não aumentem a largura total */
        }

        button {
            background-color: dodgerblue;
            color: white;
            padding: 10px 15px; /* Ajusta o padding para ser mais uniforme */
            border: none;
            border-radius: 6px;
            cursor: pointer;
            margin-left: 5px; /* Mantém um pequeno espaçamento à esquerda */
            transition: background-color 0.3s ease;
        }

        button:hover {
            background-color: deepskyblue;
        }

        .acoes {
            margin-top: 10px;
        }

        /* --- Novas Classes para Abas Principais --- */
        .main-tabs {
            display: flex;
            justify-content: space-around; /* Distribui os botões uniformemente */
            gap: 10px; /* Espaçamento entre os botões */
            margin-bottom: 20px;
            flex-wrap: wrap; /* Permite que os botões quebrem a linha em telas pequenas */
            border-bottom: 2px solid rgba(255, 255, 255, 0.2); /* Linha sutil abaixo das abas */
            padding-bottom: 10px; /* Espaçamento abaixo dos botões antes da linha */
        }

        .main-tabs button {
            flex: 1; /* Faz com que os botões ocupem o espaço disponível igualmente */
            min-width: 150px; /* Garante um tamanho mínimo para os botões */
            padding: 12px 15px; /* Ajusta o padding */
            border-radius: 8px 8px 0 0; /* Bordas arredondadas apenas no topo */
            border: none;
            background-color: #444; /* Cor para as abas principais não ativas */
            color: white;
            cursor: pointer;
            font-size: 1rem;
            transition: background-color 0.3s ease, transform 0.2s ease;
            text-align: center;
        }

        .main-tabs button:hover {
            background-color: #666; /* Cor mais clara ao passar o mouse */
            transform: translateY(-2px); /* Pequeno efeito de elevação */
        }

        .main-tabs button.active {
            background-color: #004085; /* Azul escuro para a aba principal ativa */
            color: gold; /* Texto dourado para a aba ativa */
            font-weight: bold;
            border-bottom: none; /* Remove a borda inferior para a aba ativa */
            transform: translateY(0); /* Garante que não haja elevação */
        }

        .main-section {
            display: none; /* Esconde as seções principais por padrão */
            padding: 20px 0; /* Espaçamento interno para as seções */
        }

        .main-section.active {
            display: block; /* Mostra a seção principal ativa */
        }
        /* --- Fim das Novas Classes para Abas Principais --- */


        .mini-abas { /* Para as abas de turmas dentro de "Gerenciar Alunos" */
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
            justify-content: flex-start; /* Alinha os botões à esquerda */
        }

        .mini-abas button {
            background: #666; /* Cor ligeiramente diferente para as mini-abas */
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 6px;
            cursor: pointer;
            flex-grow: 0; /* Não crescem para preencher o espaço */
            min-width: unset; /* Remove o min-width para as mini-abas */
            transition: background-color 0.3s ease;
        }

        .mini-abas button.active {
            background: darkblue;
            font-weight: bold;
        }

        .turma {
            display: none;
        }

        .turma.active {
            display: block;
        }

        table {
            width: 100%;
            margin-top: 10px;
            background: #333;
            border-collapse: collapse;
            border-radius: 8px; /* Arredonda as bordas da tabela */
            overflow: hidden; /* Garante que as bordas arredondadas sejam visíveis */
        }

        th, td {
            padding: 12px; /* Aumenta o padding para melhor leitura */
            border: 1px solid #444;
            text-align: left;
            vertical-align: top; /* Alinha o conteúdo ao topo */
        }

        th {
            background-color: #1a1a1a; /* Fundo mais escuro para cabeçalhos */
            color: gold;
            font-weight: bold;
        }

        td {
            background-color: #2a2a2a; /* Fundo ligeiramente mais claro para células de dados */
        }

        p {
            padding: 5px 0;
            margin-bottom: 10px;
        }

        .formulario {
            margin-top: 30px;
            background: #444;
            padding: 20px; /* Aumenta o padding do formulário */
            border-radius: 10px; /* Arredonda mais as bordas */
            margin-bottom: 20px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
        }

        .erro {
            background-color: #dc3545; /* Vermelho vibrante para erros */
            color: white;
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 15px;
            text-align: center;
            font-weight: bold;
        }
        .sucesso { /* Novo estilo para mensagens de sucesso */
            background-color: #28a745; /* Verde para sucesso */
            color: white;
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 15px;
            text-align: center;
            font-weight: bold;
        }


        hr {
            border: none;
            border-top: 1px dashed #555;
            margin: 25px 0;
        }

        /* Estilos para a seção de busca de registros (agora dentro de .formulario) */
        .search-records-form label {
            display: block;
            margin-bottom: 5px;
            color: #ddd;
        }

        /* Ajusta o width dos inputs e selects dentro do formulário de busca */
        .search-records-form input[type="text"],
        .search-records-form input[type="date"],
        .search-records-form select {
            width: calc(100% - 20px); /* Ocupa 100% da largura do .formulario menos o padding */
            margin-bottom: 10px;
        }

        .search-records-form button {
            background-color: #28a745; /* Cor verde para o botão de busca */
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s ease;
            margin-left: 0; /* Remove margem esquerda padrão dos botões */
        }

        .search-records-form button:hover {
            background-color: #218838;
        }

        .search-results-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .search-results-table th, .search-results-table td {
            border: 1px solid #444;
            padding: 10px;
            text-align: left;
            word-wrap: break-word; /* Para quebrar palavras longas, como UUID */
        }

        .search-results-table th {
            background-color: #1a1a1a;
            color: gold;
        }

        .search-results-table tr:nth-child(even) {
            background-color: #2a2a2a;
        }

        .search-results-table tr:hover {
            background-color: #3a3a3a;
        }

        .no-records-found {
            text-align: center;
            color: #ddd;
            padding: 20px;
        }

        /* Ajuste para o botão de "Sair do Painel" */
        .logout-form {
            text-align: center;
            margin-top: 30px;
        }
        .logout-form button {
            background-color: #dc3545;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        .logout-form button:hover {
            background-color: #c82333;
        }

        /* Estilo para os botões de alternar formulários (Cadastrar e Remover Usuário) */
        .user-management-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        .user-management-buttons button {
            background-color: #007bff;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        .user-management-buttons button:hover {
            background-color: #0056b3;
        }
        .user-management-buttons button.active {
            background-color: #004085;
            font-weight: bold;
        }

    </style>
</head>
<body>
<div class="container">
    <h1>Painel do Administrador</h1>

    <?php
    // Exibe mensagens de sucesso/erro da sessão
    if (isset($_SESSION['message'])) {
        $message_class = ($_SESSION['message_type'] === "success") ? "sucesso" : "erro";
        echo '<p class="' . $message_class . '">' . htmlspecialchars($_SESSION['message']) . '</p>';
        unset($_SESSION['message']); // Limpa a mensagem após exibir
        unset($_SESSION['message_type']); // Limpa o tipo de mensagem
    }
    ?>

    <div class="main-tabs">
        <button class="tab-button active" onclick="openMainTab(event, 'tabAlunosTurmas')">Gerenciar Turmas e Alunos</button>
        <button class="tab-button" onclick="openMainTab(event, 'tabUsuarios')">Gerenciar Usuários</button>
        <button class="tab-button" onclick="openMainTab(event, 'tabMaquinas')">Gerenciar Máquinas</button>
        <button class="tab-button" onclick="openMainTab(event, 'tabBuscaRegistros')">Busca de Registros</button>
    </div>

    <div id="tabAlunosTurmas" class="main-section active">
        <h2>Gerenciar Turmas</h2>
        <form method="POST" class="formulario">
            <h3>Adicionar Nova Turma</h3>
            <input type="text" name="nome_nova_turma" placeholder="Nome da nova turma (ex: tdse)" required autocomplete="off">
            <button type="submit" name="nova_turma">Criar Turma</button>
        </form>

        <div class="formulario">
            <h3>Remover Turma</h3>
            <p>Selecione a turma que deseja remover:</p>
            <form method="POST" action="">
                <select name="turma_para_remover" required autocomplete="off">
                    <option value="" disabled selected>Selecione uma turma</option>
                    <?php
                    $result_turmas_remover = $conn->query("SHOW TABLES");
                    if ($result_turmas_remover) {
                        while ($row_remover = $result_turmas_remover->fetch_row()) {
                            $nome_tabela_remover = $row_remover[0];
                            if (!in_array($nome_tabela_remover, ['administrador', 'logs', 'maquinas', 'users', 'registro'])) {
                                echo '<option value="' . htmlspecialchars($nome_tabela_remover) . '">' . htmlspecialchars(strtoupper($nome_tabela_remover)) . '</option>';
                            }
                        }
                        $result_turmas_remover->free_result();
                    } else {
                        echo "<p>Erro ao listar as turmas para remoção: " . $conn->error . "</p>";
                    }
                    ?>
                </select>
                <button type="submit" name="remover_turma" onclick="return confirm('Tem certeza que deseja remover esta turma? Todos os dados serão perdidos!')">Remover Turma</button>
            </form>
        </div>

        <hr style="border-top: 1px dashed #555; margin: 20px 0;">

        <h2>Gerenciar Alunos</h2>
        <form method="POST" class="formulario">
            <h3>Cadastrar Aluno</h3>
            <input type="text" name="nome" placeholder="Nome do aluno" required autocomplete="off">
            <input type="text" name="numero" placeholder="Número de chamada" required autocomplete="off">
            <select name="turma" required autocomplete="off">
                <option value="" disabled selected>Selecione a turma</option>
                <?php foreach ($turmas as $turma): ?>
                    <?php if (in_array($turma, ['administrador', 'logs', 'maquinas', 'users', 'registro'])) continue; ?>
                    <option value="<?= $turma ?>"><?= strtoupper($turma) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" name="cadastrar_aluno">Cadastrar</button>
        </form>

        <div class="mini-abas">
            <?php
            $turmas_validas_para_abas = [];
            foreach ($turmas as $t) {
                if (!in_array($t, ['administrador', 'logs', 'maquinas', 'users', 'registro'])) {
                    $turmas_validas_para_abas[] = $t;
                }
            }
            ?>
            <?php foreach ($turmas_validas_para_abas as $index => $turma): ?>
                <button onclick="showTurma('<?= $turma ?>')" class="<?= $index === 0 ? 'active' : '' ?>"><?= strtoupper($turma) ?></button>
            <?php endforeach; ?>
        </div>

        <?php foreach ($alunos_por_turma as $turma => $alunos): ?>
            <div id="<?= $turma ?>" class="turma <?= $turma === ($turmas_validas_para_abas[0] ?? null) ? 'active' : '' ?>">
                <h3>Alunos da Turma <?= strtoupper($turma) ?></h3>
                <table>
                    <tr><th>ID</th><th>Nome</th><th>Número</th><th>Ações</th></tr>
                    <?php if (empty($alunos)): ?>
                        <tr><td colspan="4">Nenhum aluno cadastrado nesta turma.</td></tr>
                    <?php else: ?>
                        <?php foreach ($alunos as $aluno): ?>
                            <tr>
                                <td><?= $aluno['id'] ?></td>
                                <td><?= htmlspecialchars($aluno['nome']) ?></td>
                                <td><?= htmlspecialchars($aluno['Numero']) ?></td>
                                <td>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="turma" value="<?= $turma ?>">
                                        <input type="hidden" name="id_aluno" value="<?= $aluno['id'] ?>">
                                        <input type="text" name="novo_nome" placeholder="Novo nome" required style="width: auto; display: inline-block; margin-right: 5px;" autocomplete="off">
                                        <button type="submit" name="renomear_aluno" style="display: inline-block;">Renomear</button>
                                    </form>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="turma" value="<?= $turma ?>">
                                        <input type="hidden" name="id_aluno" value="<?= $aluno['id'] ?>">
                                        <button type="submit" name="remover_aluno" onclick="return confirm('Tem certeza que deseja remover este aluno?')" style="background-color: #dc3545; display: inline-block;">Remover</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </table>
            </div>
        <?php endforeach; ?>
    </div>

    <div id="tabUsuarios" class="main-section">
        <h2>Gerenciar Usuários de Acesso</h2>
        <div class="user-management-buttons">
            <button class="toggle-user-form-button active" onclick="showUserForm('userFormCadastro')">Cadastrar Novo Usuário</button>
            <button class="toggle-user-form-button" onclick="showUserForm('userFormRemocao')">Remover Usuário ADM</button>
        </div>


        <div id="userFormCadastro" class="formulario user-form-visible">
            <h3>Cadastrar Novo Usuário</h3>
            <p>Preencha os dados do novo usuário e suas credenciais atuais para autorizar.</p>
            <form method="POST">
                <label for="novo_usuario">Novo Nome de Usuário:</label>
                <input type="text" id="novo_usuario" name="novo_usuario" placeholder="Nome de usuário" required autocomplete="off"><br>

                <label for="nova_senha">Nova Senha:</label>
                <input type="password" id="nova_senha" name="nova_senha" placeholder="Nova senha" required autocomplete="new-password"><br>

                <label for="confirmar_senha">Confirmar Nova Senha:</label>
                <input type="password" id="confirmar_senha" name="confirmar_senha" placeholder="Confirme a nova senha" required autocomplete="new-password"><br>

                <hr style="border-top: 1px dashed #555; margin: 15px 0;">

                <p>Para concluir, insira suas credenciais atuais de administrador:</p>
                <label for="usuario_atual_admin">Seu Usuário Atual:</label>
                <input type="text" id="usuario_atual_admin" name="usuario_atual_admin" placeholder="Seu usuário de administrador" required autocomplete="off"><br>

                <label for="senha_atual_admin">Sua Senha Atual:</label>
                <input type="password" id="senha_atual_admin" name="senha_atual_admin" placeholder="Sua senha de administrador" required autocomplete="current-password"><br>

                <button type="submit" name="cadastrar_novo_usuario">Cadastrar/Atualizar Usuário</button>
            </form>
        </div>

        <div id="userFormRemocao" class="formulario user-form-hidden">
            <h3>Remover Usuário ADM</h3>
            <p>Digite o nome do usuário ADM que deseja remover e insira suas credenciais atuais para autorizar.</p>
            <form method="POST">
                <label for="usuario_para_remover_input">Usuário para Remover:</label>
                <input type="text" id="usuario_para_remover_input" name="usuario_para_remover_input" placeholder="Nome de usuário a ser removido" required autocomplete="off"><br>

                <hr style="border-top: 1px dashed #555; margin: 15px 0;">

                <p>Para concluir a remoção, insira suas credenciais atuais de administrador:</p>
                <label for="usuario_atual_admin_remocao">Seu Usuário Atual:</label>
                <input type="text" id="usuario_atual_admin_remocao" name="usuario_atual_admin_remocao" placeholder="Seu usuário de administrador" required autocomplete="off"><br>

                <label for="senha_atual_admin_remocao">Sua Senha Atual:</label>
                <input type="password" id="senha_atual_admin_remocao" name="senha_atual_admin_remocao" placeholder="Sua senha de administrador" required autocomplete="current-password"><br>

                <button type="submit" name="remover_usuario_adm" onclick="return confirm('Tem certeza que deseja remover este usuário ADM? Esta ação é irreversível!')" style="background-color: #dc3545;">Remover Usuário ADM</button>
            </form>
        </div>

    </div>

    <div id="tabMaquinas" class="main-section">
        <h2>Máquinas Conectadas</h2>
        <?php if (empty($maquinas)): ?>
            <p>Nenhuma máquina conectada registrada.</p>
        <?php else: ?>
            <?php foreach ($maquinas as $maquina): ?>
                <div class="maquina">
                    <strong>ID:</strong> <?= $maquina['id'] ?><br>
                    <strong>Nome:</strong> <?= htmlspecialchars($maquina['nome'] ?? 'Sem nome') ?><br>
                    <strong>UUID:</strong> <?= $maquina['uuid'] ?><br>

                    <form method="POST" style="margin-top: 10px;">
                        <input type="hidden" name="id" value="<?= $maquina['id'] ?>">
                        <input type="text" name="novo_nome" placeholder="Novo nome" required style="width: auto; display: inline-block; margin-right: 5px;" autocomplete="off">
                        <button type="submit" name="renomear" style="display: inline-block;">Renomear</button>
                    </form>

                    <form method="POST" style="margin-top: 5px;">
                        <input type="hidden" name="id_maquina" value="<?= $maquina['id'] ?>">
                        <button type="submit" name="remover_maquina" onclick="return confirm('Tem certeza que deseja remover esta máquina?')" style="background-color: #dc3545;">Remover Máquina</button>
                    </form>

                    <div class="log">
                        <a href="Logs.php?maquina_uuid=<?= urlencode($maquina['uuid']) ?>" style="display: block; text-decoration: none; color: inherit;">
                            <button style="width: 100%; padding: 10px; border: none; background-color:dodgerblue; cursor: pointer; text-align: center;">
                                Visualizar Logs
                            </button>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div id="tabBuscaRegistros" class="main-section">
        <h2>Busca de Registros de Acesso</h2>
        <div class="formulario">
            <h3>Filtrar Registros</h3>
            <?php if (!empty($erroBusca)): ?>
                <p class="erro"><?= $erroBusca ?></p>
            <?php endif; ?>
            <form method="GET" action="">
                <input type="hidden" name="realizar_busca_registro" value="1">
                <input type="hidden" name="active_tab" value="tabBuscaRegistros">

                <label for="turma_busca">Turma:</label>
                <select id="turma_busca" name="turma_busca" autocomplete="off">
                    <option value="">Todas as Turmas</option>
                    <?php foreach ($turmas_opcoes_busca as $turma_opt): ?>
                        <option value="<?= htmlspecialchars(strtolower($turma_opt)) ?>"
                            <?= (isset($_GET['turma_busca']) && $_GET['turma_busca'] === strtolower($turma_opt)) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($turma_opt) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label for="data_inicio_busca">Data Início:</label>
                <input type="date" id="data_inicio_busca" name="data_inicio_busca" value="<?= htmlspecialchars($_GET['data_inicio_busca'] ?? '') ?>" autocomplete="off">

                <label for="data_fim_busca">Data Fim:</label>
                <input type="date" id="data_fim_busca" name="data_fim_busca" value="<?= htmlspecialchars($_GET['data_fim_busca'] ?? '') ?>" autocomplete="off">

                <button type="submit" class="search-records-form-button">Buscar Registros</button>
            </form>
        </div>

        <?php if (!empty($resultados_busca)): ?>
            <table class="search-results-table">
                <thead>
                    <tr>
                        <th>ID Registro</th>
                        <th>Nome Aluno</th>
                        <th>Turma</th>
                        <th>Data/Hora</th>
                        <th>Máquina (Nome)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($resultados_busca as $registro): ?>
                        <tr>
                            <td><?= htmlspecialchars($registro['id']) ?></td>
                            <td><?= htmlspecialchars($registro['Nome']) ?></td>
                            <td><?= htmlspecialchars(strtoupper($registro['Turma'])) ?></td>
                            <td><?= htmlspecialchars($registro['data/hr']) ?></td>
                            <td><?= htmlspecialchars($registro['NomeMaquina'] ?? 'N/A') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php elseif (isset($_GET['realizar_busca_registro'])): ?>
            <p class="no-records-found">Nenhum registro encontrado com os critérios especificados.</p>
        <?php endif; ?>
    </div>


    <button class="refresh-button" onclick="location.reload()">Atualizar Página</button>
    <form method="POST" action="http://localhost/ProjetoFinal/Fechar.php" class="logout-form">
        <button type="submit">Sair do Painel</button>
    </form>


</div>

<script>
    function openMainTab(evt, tabId) {
        var i, tabcontent, tablinks;

        tabcontent = document.getElementsByClassName("main-section");
        for (i = 0; i < tabcontent.length; i++) {
            tabcontent[i].style.display = "none";
            tabcontent[i].classList.remove('active');
        }

        tablinks = document.getElementsByClassName("main-tabs")[0].getElementsByTagName("button");
        for (i = 0; i < tablinks.length; i++) {
            tablinks[i].classList.remove("active");
        }

        document.getElementById(tabId).style.display = "block";
        document.getElementById(tabId).classList.add('active');
        if (evt) {
            evt.currentTarget.classList.add("active");
        } else {
            const initialButton = document.querySelector(`.main-tabs button[onclick*="'${tabId}'"]`);
            if (initialButton) {
                initialButton.classList.add('active');
            }
        }

        // Se a aba de "Gerenciar Turmas e Alunos" for ativada,
        // garantimos que a primeira mini-aba de turma seja exibida
        if (tabId === 'tabAlunosTurmas') {
            const firstMiniTabButton = document.querySelector('#tabAlunosTurmas .mini-abas button');
            if (firstMiniTabButton && !firstMiniTabButton.classList.contains('active')) {
                setTimeout(() => {
                    showTurma(firstMiniTabButton.textContent.toLowerCase());
                    firstMiniTabButton.classList.add('active');
                }, 10);
            }
        }
        // Se a aba de "Gerenciar Usuários" for ativada,
        // garantimos que o formulário de cadastro de usuário seja exibido por padrão
        if (tabId === 'tabUsuarios') {
            showUserForm('userFormCadastro');
            const cadastroButton = document.querySelector('#tabUsuarios .user-management-buttons button:first-child');
            if (cadastroButton) {
                cadastroButton.classList.add('active');
            }
        }
    }

    function showTurma(turmaId) {
        document.querySelectorAll('.turma').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.mini-abas button').forEach(btn => btn.classList.remove('active'));

        const targetTurma = document.getElementById(turmaId);
        if (targetTurma) {
            targetTurma.classList.add('active');
        }

        const buttons = document.querySelectorAll('.mini-abas button');
        buttons.forEach(button => {
            if (button.textContent.toLowerCase() === turmaId.toLowerCase()) {
                button.classList.add('active');
            }
        });
    }

    function showUserForm(formIdToShow) {
        const forms = ['userFormCadastro', 'userFormRemocao'];
        forms.forEach(id => {
            const form = document.getElementById(id);
            if (form) {
                form.classList.remove('user-form-visible');
                form.classList.add('user-form-hidden');
            }
        });

        const targetForm = document.getElementById(formIdToShow);
        if (targetForm) {
            targetForm.classList.remove('user-form-hidden');
            targetForm.classList.add('user-form-visible');
        }

        // Remove 'active' de todos os botões de gerenciamento de usuário
        document.querySelectorAll('.user-management-buttons button').forEach(btn => {
            btn.classList.remove('active');
        });

        // Adiciona 'active' ao botão clicado
        if (formIdToShow === 'userFormCadastro') {
            document.querySelector('#tabUsuarios .user-management-buttons button:first-child').classList.add('active');
        } else if (formIdToShow === 'userFormRemocao') {
            document.querySelector('#tabUsuarios .user-management-buttons button:last-child').classList.add('active');
        }
    }


    document.addEventListener('DOMContentLoaded', function() {
        const urlParams = new URLSearchParams(window.location.search);
        const activeTabFromUrl = urlParams.get('active_tab');
        const initialTab = activeTabFromUrl || 'tabAlunosTurmas';

        if (urlParams.has('realizar_busca_registro')) {
            openMainTab(null, 'tabBuscaRegistros');
        } else {
            openMainTab(null, initialTab);
        }

        if (initialTab === 'tabAlunosTurmas') {
            const firstMiniTabButton = document.querySelector('#tabAlunosTurmas .mini-abas button');
            if (firstMiniTabButton) {
                setTimeout(() => {
                    showTurma(firstMiniTabButton.textContent.toLowerCase());
                    firstMiniTabButton.classList.add('active');
                }, 50);
            }
        }
        // Assegura que o formulário de cadastro de usuário esteja visível quando a aba "Gerenciar Usuários" é carregada inicialmente
        if (initialTab === 'tabUsuarios') {
            showUserForm('userFormCadastro');
            const cadastroButton = document.querySelector('#tabUsuarios .user-management-buttons button:first-child');
            if (cadastroButton) {
                cadastroButton.classList.add('active');
            }
        }
    });
    document.addEventListener('contextmenu', event => event.preventDefault());
</script>

</body>
</html>