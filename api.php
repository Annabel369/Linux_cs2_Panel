<?php
session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Não autorizado']);
    exit;
}

require_once 'rcon2.php';
require_once 'db_connect.php';

$agent_url = "http://localhost:5000";

function callPythonAgent($endpoint, $method = 'GET', $data = []) {
    global $agent_url;
    $url = $agent_url . $endpoint;
    $options = [
        'http' => [
            'method' => $method,
            'header' => 'Content-type: application/json',
            'content' => json_encode($data),
            'ignore_errors' => true
        ]
    ];
    $context = stream_context_create($options);
    $result = @file_get_contents($url, false, $context);

    if ($result === FALSE) {
        return ['success' => false, 'error' => 'Falha na conexão com o agente Python.'];
    }

    $response_data = json_decode($result, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['success' => false, 'error' => 'Resposta inválida do agente.'];
    }
    return $response_data;
}

// Handle GET requests for data lists (Admins, Bans, etc)
if ($_SERVER["REQUEST_METHOD"] === "GET" && isset($_GET['type'])) {
    header('Content-Type: application/json');
    $type = $_GET['type'];
    $page = $_GET['page'] ?? 1;
    $itemsPerPage = 10;
    $offset = ($page - 1) * $itemsPerPage;

    try {
        switch ($type) {
            case 'admins':
                $countSql = "SELECT COUNT(*) AS total_count FROM admins";
                $sql = "SELECT name, CAST(steamid AS CHAR) AS steamid, permission, level, timestamp AS granted_at FROM admins ORDER BY timestamp DESC LIMIT :itemsPerPage OFFSET :offset";
                break;
            case 'bans':
                $countSql = "SELECT COUNT(*) AS total_count FROM bans";
                $sql = "SELECT CAST(steamid AS CHAR) AS steamid, reason, unbanned, timestamp FROM bans ORDER BY timestamp DESC LIMIT :itemsPerPage OFFSET :offset";
                break;
            case 'ip_bans':
                $countSql = "SELECT COUNT(*) AS total_count FROM ip_bans";
                $sql = "SELECT ip_address, reason, unbanned, timestamp FROM ip_bans ORDER BY timestamp DESC LIMIT :itemsPerPage OFFSET :offset";
                break;
            case 'mutes':
                $countSql = "SELECT COUNT(*) AS total_count FROM mutes";
                $sql = "SELECT CAST(steamid AS CHAR) AS steamid, reason, unmuted, timestamp FROM mutes ORDER BY timestamp DESC LIMIT :itemsPerPage OFFSET :offset";
                break;
            default:
                http_response_code(400);
                echo json_encode(["error" => "Tipo de dado inválido."]);
                exit;
        }

        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute();
        $totalCount = $countStmt->fetchColumn();

        $dataStmt = $pdo->prepare($sql);
        $dataStmt->bindValue(':itemsPerPage', $itemsPerPage, PDO::PARAM_INT);
        $dataStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $dataStmt->execute();
        $data = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(["data" => $data, "total_count" => (int)$totalCount], JSON_UNESCAPED_UNICODE);
        exit;
    } catch (\PDOException $e) {
        http_response_code(500);
        echo json_encode(["error" => "Erro no banco de dados: " . $e->getMessage()], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["cs2_command_action"] ?? null;
    $response_output = "";
    $success = false;

    switch ($action) {
        case "start":
            $response = callPythonAgent('/start_server', 'POST');
            $success = $response['success'];
            $response_output = $success
                ? "✅ <span style='color: lime;'>Servidor iniciado com sucesso!</span>"
                : "❌ <span style='color: red;'>Erro ao iniciar: " . ($response['error'] ?? 'desconhecido') . "</span>";
            break;

        case "stop":
            $response = callPythonAgent('/stop_server', 'POST');
            $success = $response['success'];
            $response_output = $success
                ? "🛑 <span style='color: red;'>Servidor parado com sucesso!</span>"
                : "❌ <span style='color: red;'>Erro ao parar: " . ($response['error'] ?? 'desconhecido') . "</span>";
            break;

        case "status":
            $response = callPythonAgent('/server_status', 'GET');
            if ($response['success']) {
                $success = true;
                $status_text = $response['status'] ?? 'Desconhecido';
                $is_running = $response['is_running'] ?? false;
                $status_color = $is_running ? 'lime' : 'red';
                
                if ($status_text === 'Atualizando') $status_color = 'gold';

                $ram = $response['ram_usage'] ?? '0 MB';
                $cpu = $response['cpu_usage'] ?? '0%';
                $ver_local = $response['local_version'] ?? 'N/A';
                $ver_latest = $response['latest_version'] ?? 'N/A';
                $has_update = ($response['update_available'] ?? false) ? " <span style='color:orange; font-weight:bold;'>[UPDATE DISPONÍVEL!]</span>" : "";

                $response_output = "📊 <strong>Status em Tempo Real:</strong><br>";
                $response_output .= "• Estado: <span style='color:{$status_color}; font-weight:bold;'>{$status_text}</span>{$has_update}<br>";
                $response_output .= "• Recurso: RAM: {$ram} | CPU: {$cpu}<br>";
                $response_output .= "• Versão: Build {$ver_local} (Steam: {$ver_latest})";
            } else {
                $response_output = "❌ <span style='color: red;'>Erro ao obter status: " . ($response['error'] ?? 'desconhecido') . "</span>";
            }
            break;

        case "update":
            $response = callPythonAgent('/update_server', 'POST');
            $success = $response['success'];
            if ($success) {
                $response_output = "🚀 <span style='color: gold;'><strong>Processo de atualização iniciado!</strong></span><br>O servidor está sendo validado pela SteamCMD em segundo plano. Acompanhe o progresso no log abaixo.";
            } else {
                $response_output = "❌ <span style='color: red;'>Erro na atualização: " . htmlspecialchars($response['error'] ?? 'desconhecido') . "</span>";
            }
            break;

        case "rcon":
            $command = $_POST["rcon_command_input"] ?? '';
            if (!empty($command)) {
                $rcon = new Rcon($cs2_rcon_host, $cs2_rcon_port, $cs2_rcon_password, $rcon_timeout);
                if ($rcon->connect()) {
                    $response_rcon = $rcon->send_command($command);
                    if ($response_rcon !== false) {
                        $success = true;
                        $clean_response = trim(str_replace("\x01", '', $response_rcon));
                        $icon = stripos($command, 'status') !== false ? '📊' :
                                (stripos($clean_response, 'sucesso') !== false ? '✅' :
                                (stripos($clean_response, 'uso:') !== false || stripos($clean_response, 'erro') !== false ? '❌' : 'ℹ️'));
                        $response_output = "{$icon} Comando RCON enviado: `{$command}`<br>Resposta:<br><pre>" . htmlspecialchars($clean_response) . "</pre>";
                    } else {
                        $response_output = "❌ <span style='color: red;'>Erro ao enviar comando: " . htmlspecialchars($rcon->get_response()) . "</span>";
                    }
                    $rcon->disconnect();
                } else {
                    $response_output = "❌ <span style='color: red;'>Falha ao conectar ao RCON.</span>";
                }
            } else {
                $response_output = "⚠️ Por favor, digite um comando RCON.";
            }
            break;
        
        case "get_settings":
            $settings_file = 'settings.json';
            if (file_exists($settings_file)) {
                echo file_get_contents($settings_file);
            } else {
                echo json_encode(['steam_token' => '', 'start_map' => 'de_anubis', 'server_name' => 'CS2 Server']);
            }
            exit;

        case "save_settings":
            $new_settings = [
                "steam_token" => $_POST['steam_token'] ?? "",
                "start_map" => $_POST['start_map'] ?? "de_anubis",
                "server_name" => $_POST['server_name'] ?? "CS2 Server"
            ];
            if (file_put_contents('settings.json', json_encode($new_settings, JSON_PRETTY_PRINT))) {
                $success = true;
                $response_output = "✅ Configurações salvas com sucesso! **Reinicie o servidor para aplicar.**";
            } else {
                $response_output = "❌ Erro ao salvar o arquivo settings.json.";
            }
            break;
        
        case "reload_admins":
        case "show_plugins":
        case "show_admins":
            $cmds = [
                "reload_admins" => "css_reload_admins",
                "show_plugins" => "css_plugins list",
                "show_admins" => "css_admins"
            ];
            $command = $cmds[$action];
            $rcon = new Rcon($cs2_rcon_host, $cs2_rcon_port, $cs2_rcon_password, $rcon_timeout);
            if ($rcon->connect()) {
                $response_rcon = $rcon->send_command($command);
                if ($response_rcon !== false) {
                    $success = true;
                    $clean_response = trim(str_replace("\x01", '', $response_rcon));
                    $response_output = "🛠️ Comando Admin `{$command}` executado.<br>Resposta:<br><pre>" . htmlspecialchars($clean_response) . "</pre>";
                } else {
                    $response_output = "❌ Erro: " . htmlspecialchars($rcon->get_response());
                }
                $rcon->disconnect();
            } else {
                $response_output = "❌ Falha na conexão RCON.";
            }
            break;
        
        default:
            $response_output = "Ação inválida.";
            break;
    }

    header('Content-Type: application/json');
    echo json_encode(['success' => $success, 'message' => $response_output]);
    exit;
}
?>
