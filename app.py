from flask import Flask, jsonify, request
import subprocess
import signal
import logging
import os
import psutil
import time
import threading
import re

from config import (
    RCON_HOST, RCON_PORT, RCON_PASSWORD,
    get_cs2_start_command, AGENT_PORT,
    CS2_SERVER_DIR, STEAMCMD_PATH,
    STEAMCMD_INSTALL_DIR, STEAM_APP_ID,
    STEAM_INF_PATH, STEAM_MANIFEST_PATH,
    STEAMCMD_CONSOLE_LOG, OFFLINE_LOG_PATH
)

# Configuração de Logging
logging.basicConfig(level=logging.INFO, format='%(asctime)s - %(levelname)s - %(message)s')

app = Flask(__name__)

# Estado Global do Agente
agente_status = {
    "estado": 0,           # 0: Parado, 1: Rodando, 2: Atualizando, 3: Erro
    "is_running": False,
    "ram_usage": "0 MB",
    "cpu_usage": "0%",
    "local_version": "Desconhecida",
    "latest_version": "Desconhecida",
    "update_available": False,
    "last_check": 0
}

class ServerMonitor(threading.Thread):
    def __init__(self):
        super().__init__()
        self.daemon = True
        self.running = True

    def run(self):
        logging.info("Monitor de Servidor Iniciado.")
        while self.running:
            try:
                proc = self.update_process_status()
                # A cada 10 ciclos de status (50s), verifica verões se necessário
                self.update_versions()
                time.sleep(5)
            except Exception as e:
                logging.error(f"Erro no monitor: {e}")
                time.sleep(10)

    def find_cs2_process(self):
        try:
            server_base = '/media/astral/7DFD-F7FB/Counter-Strike2-Server/cs2'
            for proc in psutil.process_iter(['pid', 'name', 'exe', 'cmdline']):
                try:
                    pname = (proc.info.get('name') or '').lower()
                    exe_path = proc.info.get('exe') or ''
                    cmdline = proc.info.get('cmdline') or []
                    cmdline_str = ' '.join(cmdline).lower()
                    # Match the actual cs2 binary running from our server dir
                    if pname == 'cs2' and server_base.lower() in exe_path.lower():
                        return proc
                    # Fallback: check cmdline for cs2.sh from our dir
                    if 'cs2' in pname and server_base.lower() in cmdline_str:
                        return proc
                except (psutil.NoSuchProcess, psutil.AccessDenied, psutil.ZombieProcess):
                    continue
        except Exception as e:
            logging.error(f"Erro ao buscar processo: {e}")
        return None

    def update_process_status(self):
        global agente_status
        proc = self.find_cs2_process()
        
        was_running = agente_status["is_running"]
        agente_status["is_running"] = proc is not None
        
        if proc:
            try:
                # Se o estado não estiver como 'Atualizando', define como 'Rodando'
                if agente_status["estado"] != 2:
                    agente_status["estado"] = 1
                
                # RAM
                ram_mb = proc.memory_info().rss / (1024 * 1024)
                agente_status["ram_usage"] = f"{ram_mb:.2f} MB"
                
                # CPU (Uso do processo)
                agente_status["cpu_usage"] = f"{proc.cpu_percent(interval=None):.1f}%"
                return proc
            except (psutil.NoSuchProcess, psutil.AccessDenied):
                agente_status["is_running"] = False

        if not proc:
            if was_running:
                # O servidor acabou de fechar, salvar log final
                logging.info("Servidor detectado como PARADO. Capturando log final...")
                self.capture_shutdown_log()
            
            if agente_status["estado"] != 2:
                agente_status["estado"] = 0
            agente_status["ram_usage"] = "0 MB"
            agente_status["cpu_usage"] = "0%"
        return None

    def update_versions(self):
        global agente_status
        now = time.time()
        
        # Só verifica versão a cada 30 minutos ou se for a primeira vez
        if agente_status["last_check"] != 0 and (now - agente_status["last_check"] < 1800):
            return

        # Versão Local via appmanifest
        if os.path.exists(STEAM_MANIFEST_PATH):
            try:
                with open(STEAM_MANIFEST_PATH, 'r', encoding='utf-8') as f:
                    content = f.read()
                    match = re.search(r'"buildid"\s+"(\d+)"', content)
                    if match:
                        agente_status["local_version"] = match.group(1)
            except Exception as e:
                logging.error(f"Erro ao ler buildid local: {e}")

        # Versão Remota via SteamCMD (Anônimo)
        try:
            logging.info("Checando nova versão na Steam...")
            env = os.environ.copy()
            env['HOME'] = '/home/astral'
            env['USER'] = 'astral'
            cmd = f'"{STEAMCMD_PATH}" +login anonymous +app_info_update 1 +app_info_print {STEAM_APP_ID} +quit'
            result = subprocess.run(cmd, capture_output=True, shell=True, timeout=60, user='astral', env=env)
            
            # Decodifica de forma segura ignorando caracteres não reconhecidos
            output_str = result.stdout.decode('utf-8', errors='ignore')
            
            match = re.search(r'"public".*?"buildid"\s+"(\d+)"', output_str, re.DOTALL)
            if match:
                agente_status["latest_version"] = match.group(1)
                agente_status["update_available"] = agente_status["latest_version"] != agente_status["local_version"]
                logging.info(f"Build Local: {agente_status['local_version']} | Build Steam: {agente_status['latest_version']}")
        except Exception as e:
            logging.error(f"Erro ao buscar versão remota: {e}")

        agente_status["last_check"] = now

    def capture_shutdown_log(self):
        """Copia as últimas linhas do log ativo para o log offline."""
        active_log = '/media/astral/7DFD-F7FB/Counter-Strike2-Server/cs2/game/csgo/addons/metamod/console.log'
        if not os.path.exists(active_log):
            active_log = '/media/astral/7DFD-F7FB/Counter-Strike2-Server/cs2/game/csgo/console.log'
        
        if os.path.exists(active_log):
            try:
                with open(active_log, 'r', encoding='latin1', errors='replace') as f:
                    lines = f.readlines()
                    last_lines = lines[-100:]
                
                with open(OFFLINE_LOG_PATH, 'a', encoding='utf-8') as f:
                    f.write(f"\n--- SERVIDOR DESLIGADO EM {time.strftime('%Y-%m-%d %H:%M:%S')} ---\n")
                    f.writelines(last_lines)
                    f.write("\n----------------------------------------------------")
                    f.write(f"\n( ̲̅:̲̅:̲̅:̲̅[̲̅ ̲̅]̲̅:̲̅:̲̅:̲̅ ) (Server Desligado)  {time.strftime('%Y-%m-%d %H:%M:%S')} ( ̲̅:̲̅:̲̅:̲̅[̲̅ ̲̅]̲̅:̲̅:̲̅:̲̅ )")
                    f.write("\n▬▬▬.◙.▬▬▬")
                    f.write("\n═▂▄▄▓▄▄▂")
                    f.write("\n◢◤ █▀🐒▀████▄▄▄▄◢◤")
                    f.write("\n█▄ █ー ███▀▀▀▀▀▀▀╬")
                    f.write("\n◥█████◤")
                    f.write("\n══╩══╩═")
            except Exception as e:
                logging.error(f"Erro ao capturar log de desligamento: {e}")

