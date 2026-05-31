<?php
session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Não autorizado']);
    exit;
}

$agent_url = "http://localhost:5000/console_log";

$options = [
    'http' => [
        'method' => 'GET',
        'ignore_errors' => true
    ]
];
$context = stream_context_create($options);
$result = @file_get_contents($agent_url, false, $context);

if ($result === FALSE) {
    echo json_encode(['success' => false, 'error' => 'Falha na conexão com o agente Python.']);
    exit;
}

header('Content-Type: application/json');
echo $result;
?>
