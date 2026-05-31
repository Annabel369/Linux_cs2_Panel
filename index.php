<?php
session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit;
}

require_once 'db_connect.php';
$output_message = "Pronto para receber comandos.";
?>

<!DOCTYPE html>
<html lang="pt">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel Unificado - Counter-Strike 2</title>
    <link rel="icon" href="./favicon.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <style>
        :root {
            --primary-orange: #e67e22;
            --hover-orange: #d35400;
            --bg-dark: #1a1a1a;
            --card-bg: #2d2d2d;
            --input-bg: #1a1a1a;
            --text-color: #e0e0e0;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--bg-dark);
            color: var(--text-color);
            text-align: center;
            padding: 20px;
            margin: 0;
        }

        .container {
            max-width: 900px;
            margin: auto;
            background: var(--card-bg);
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0px 8px 30px rgba(0, 0, 0, 0.5);
        }

        h2 {
            color: #f39c12;
            margin-bottom: 20px;
            font-weight: 300;
            text-transform: uppercase;
            letter-spacing: 2px;
        }

        h3 {
            color: #bdc3c7;
            font-size: 1.1em;
            margin-top: 25px;
            border-bottom: 1px solid #444;
            padding-bottom: 8px;
            text-align: left;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .output {
            background: #000;
            padding: 15px;
            border-radius: 8px;
            min-height: 50px;
            max-height: 250px;
            overflow-y: auto;
            white-space: pre-wrap;
            word-wrap: break-word;
            margin-top: 15px;
            text-align: left;
            border: 1px solid #333;
            font-family: 'Consolas', monospace;
            font-size: 0.9em;
        }

        .btn-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 10px;
            margin-top: 15px;
        }

        button {
            background: var(--primary-orange);
            color: #fff;
            cursor: pointer;
            border-radius: 6px;
            border: none;
            padding: 10px 15px;
            font-size: 0.85em;
            font-weight: 600;
            text-transform: uppercase;
            transition: all 0.2s ease;
            width: 100%;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.2);
        }

        button:hover {
            background: var(--hover-orange);
            transform: translateY(-1px);
        }

        button:active {
            transform: translateY(1px);
        }

        .btn-admin {
            background: #2980b9;
        }

        .btn-admin:hover {
            background: #1f6391;
        }

        .btn-connect {
            background: #27ae60;
            margin-top: 20px;
            padding: 15px;
            font-size: 1em;
        }

        input[type="text"],
        select {
            width: 100%;
            background: var(--input-bg);
            color: #fff;
            border: 1px solid #444;
            padding: 12px;
            border-radius: 6px;
            box-sizing: border-box;
            margin-top: 5px;
            font-size: 0.9em;
        }

        .imagem-container {
            margin-bottom: 20px;
        }

        .imagem-container img {
            width: 100%;
            max-width: 180px;
            border-radius: 10px;
            filter: drop-shadow(0 5px 15px rgba(0, 0, 0, 0.5));
        }

        #live-console {
            background: #000;
            color: #2ecc71;
            font-size: 0.8em;
            border: 1px solid #27ae60;
            height: 300px;
            max-height: 400px;
        }

        .logout-btn {
            position: absolute;
            top: 20px;
            right: 20px;
            width: auto;
            background: #c0392b;
            padding: 8px 15px;
        }

        /* Settings Box */
        .settings-box {
            background: #383838;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: left;
            display: none;
            border: 1px dashed #555;
        }

        .toggle-btn-small {
            background: #444;
            margin-bottom: 15px;
            width: auto;
            padding: 8px 15px;
            font-size: 0.75em;
            display: inline-block;
        }

        /* Data Tables Section */
        .tab-container {
            display: flex;
            gap: 5px;
            margin-top: 15px;
            overflow-x: auto;
            padding-bottom: 10px;
        }

        .tab-button {
            background: #333;
            color: #aaa;
            padding: 8px 15px;
            border-radius: 6px 6px 0 0;
            width: auto;
            text-transform: none;
            font-size: 0.8em;
            box-shadow: none;
            border-bottom: 2px solid transparent;
        }

        .tab-button:hover {
            background: #444;
        }

        .tab-button.active {
            background: #444;
            color: var(--primary-orange);
            border-bottom-color: var(--primary-orange);
        }

        .data-table-container {
            background: #222;
            border-radius: 0 0 8px 8px;
            border: 1px solid #444;
            padding: 15px;
            text-align: left;
            min-height: 200px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85em;
            margin-bottom: 10px;
        }

        th {
            background: #333;
            color: var(--primary-orange);
            text-align: left;
            padding: 12px;
            border-bottom: 2px solid #444;
        }

        td {
            padding: 12px;
            border-bottom: 1px solid #333;
            color: #ccc;
        }

        tr:hover {
            background: #2a2a2a;
        }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 5px;
            margin-top: 15px;
        }

        .pagination-link {
            padding: 5px 10px;
            background: #333;
            border-radius: 4px;
            color: #fff;
            text-decoration: none;
            font-size: 0.8em;
        }

        .pagination-link.active {
            background: var(--primary-orange);
        }

        .status-badge {
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.75em;
            font-weight: bold;
        }

        .badge-active {
            background: #c0392b;
            color: #fff;
        }

        .badge-inactive {
            background: #27ae60;
            color: #fff;
        }

        hr {
            border: 0;
            border-top: 1px solid #444;
            margin: 30px 0;
        }

        /* Novas Badges de Status */
        .status-bar {
            display: flex;
            justify-content: space-around;
            background: #333;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #444;
        }

        .status-item {
            flex: 1;
            text-align: center;
            border-right: 1px solid #444;
        }

        .status-item:last-child {
            border-right: none;
        }

        .status-item span {
            display: block;
            font-weight: bold;
            color: var(--primary-orange);
            font-size: 1.1em;
        }

        .status-item label {
            font-size: 0.75em;
            color: #888;
            text-transform: uppercase;
        }

        .update-alert {
            background: #d35400;
            color: #fff;
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 15px;
            font-weight: bold;
            animation: pulse 2s infinite;
            display: none;
        }

        @keyframes pulse {
            0% {
                opacity: 0.8;
            }

            50% {
                opacity: 1;
            }

            100% {
                opacity: 0.8;
            }
        }

        .console-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #111;
            padding: 5px 15px;
            border-radius: 8px 8px 0 0;
            border: 1px solid #27ae60;
            border-bottom: none;
            font-size: 0.8em;
        }

        .log-mode {
            font-weight: bold;
            color: #2ecc71;
        }

        .log-mode.offline {
            color: #e74c3c;
        }

        .log-mode.updating {
            color: #f1c40f;
        }
    </style>