# Iniciar Monitor
monitor = ServerMonitor()
monitor.start()

def write_agent_log(message):
    try:
        with open(OFFLINE_LOG_PATH, 'a', encoding='utf-8') as f:
            f.write(f"{time.strftime('%Y-%m-%d %H:%M:%S')} - INFO - {message}\n")
    except Exception as e:
        logging.error(f"Erro ao escrever off_log: {e}")

@app.route('/start_server', methods=['POST'])
def start_server():
    if agente_status["is_running"]:
        return jsonify({'success': False, 'error': 'Servidor já está rodando.'}), 409

    try:
        cmd = get_cs2_start_command()
        logging.info(f"Iniciando servidor com comando: {cmd}")
        # Redireciona a saída para um arquivo de log para depuração
        log_file = open('/var/www/html/PanelCS2_PHP_RCON2-main/server_output.log', 'a')
        subprocess.Popen(
            cmd,
            cwd=CS2_SERVER_DIR,
            stdout=log_file,
            stderr=log_file,
            stdin=subprocess.DEVNULL,
            user='astral',
            start_new_session=True
        )
        
        # Atualização Instantânea
        agente_status["is_running"] = True
        agente_status["estado"] = 1
        
        return jsonify({'success': True, 'message': 'Comando de inicialização enviado.'})
    except Exception as e:
        logging.error(f"Erro ao iniciar servidor: {e}")
        return jsonify({'success': False, 'error': str(e)}), 500

