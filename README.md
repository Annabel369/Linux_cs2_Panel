# 🛠️ CS2 Control Panel — Tutorial de Instalação e Uso (Edição Linux)

Este projeto é um painel leve, moderno e seguro para gerenciar servidores de Counter-Strike 2 (CS2) remotamente através do navegador. Ele utiliza PHP para a interface web e um Agente Python rodando em segundo plano para controlar o processo do servidor, atualizar o jogo via SteamCMD e capturar o consumo de RAM/CPU em tempo real.

---

## 🚀 Requisitos e Caminhos de Instalação

- **Sistema Operacional:** Debian / Ubuntu Linux
- **Servidor Web:** Apache2
- **Banco de Dados:** MariaDB ou MySQL
- **Linguagem Backend:** PHP 8+ e Python 3 (com `psutil` e `flask`)

### Caminhos Padrões Usados no Projeto (Ajuste no `config.py`):
- **Painel Web:** `/var/www/html/PanelCS2_PHP_RCON2-main`
- **Agente Python:** `/var/www/html/PanelCS2_PHP_RCON2-main/app.py`
- **SteamCMD:** `/media/astral/7DFD-F7FB/Counter-Strike2-Server/steamcmd/steamcmd.sh`
- **Servidor CS2:** `/media/astral/7DFD-F7FB/Counter-Strike2-Server/cs2/game`

---

## ⚙️ Configuração do Ambiente Linux

### 1. Dependências do PHP e Servidor Web
Certifique-se de ter o Apache e as extensões PHP essenciais instaladas para a comunicação RCON funcionar e o site rodar corretamente:
```bash
sudo apt update
sudo apt install apache2 php php-mysql php-curl php-mbstring
```

### 2. Agente Python (Monitoramento e Inicialização)
O painel necessita de um script Python rodando em background na porta 5000 para ligar, desligar e atualizar o servidor com segurança, evitando problemas de permissão.

Instale as dependências do Python:
```bash
pip3 install Flask psutil
```

**Criando o Serviço no SystemD (Para iniciar com o Linux):**
Crie um arquivo em `/etc/systemd/system/cs2_panel_agent.service`:
```ini
[Unit]
Description=CS2 Panel Python Agent
After=network.target

[Service]
User=root
WorkingDirectory=/var/www/html/PanelCS2_PHP_RCON2-main
ExecStart=/usr/bin/python3 app.py
Restart=always

[Install]
WantedBy=multi-user.target
```
E ative com:
```bash
sudo systemctl enable cs2_panel_agent
sudo systemctl start cs2_panel_agent
```

### 3. Configuração do Banco de Dados
Edite o arquivo `db_connect.php` e insira suas credenciais locais do MySQL/MariaDB:
```php
$host = 'localhost';
$db   = 'cs2_panel';
$user = 'seu_usuario';
$pass = 'sua_senha';
```
A tabela de contas de usuários será criada automaticamente assim que o painel for acessado pela primeira vez.

---

## ✨ Funcionalidades Principais

- 🔐 **Login e Registro Seguro:** Autenticação via senhas criptografadas. O registro de novos usuários pode ser bloqueado no `db_connect.php` após a criação do administrador.
- 🔒 **Sessão Protegida:** Todo o painel requer login.
- 🎮 **Conexão RCON e Painel Interativo:** Envie comandos RCON facilmente com botões rápidos para Admins, Plugins e Controle de Partida.
- 🖥️ **Console ao Vivo e Status:** Veja a tela preta do servidor no navegador! O painel exibe um log em tempo real do jogo utilizando o modo `-condebug` do CS2, além de mostrar uso de RAM e Processador.
- 🔄 **Atualizador Automático SteamCMD:** Verifica a compilação (BuildID) local versus a da Steam e atualiza o servidor com 1 clique usando uma thread em segundo plano.

---

## 💬 Dicas de Segurança e Uso

📌 **Proteja a RCON:** Troque a senha `rcon_password` dentro do arquivo `config.py` e certifique-se de que não está acessível de fora sem autenticação.
📌 **Portas e Firewall:** O servidor web (porta 80/443) precisa estar aberto, assim como a porta do jogo (padrão UDP/TCP 27015). A porta do Agente Python (5000) deve ser mantida bloqueada no Firewall para acesso externo (ela é usada localmente pelo PHP).

---
*Painel adaptado para ecossistemas Linux de alta performance e monitoramento dedicado.*
