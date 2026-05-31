<?php
/**
 * Classe Rcon para comunicação com servidores Source Engine (CS2, CS:GO, etc.)
 * Desenvolvida por Amauri Bueno dos Santos com suporte técnico
 * Última atualização: 2026
 *
 * Esta classe permite autenticação via RCON, envio de comandos e leitura de respostas.
 * Foi refinada para funcionar com servidores CS2 modernos, incluindo suporte a pacotes múltiplos e respostas limpas.
 */

class Rcon
{
    // Configurações básicas
    private $host;
    private $port;
    private $password;
    private $timeout;

    // Estado interno
    private $socket;
    private $authorized = false;
    private $last_response;

    // Constantes do protocolo RCON
    const PACKET_AUTHORIZE = 5;
    const PACKET_COMMAND = 6;

    const SERVERDATA_AUTH = 3;
    const SERVERDATA_AUTH_RESPONSE = 2;
    const SERVERDATA_EXECCOMMAND = 2;
    const SERVERDATA_RESPONSE_VALUE = 0;

    /**
     * Construtor da classe
     * @param string $host IP ou hostname do servidor
     * @param int $port Porta RCON (geralmente igual à porta do jogo)
     * @param string $password Senha RCON definida no servidor
     * @param int $timeout Tempo limite de conexão
     */
    public function __construct($host, $port, $password, $timeout)
    {
        $this->host = $host;
        $this->port = $port;
        $this->password = $password;
        $this->timeout = $timeout;
    }

    /**
     * Retorna a última resposta recebida do servidor
     */
    public function get_response()
    {
        return $this->last_response;
    }

    /**
     * Estabelece conexão com o servidor e realiza autenticação RCON
     */
    public function connect()
    {
        @$this->socket = fsockopen($this->host, $this->port, $errno, $errstr, $this->timeout);

        if (!$this->socket) {
            $this->last_response = $errstr;
            return false;
        }

        stream_set_timeout($this->socket, 3, 0);
        return $this->authorize();
    }

    /**
     * Encerra a conexão com o servidor
     */
    public function disconnect()
    {
        if ($this->socket) {
            fclose($this->socket);
        }
    }

    /**
     * Verifica se a conexão está autorizada
     */
    public function is_connected()
    {
        return $this->authorized;
    }

    /**
     * Envia um comando RCON e retorna a resposta do servidor
     * @param string $command Comando a ser executado
     * @return string|false Resposta do servidor ou false em caso de erro
     */
    public function send_command($command)
    {
        if (!$this->is_connected()) {
            return false;
        }

        // ID único para o comando real e ID secundário para o comando Dummy (Vazio)
        $cmd_id = rand(2000, 8999);
        
        $this->write_packet($cmd_id, self::SERVERDATA_EXECCOMMAND, $command);
        // 🎯 Envia pacote dummy logo em seguida para marcar o fim da transmissão no CS2
        $this->write_packet($cmd_id + 1, self::SERVERDATA_RESPONSE_VALUE, '');

        $response = '';
        
        // Loop de captura contínua
        while ($packet = $this->read_packet()) {
            if ($packet['id'] == -1) {
                break;
            }
            
            // 🎯 Se receber o ID do pacote dummy, o CS2 terminou de enviar a resposta do comando principal
            if ($packet['id'] == $cmd_id + 1) {
                break;
            }
            
            if ($packet['id'] == $cmd_id) {
                $response .= $packet['body'];
            }
        }

        $this->last_response = $response;
        return $response;
    }

    /**
     * Realiza autenticação com o servidor usando a senha RCON
     */
    private function authorize()
    {
        $packet_id = rand(1, 999);
        $this->write_packet($packet_id, self::SERVERDATA_AUTH, $this->password);

        $auth_success = false;
        
        // 🎯 O CS2 costuma responder pacotes vazios antes, aumentamos a tolerância para até 5 leituras
        for ($i = 0; $i < 5; $i++) {
            $packet = $this->read_packet();
            if ($packet['id'] == -1) {
                continue;
            }
            
            if ($packet['type'] == self::SERVERDATA_AUTH_RESPONSE && $packet['id'] == $packet_id) {
                $auth_success = true;
                break;
            }
            
            // Se o servidor retornar ID -1 dentro do pacote descriptografado, a senha está errada
            if ($packet['id'] == -1) {
                break;
            }
        }

        if ($auth_success) {
            $this->authorized = true;
            return true;
        }

        $this->disconnect();
        return false;
    }

    /**
     * Monta e envia um pacote RCON para o servidor
     */
    private function write_packet($packet_id, $packet_type, $packet_body)
    {
        $packet = pack("VV", $packet_id, $packet_type) . $packet_body . "\x00\x00";
        $packet_size = strlen($packet);
        $packet = pack("V", $packet_size) . $packet;
        fwrite($this->socket, $packet);
    }

    /**
     * Lê um pacote RCON da resposta do servidor
     * @return array Pacote com campos: id, type, body
     */
    private function read_packet()
    {
        $size_data = fread($this->socket, 4);
        if (strlen($size_data) < 4) {
            return ['id' => -1, 'type' => -1, 'body' => ''];
        }

        // Desempacota o tamanho esperado
        $unpacked_size = unpack("Vsize", $size_data);
        $size = $unpacked_size['size'];
        
        if ($size < 9) {
            return ['id' => -1, 'type' => -1, 'body' => ''];
        }

        $packet_data = '';
        $bytes_to_read = $size;
        
        // 🎯 Loop de segurança: Garante a leitura completa do tamanho do bloco na rede
        while ($bytes_to_read > 0) {
            $chunk = fread($this->socket, $bytes_to_read);
            if ($chunk === false || strlen($chunk) === 0) {
                break;
            }
            $packet_data .= $chunk;
            $bytes_to_read -= strlen($chunk);
        }

        if (strlen($packet_data) < $size) {
            return ['id' => -1, 'type' => -1, 'body' => ''];
        }

        // 🎯 Aplicação do unpack com a limpeza correta dos caracteres nulos residuais (\x00)
        $packet = unpack("Vid/Vtype/a*body", $packet_data);
        $packet['body'] = rtrim($packet['body'], "\x00");
        
        return $packet;
    }
}
?>