@app.route('/stop_server', methods=['POST'])
def stop_server():
    proc = monitor.find_cs2_process()
    if not proc:
        return jsonify({'success': False, 'error': 'Servidor não está rodando.'}), 404

    try:
        # Capturar o log imediatamente antes do processo morrer 
        # (assim a transição para OFFLINE na UI já possui as últimas mensagens prontas)
        monitor.capture_shutdown_log()

        logging.info(f"Parando servidor PID: {proc.pid}")
        try:
            os.killpg(os.getpgid(proc.pid), signal.SIGTERM)
        except (ProcessLookupError, PermissionError):
            proc.terminate()
        
        # Sincronização Imediata
        agente_status["estado"] = 0
        agente_status["is_running"] = False
        agente_status["ram_usage"] = "0 MB"
        agente_status["cpu_usage"] = "0%"
        
        return jsonify({'success': True, 'message': 'Comando de encerramento enviado.'})
    except Exception as e:
        logging.error(f"Erro ao parar servidor: {e}")
        return jsonify({'success': False, 'error': str(e)}), 500

@app.route('/server_status', methods=['GET'])
def server_status():
    status_map = {0: "Parado", 1: "Rodando", 2: "Atualizando", 3: "Erro"}
    return jsonify({
        'success': True,
        'status': status_map.get(agente_status["estado"], "Desconhecido"),
        'is_running': agente_status["is_running"],
        'ram_usage': agente_status["ram_usage"],
        'cpu_usage': agente_status["cpu_usage"],
        'local_version': agente_status["local_version"],
        'latest_version': agente_status["latest_version"],
        'update_available': agente_status["update_available"]
    })

@app.route('/update_server', methods=['POST'])
def update_server():
    # 1. Segurança: Usamos o método do monitor para checar se o processo existe
    if monitor.find_cs2_process():
        return jsonify({'success': False, 'error': 'O servidor está rodando. Desligue-o antes de atualizar.'}), 400

    def run_update():
        global agente_status
        agente_status["estado"] = 2
        
        try:
            # --- PASSO 1: STEAMCMD ---
            write_agent_log("Iniciando SteamCMD (Atualização/Validação)...")
            steamcmd_command = [
                STEAMCMD_PATH,
                '+force_install_dir', STEAMCMD_INSTALL_DIR,
                '+login', 'anonymous',
                '+app_update', STEAM_APP_ID, 'validate',
                '+quit'
            ]
            
            # Limpa o log de atualização para o PHP ler do zero
            if os.path.exists(OFFLINE_LOG_PATH):
                open(OFFLINE_LOG_PATH, 'w').close()
            
            env = os.environ.copy()
            env['HOME'] = '/home/astral'
            env['USER'] = 'astral'
            # Executa o SteamCMD e captura a saída para o log em tempo real
            process = subprocess.Popen(
                " ".join(steamcmd_command),
                stdout=subprocess.PIPE,
                stderr=subprocess.STDOUT,
                shell=True,
                user='astral',
                env=env
            )

            while True:
                line = process.stdout.readline()
                if not line and process.poll() is not None:
                    break
                if line:
                    decoded = line.decode('latin1', errors='replace').strip()
                    if decoded:
                        write_agent_log(f"SteamCMD: {decoded}")

            # --- PASSO 2: METAMOD ---
            write_agent_log("Verificando Metamod no gameinfo.gi...")
            gameinfo_path = os.path.join(STEAMCMD_INSTALL_DIR, 'game', 'csgo', 'gameinfo.gi')
            metamod_entry = "Game\tcsgo/addons/metamod" 

            if os.path.exists(gameinfo_path):
                with open(gameinfo_path, 'r', encoding='utf-8') as f:
                    content = f.readlines()

                if not any(metamod_entry in line for line in content):
                    write_agent_log("Injetando Metamod...")
                    new_content = []
                    added = False
                    for line in content:
                        new_content.append(line)
                        if "csgo_lv" in line and not added:
                            new_content.append(f"\t\t\t{metamod_entry}\n")
                            added = True
                    
                    with open(gameinfo_path, 'w', encoding='utf-8') as f:
                        f.writelines(new_content)
                    write_agent_log("Metamod configurado.")
                else:
                    write_agent_log("Metamod já está presente.")

            # --- PASSO 3: SERVER.CFG (RCON & CONFIGS) ---
            write_agent_log("Validando server.cfg e RCON...")
            cfg_path = os.path.join(STEAMCMD_INSTALL_DIR, 'game', 'csgo', 'cfg', 'server.cfg')
            
            default_cfg = f"""// Auto-generated by Agent
hostname "[CS2]ASTRAL SERVER SKINS | KNIFE | WS | VIPNIGHT | RANKED"
sv_lan "0"
sv_region "2"
rcon_password "{RCON_PASSWORD}"
rcon_port 27018
sv_maxrate "0"
sv_minrate "0"
sv_parallel_sendsnapshot "1"
sv_clockcorrection_msecs "15"
sv_hibernate_when_empty "0"
mp_endmatch_votenextmap "1"
mp_match_end_changelevel "1"
mp_match_end_restart "0"
bot_quota "0"
mp_autoteambalance "true"
mp_humanteam "any"
mp_limitteams "2"
sv_alltalk "1"
sv_talk_enemy_dead "0"
sv_talk_enemy_living "0"
"""
            
            reparar_cfg = True
            if os.path.exists(cfg_path):
                with open(cfg_path, 'r', encoding='utf-8') as f:
                    current_content = f.read()
                    if f'rcon_password "{RCON_PASSWORD}"' in current_content:
                        reparar_cfg = False
            
            if reparar_cfg:
                with open(cfg_path, 'w', encoding='utf-8') as f:
                    f.write(default_cfg)
                write_agent_log("server.cfg reparado/atualizado.")
            else:
                write_agent_log("server.cfg já contém a RCON correta.")

            write_agent_log("=== ATUALIZAÇÃO CONCLUÍDA COM SUCESSO ===")
            agente_status["estado"] = 0 
            agente_status["last_check"] = 0 # Força o monitor a checar a nova versão buildid

        except Exception as e:
            error_msg = f"ERRO CRÍTICO NA ATUALIZAÇÃO: {str(e)}"
            logging.error(error_msg)
            write_agent_log(error_msg)
            agente_status["estado"] = 3 

    # Inicia a thread
    threading.Thread(target=run_update, daemon=True).start()
    
    return jsonify({
        'success': True, 
        'message': 'Processo de atualização iniciado em segundo plano.'
    })

