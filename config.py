# config.py
import json
import os

# Caminho para o arquivo de configurações dinâmicas
SETTINGS_FILE = os.path.join(os.path.dirname(__file__), 'settings.json')

def load_settings():
    defaults = {
        "steam_token": "020094507D2372836C9840E106FD8E2C",
        "start_map": "de_anubis",
        "server_name": "CS2 Server"
    }
    if os.path.exists(SETTINGS_FILE):
        try:
            with open(SETTINGS_FILE, 'r') as f:
                return {**defaults, **json.load(f)}
        except:
            pass
    return defaults

settings = load_settings()

# Configurações do RCON
RCON_HOST = '0.0.0.0'
RCON_PORT = 27015
RCON_PASSWORD = 'GZPWA3PyZ7zonPf'

# Diretório do servidor CS2
CS2_SERVER_DIR = '/media/astral/7DFD-F7FB/Counter-Strike2-Server/cs2/game'

def get_cs2_start_command():
    current_settings = load_settings()
    token = current_settings.get("steam_token", "")
    start_map = current_settings.get("start_map", "de_anubis")
    server_name = current_settings.get("server_name", "CS2 Server")
    
    cmd = [
        './cs2.sh',
        '-dedicated',
        '-nojoy',
        '-usercon',
        '-condebug',                   # 🌟 ADICIONADO: Escreve o console em game/csgo/console.log
        '+ip', '0.0.0.0',              # 🌟 ADICIONADO: Força escutar em todas as interfaces (Tailscale/Local)
        '+port', str(RCON_PORT),       # 🔧 ALTERADO: Mudado de -port para +port para maior estabilidade
        '+map', start_map,
        '+rcon_password', RCON_PASSWORD,
        '+hostname', server_name       # 🏷️ Opcional: Já puxa o nome do servidor direto do seu painel
    ]
    
    if token:
        cmd.extend(['+sv_setsteamaccount', token])
        
    return cmd


# Caminho do SteamCMD e diretório de instalação
STEAMCMD_PATH = '/media/astral/7DFD-F7FB/Counter-Strike2-Server/steamcmd/steamcmd.sh'
STEAMCMD_INSTALL_DIR = '/media/astral/7DFD-F7FB/Counter-Strike2-Server/cs2'
STEAM_APP_ID = '730'

# Novos Caminhos para Monitoramento e Logs
STEAM_INF_PATH = '/media/astral/7DFD-F7FB/Counter-Strike2-Server/cs2/game/csgo/steam.inf'
STEAM_MANIFEST_PATH = '/media/astral/7DFD-F7FB/Counter-Strike2-Server/cs2/steamapps/appmanifest_730.acf'
STEAMCMD_CONSOLE_LOG = '/media/astral/7DFD-F7FB/Counter-Strike2-Server/steamcmd/linux32/logs/console_log.txt'
OFFLINE_LOG_PATH = '/var/www/html/PanelCS2_PHP_RCON2-main/off_log.txt'

# Porta do agente Flask
AGENT_PORT = 5000