</head>

<body>
    <button class="logout-btn" onclick="window.location.href='logout.php'">Sair</button>

    <div class="imagem-container">
        <img src="Counter-Strike 2 - W.png" alt="Counter-Strike 2">
    </div>

    <div class="container">
        <h2>Paine Unificado CS2</h2>

        <div style="text-align: left;">
            <button class="toggle-btn-small" onclick="toggleSection('settings-box')"><i class="fas fa-cog"></i>
                Configurações Base</button>
        </div>

        <div id="settings-box" class="settings-box">
            <h3 style="margin-top:0;"><i class="fas fa-sliders-h"></i> Ajustes de Inicialização</h3>
            <label>Token Steam (GSLT) - <a href="https://steamcommunity.com/dev/managegameservers" target="_blank"
                    style="color:#f39c12;">Gerar App ID 730</a></label>
            <input type="text" id="set_steam_token" placeholder="Token da Steam">

            <label>Hostname (Nome do Servidor)</label>
            <input type="text" id="set_server_name" placeholder="Ex: [Brasil] Meu Servidor">

            <label>Mapa Inicial</label>
            <select id="set_start_map">
                <option value="de_anubis">Anubis</option>
                <option value="de_dust2">Dust 2</option>
                <option value="de_mirage">Mirage</option>
                <option value="de_inferno">Inferno</option>
                <option value="de_nuke">Nuke</option>
                <option value="de_vertigo">Vertigo</option>
                <option value="de_ancient">Ancient</option>
            </select>

            <button type="button" style="margin-top:15px; background:#27ae60;" onclick="saveSettings()">Salvar no
                arquivo</button>
        </div>

        <!-- Nova Barra de Status -->
        <div class="status-bar">
            <div class="status-item">
                <label>Uso de CPU</label>
                <span id="stat-cpu">0%</span>
            </div>
            <div class="status-item">
                <label>Uso de RAM</label>
                <span id="stat-ram">0 MB</span>
            </div>
            <div class="status-item">
                <label>Build Local</label>
                <span id="stat-ver">N/A</span>
            </div>
        </div>

        <div id="update-alert" class="update-alert">
            <i class="fas fa-arrow-circle-up"></i> NOVA ATUALIZAÇÃO DISPONÍVEL NA STEAM!
        </div>

        <h3><i class="fas fa-power-off"></i> Controle de Instância</h3>
        <div class="btn-grid">
            <button type="button" onclick="sendCommand('start')">Ligar</button>
            <button type="button" onclick="sendCommand('stop')">Desligar</button>
            <button type="button" onclick="sendCommand('status')">Status</button>
            <button type="button" onclick="sendCommand('update')">Atualizar</button>
        </div>

        <h3><i class="fas fa-user-shield"></i> Comandos Rápidos Admin</h3>
        <div class="btn-grid">
            <button type="button" class="btn-admin" onclick="sendCommand('reload_admins')">Recarregar Lista</button>
            <button type="button" class="btn-admin" onclick="sendCommand('show_plugins')">Ver Plugins</button>
            <button type="button" class="btn-admin" onclick="sendCommand('show_admins')">Admins Online</button>
        </div>

        <hr>

        <h3><i class="fas fa-terminal"></i> Console Interativo</h3>
        <input type="text" id="rcon_command_input" placeholder="Comando RCON (Ex: sv_cheats 1)...">
        <button type="button" style="margin-top:10px;" onclick="sendCommand('rcon')">Enviar Comando</button>

        <a href="steam://connect/<?php echo $cs2_rcon_host . ':' . $cs2_rcon_port; ?>" style="text-decoration: none;">
            <button type="button" class="btn-connect">🎮 ENTRAR NO JOGO (IP: <?php echo $cs2_rcon_host; ?>)</button>
        </a>

        <div class="output" id="command-result"><?php echo $output_message; ?></div>

        <hr>

        <h3><i class="fas fa-database"></i> Gestão de Dados (Database)</h3>
        <div class="tab-container">
            <button class="tab-button active" onclick="switchDataTab('admins', this)">Admins</button>
            <button class="tab-button" onclick="switchDataTab('bans', this)">Banimentos</button>
            <button class="tab-button" onclick="switchDataTab('ip_bans', this)">IP Bans</button>
            <button class="tab-button" onclick="switchDataTab('mutes', this)">Silenciados</button>
        </div>
        <div class="data-table-container">
            <div id="data-table-render">Carregando dados...</div>
            <div id="data-pagination" class="pagination"></div>
        </div>

        <hr>
        <h3><i class="fas fa-scroll"></i> Log em Tempo Real</h3>
        <div class="console-header">
            <span id="log-file-path">C:\...\console.log</span>
            <span id="log-status-mode" class="log-mode">LIVE</span>
        </div>
        <div id="live-console" class="output" style="margin-top:0; border-radius: 0 0 8px 8px;">Aguardando dados do
            servidor...</div>
    </div>

    <footer style="margin-top: 40px; font-size: 0.8em; color: #444;">
        © 2025 — Desenvolvido para Amauri Bueno dos Santos. Sistema de Monitoramento Avançado.
    </footer>

    <script>
        let currentDataType = 'admins';

        function toggleSection(id) {
            const box = document.getElementById(id);
            box.style.display = box.style.display === 'block' ? 'none' : 'block';
            if (id === 'settings-box' && box.style.display === 'block') loadSettings();
        }

        // Settings Logic
        function loadSettings() {
            fetch('api.php', { method: 'POST', body: new URLSearchParams({ 'cs2_command_action': 'get_settings' }) })
                .then(r => r.json()).then(data => {
                    document.getElementById('set_steam_token').value = data.steam_token || '';
                    document.getElementById('set_server_name').value = data.server_name || '';
                    document.getElementById('set_start_map').value = data.start_map || 'de_anubis';
                });
        }

        function saveSettings() {
            const res = document.getElementById('command-result');
            const fd = new FormData();
            fd.append('cs2_command_action', 'save_settings');
            fd.append('steam_token', document.getElementById('set_steam_token').value);
            fd.append('server_name', document.getElementById('set_server_name').value);
            fd.append('start_map', document.getElementById('set_start_map').value);
            fetch('api.php', { method: 'POST', body: fd }).then(r => r.json()).then(data => {
                res.innerHTML = data.message;
                toggleSection('settings-box');
            });
        }

        // Server Commands
        function sendCommand(action) {
            const res = document.getElementById('command-result');
            const inp = document.getElementById('rcon_command_input');
            const cmd = inp ? inp.value : '';
            res.innerHTML = "⏳ Processando...";
            const fd = new FormData();
            fd.append('cs2_command_action', action);
            if (action === 'rcon') {
                if (!cmd) { res.innerHTML = "⚠️ Digite um comando."; return; }
                fd.append('rcon_command_input', cmd);
            }
            fetch('api.php', { method: 'POST', body: fd }).then(r => r.json()).then(data => {
                res.innerHTML = data.message;
                if (action === 'rcon' && data.success) inp.value = '';
            });
        }

        // Real-time Console and Status
        function updateConsole() {
            fetch('get_console.php').then(r => r.json()).then(data => {
                const con = document.getElementById('live-console');
                const statCpu = document.getElementById('stat-cpu');
                const statRam = document.getElementById('stat-ram');
                const statVer = document.getElementById('stat-ver');
                const updateAlert = document.getElementById('update-alert');
                const logMode = document.getElementById('log-status-mode');
                const logPath = document.getElementById('log-file-path');

                if (data.success) {
                    // Update Status Badges
                    if (statCpu) statCpu.innerText = data.cpu_usage || '0%';
                    if (statRam) statRam.innerText = data.ram_usage || '0 MB';
                    if (statVer) statVer.innerText = data.local_version || 'N/A';

                    // Update Alert
                    if (updateAlert) updateAlert.style.display = data.update_available ? 'block' : 'none';

                    // Update Log Mode
                    if (data.status_id === 2) {
                        logMode.innerText = "🔄ATUALIZANDO (STEAMCMD)";
                        logMode.className = "log-mode updating";
                        logPath.innerText = "C:\\steamcmd\\logs\\console_log.txt";
                    } else if (data.status_id === 1) {
                        logMode.innerText = "🟢 LIVE (MODO ONLINE)";
                        logMode.className = "log-mode";
                        logPath.innerText = "C:\\cs2-ds\\...\\console.log";
                    } else {
                        logMode.innerText = "🔴 OFFLINE (CARREGANDO LOGS)";
                        logMode.className = "log-mode offline";
                        logPath.innerText = "off_log.txt";
                    }

                    // Update Console Text
                    if (data.lines) {
                        const txt = data.lines.join('\n');
                        if (con.innerText !== txt) { con.innerText = txt; con.scrollTop = con.scrollHeight; }
                    }
                }
            });
        }

        // Data Tables Logic
        async function switchDataTab(type, btn) {
            currentDataType = type;
            document.querySelectorAll('.tab-button').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            loadData(type, 1);
        }

        async function loadData(type, page = 1) {
            const renderArea = document.getElementById('data-table-render');
            const paginationArea = document.getElementById('data-pagination');
            renderArea.innerHTML = "⏳ Carregando...";

            try {
                const response = await fetch(`api.php?type=${type}&page=${page}`);
                const result = await response.json();
                renderTable(result.data, type);
                renderPagination(result.total_count, page, type);
            } catch (e) {
                renderArea.innerHTML = "❌ Erro ao carregar dados.";
            }
        }

        function renderTable(data, type) {
            const area = document.getElementById('data-table-render');
            if (!data || data.length === 0) {
                area.innerHTML = "ℹ️ Nenhum registro encontrado.";
                return;
            }

            let html = `<table><thead><tr>`;
            if (type === 'admins') {
                html += `<th>Nome</th><th>SteamID</th><th>Perm</th><th>Nível</th><th>Desde</th>`;
            } else if (type === 'bans' || type === 'mutes') {
                html += `<th>Status</th><th>SteamID</th><th>Razão</th><th>Data</th>`;
            } else if (type === 'ip_bans') {
                html += `<th>Status</th><th>IP</th><th>Razão</th><th>Data</th>`;
            }
            html += `</tr></thead><tbody>`;

            data.forEach(item => {
                html += `<tr>`;
                if (type === 'admins') {
                    html += `<td>${item.name}</td><td>${item.steamid}</td><td>${item.permission}</td><td>${item.level}</td><td>${item.granted_at}</td>`;
                } else if (type === 'bans') {
                    const active = !item.unbanned;
                    html += `<td><span class="status-badge ${active ? 'badge-active' : 'badge-inactive'}">${active ? 'BANIDO' : 'LIVRE'}</span></td><td>${item.steamid}</td><td>${item.reason}</td><td>${item.timestamp}</td>`;
                } else if (type === 'mutes') {
                    const active = !item.unmuted;
                    html += `<td><span class="status-badge ${active ? 'badge-active' : 'badge-inactive'}">${active ? 'MUDO' : 'ATIVO'}</span></td><td>${item.steamid}</td><td>${item.reason}</td><td>${item.timestamp}</td>`;
                } else if (type === 'ip_bans') {
                    const active = !item.unbanned;
                    html += `<td><span class="status-badge ${active ? 'badge-active' : 'badge-inactive'}">${active ? 'BANIDO' : 'LIVRE'}</span></td><td>${item.ip_address}</td><td>${item.reason}</td><td>${item.timestamp}</td>`;
                }
                html += `</tr>`;
            });
            html += `</tbody></table>`;
            area.innerHTML = html;
        }

        function renderPagination(total, current, type) {
            const area = document.getElementById('data-pagination');
            const pages = Math.ceil(total / 10);
            area.innerHTML = "";
            for (let i = 1; i <= pages; i++) {
                const a = document.createElement('a');
                a.className = `pagination-link ${i === current ? 'active' : ''}`;
                a.href = "#";
                a.textContent = i;
                a.onclick = (e) => { e.preventDefault(); loadData(type, i); };
                area.appendChild(a);
            }
        }

        setInterval(updateConsole, 2500);
        updateConsole();
        loadData('admins', 1); // Carga inicial
    </script>
</body>

</html>