@app.route('/console_log', methods=['GET'])
def console_log():
    # Caminhos solicitados pelo usuário
    if agente_status["estado"] == 2:
        log_path = STEAMCMD_CONSOLE_LOG
    elif agente_status["is_running"]:
        log_path = '/media/astral/7DFD-F7FB/Counter-Strike2-Server/cs2/game/csgo/addons/metamod/console.log'
        if not os.path.exists(log_path):
            log_path = '/media/astral/7DFD-F7FB/Counter-Strike2-Server/cs2/game/csgo/console.log'
    else:
        log_path = OFFLINE_LOG_PATH

    try:
        if agente_status["estado"] == 0:
            # Em vez de carregar o texto gigante e quebrar o desenho da view, apenas retorna que está off.
            # O log_path real continuará recebendo o backup nos bastidores pela captura de finalização.
            log_lines = [
                "",
                "              [ SERVIDOR OFFLINE ]",
                "   Os detalhes do último log de desligamento",
                "   foram salvos na raiz do agente em off_log.txt",
                "   ▬▬▬.◙.▬▬▬",
                "   ═▂▄▄▓▄▄▂",
                "   ◢◤ █▀🐒▀████▄▄▄▄◢◤",
                "   █▄ █ー ███▀▀▀▀▀▀▀╬",
                "   ◥█████◤",
                "   ══╩══╩═",
                ""
            ]
        else:
            if os.path.exists(log_path):
                with open(log_path, 'r', encoding='latin1', errors='replace') as f:
                    lines = f.readlines()
                    log_lines = [l.strip() for l in lines[-50:]]
            else:
                log_lines = [f"--- Arquivo de log não encontrado em: {log_path} ---"]
            
        return jsonify({
            'success': True,
            'status_id': agente_status["estado"],
            'is_running': agente_status["is_running"],
            'ram_usage': agente_status["ram_usage"],
            'cpu_usage': agente_status["cpu_usage"],
            'local_version': agente_status["local_version"],
            'latest_version': agente_status["latest_version"],
            'update_available': agente_status["update_available"],
            'lines': log_lines
        })
    except Exception as e:
        return jsonify({'success': False, 'error': str(e)})

if __name__ == '__main__':
    app.run(host='0.0.0.0', port=AGENT_PORT)
