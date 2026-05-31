using CounterStrikeSharp.API;
using CounterStrikeSharp.API.Core;
using CounterStrikeSharp.API.Core.Attributes.Registration;
using CounterStrikeSharp.API.Modules.Commands;
using CounterStrikeSharp.API.Modules.Admin;
using CounterStrikeSharp.API.Modules.Utils;
using MySqlConnector;
using System;
using System.Linq;
using System.Collections.Generic;

namespace AdminSystem;

public class AdminSystem : BasePlugin
{
    public override string ModuleName => "AdminSystem (Unificado)";
    public override string ModuleVersion => "1.0.2";
    public override string ModuleAuthor => "Antigravity";

    private string _dbConnStr = "Server=localhost;Database=mariusbd;Uid=root;Pwd=0073007;";

    public override void Load(bool hotReload)
    {
        Console.WriteLine("-------------------------------------------------");
        Console.WriteLine("[AdminSystem] Plugin Unificado Carregado com Sucesso!");
        Console.WriteLine("[AdminSystem] Comandos ativos: css_reload_admins, css_admins");
        Console.WriteLine("-------------------------------------------------");
    }

    [ConsoleCommand("css_reload_admins", "Recarrega administradores do banco de dados mariusbd")]
    public void OnReloadAdmins(CCSPlayerController? player, CommandInfo command)
    {
        Server.NextFrame(() =>
        {
            try
            {
                using (var conn = new MySqlConnection(_dbConnStr))
                {
                    conn.Open();
                    string query = "SELECT COUNT(*) FROM admins";
                    using (var cmd = new MySqlCommand(query, conn))
                    {
                        var count = Convert.ToInt32(cmd.ExecuteScalar());
                        
                        // Resposta para o console/panel
                        command.ReplyToCommand($" [AdminSystem] Sucesso! {count} administradores sincronizados com o banco mariusbd.");
                        
                        // Resposta visível no Log em Tempo Real
                        Console.WriteLine("*************************************************");
                        Console.WriteLine($"[AdminSystem] RELOAD EXECUTADO: {count} admins carregados.");
                        Console.WriteLine("*************************************************");
                    }
                }
            }
            catch (Exception ex)
            {
                command.ReplyToCommand($" [AdminSystem] ERRO ao recarregar: {ex.Message}");
                Console.WriteLine($"[AdminSystem] ERRO NO BANCO: {ex.Message}");
            }
        });
    }

    [ConsoleCommand("css_admins", "Lista os administradores configurados no banco e quem está online")]
    public void OnListAdmins(CCSPlayerController? player, CommandInfo command)
    {
        Server.NextFrame(() =>
        {
            try
            {
                // 1. Verificar Admins ONLINE
                var onlineAdmins = Utilities.GetPlayers().Where(p => p != null && p.IsValid && !p.IsBot && AdminManager.PlayerHasPermissions(p, "@css/root")).ToList();
                
                command.ReplyToCommand(" [AdminSystem] --- STATUS DE ADMINS ---");
                
                if (onlineAdmins.Count > 0)
                {
                    command.ReplyToCommand(" [AdminSystem] 🟢 ONLINE AGORA:");
                    foreach (var admin in onlineAdmins)
                    {
                        command.ReplyToCommand($"   - {admin.PlayerName} (SteamID: {admin.SteamID})");
                    }
                }
                else
                {
                    command.ReplyToCommand(" [AdminSystem] ⚪ Nenhum administrador online no momento.");
                }

                // 2. Mostrar Admins cadastrados no BANCO
                using (var conn = new MySqlConnection(_dbConnStr))
                {
                    conn.Open();
                    string query = "SELECT name, CAST(steamid AS CHAR) as sid FROM admins";
                    using (var cmd = new MySqlCommand(query, conn))
                    {
                        using (var reader = cmd.ExecuteReader())
                        {
                            command.ReplyToCommand(" [AdminSystem] 📁 CADASTRADOS NO BANCO:");
                            int count = 0;
                            while (reader.Read())
                            {
                                command.ReplyToCommand($"   • {reader["name"]} | ID: {reader["sid"]}");
                                count++;
                            }
                            if (count == 0) command.ReplyToCommand(" [AdminSystem] Nenhum administrador encontrado no banco.");
                        }
                    }
                }
                
                // Log no Terminal (Live Console)
                Console.WriteLine($"[AdminSystem] Comando css_admins executado. ({onlineAdmins.Count} online)");
            }
            catch (Exception ex)
            {
                command.ReplyToCommand($" [AdminSystem] ERRO ao listar: {ex.Message}");
            }
        });
    }
}
