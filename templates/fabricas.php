<?php
// Verifica se já está no ambiente WordPress
if (!function_exists('add_action')) {
    die('Acesso direto não permitido.');
}

// WordPress functions para data/hora
if (!function_exists('get_date_from_gmt')) {
    function get_date_from_gmt($string, $format = '') {
        if (empty($format)) {
            $format = get_option('date_format') . ' ' . get_option('time_format');
        }
        return date_i18n($format, strtotime($string));
    }
}

// Inclui as funções do WordPress necessárias
require_once(ABSPATH . 'wp-admin/includes/template.php');
?>
<div class="wrap">
    <h1><?php _e('Cadastro de Fábricas', 'painel-master'); ?></h1>
    <div style="margin-bottom: 20px;">
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display: inline-block;">
            <input type="hidden" name="action" value="painel_master_limpar_cache">
            <?php wp_nonce_field('painel_master_limpar_cache_action', 'painel_master_limpar_cache_nonce'); ?>
            <button type="submit" class="button button-secondary">
                <span class="dashicons dashicons-update" style="vertical-align: middle;"></span>
                <?php _e('Limpar Cache das Fábricas', 'painel-master'); ?>
            </button>
        </form>
    </div>
    <?php
    // Busca
    $busca = isset($_GET['fab_search']) ? trim(sanitize_text_field($_GET['fab_search'])) : '';
    $fabricas = painel_master_get_fabricas();
    if ($busca !== '') {
        $fabricas = array_filter($fabricas, function($fab) use ($busca) {
            return stripos($fab['nome'], $busca) !== false || stripos($fab['url'], $busca) !== false;
        });
    }
    $total = count($fabricas);
    $por_pagina = 10;
    $pagina = max(1, intval($_GET['fab_page'] ?? 1));
    $total_paginas = max(1, ceil($total / $por_pagina));
    $inicio = ($pagina - 1) * $por_pagina;
    // Preserve original keys so removal by index corresponds to stored option keys
    $fabricas_pagina = array_slice($fabricas, $inicio, $por_pagina, true);
    ?>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" autocomplete="off" style="margin-bottom:32px;">
        <input type="hidden" name="action" value="painel_master_add_fabrica">
        <?php wp_nonce_field('painel_master_fabricas_action', 'painel_master_fabricas_nonce'); ?>
        <table class="form-table">
            <tr>
                <th><?php _e('Nome da Fábrica', 'painel-master'); ?></th>
                <td><input type="text" name="nova_fabrica_nome" required aria-label="<?php esc_attr_e('Nome da Fábrica', 'painel-master'); ?>" autocomplete="organization"></td>
            </tr>
            <tr>
                <th><?php _e('URL da Loja', 'painel-master'); ?></th>
                <td><input type="url" name="nova_fabrica_url" required aria-label="<?php esc_attr_e('URL da Loja', 'painel-master'); ?>" autocomplete="url"></td>
            </tr>
            <tr>
                <th><?php _e('Token de Acesso', 'painel-master'); ?></th>
                <td><input type="text" name="nova_fabrica_token" required aria-label="<?php esc_attr_e('Token de Acesso', 'painel-master'); ?>" minlength="8" autocomplete="off"></td>
            </tr>
        </table>
        <input type="submit" class="button button-primary" value="<?php esc_attr_e('Adicionar Fábrica', 'painel-master'); ?>">
    </form>
    <form method="get" style="margin-bottom:18px;display:flex;gap:12px;align-items:center;" id="painel-master-busca-form">
        <input type="hidden" name="page" value="painel-master-fabricas">
        <input type="search" name="fab_search" value="<?php echo esc_attr($busca); ?>" placeholder="<?php esc_attr_e('Buscar por nome ou URL...', 'painel-master'); ?>" style="min-width:220px;padding:6px 12px;border-radius:6px;border:1px solid #ccc;" id="painel-master-busca-input" autocomplete="off">
        <button class="button" type="submit"><?php _e('Buscar', 'painel-master'); ?></button>
        <?php if ($busca !== ''): ?>
            <a href="<?php echo esc_url(remove_query_arg(['fab_search','fab_page'])); ?>" class="button button-secondary" style="margin-left:8px;"><?php _e('Limpar', 'painel-master'); ?></a>
        <?php endif; ?>
    </form>
    <hr>
    <h2><?php _e('Fábricas Cadastradas', 'painel-master'); ?></h2>
    <div class="painel-master-grid" style="display: grid; gap: 20px; margin-top: 20px;">
        <?php foreach ($fabricas_pagina as $real_i => $fab): 
            $fabrica_id = md5($fab['url'] . ($fab['token'] ?? ''));
            $dados_fabrica = painel_master_get_dados_fabrica($fabrica_id);
        ?>
            <div class="fabrica-card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 15px;">
                    <div>
                        <h3 style="margin: 0; color: #1e1e1e;"><?php echo esc_html($fab['nome']); ?></h3>
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <a href="<?php echo esc_url($fab['url']); ?>" target="_blank" rel="noopener noreferrer" style="color: #2271b1; text-decoration: none;">
                                <?php echo esc_html($fab['url']); ?>
                            </a>
                            <?php if (!empty($dados_fabrica['status'])): ?>
                                <span style="color: <?php echo $dados_fabrica['status'] === 'ativo' ? '#00a32a' : '#d63638'; ?>; font-weight: 500;">
                                    ●&nbsp;<?php echo ucfirst(esc_html($dados_fabrica['status'])); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline;" class="painel-master-remover-form">
                        <input type="hidden" name="action" value="painel_master_remover_fabrica">
                        <?php wp_nonce_field('painel_master_fabricas_action', 'painel_master_fabricas_nonce'); ?>
                        <input type="hidden" name="remover_fabrica" value="<?php echo esc_attr($real_i); ?>">
                        <input type="submit" class="button button-secondary" value="<?php esc_attr_e('Remover', 'painel-master'); ?>" 
                               aria-label="<?php esc_attr_e('Remover fábrica', 'painel-master'); ?>">
                    </form>
                </div>

                <?php if ($dados_fabrica): ?>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                        <!-- Estatísticas Gerais -->
                        <div style="background: #f8f9fa; padding: 15px; border-radius: 6px;">
                            <h4 style="margin: 0 0 10px; color: #2271b1;">Estatísticas Gerais</h4>
                            <?php
                            // Debug para ver a estrutura completa dos dados
                            error_log('Dados da fábrica: ' . print_r($dados_fabrica, true));
                            
                            // Extrai as estatísticas para facilitar o acesso
                            $stats = $dados_fabrica['estatisticas_gerais'] ?? [];
                            ?>
                            <p style="margin: 5px 0;"><strong>Revendedores:</strong> <?php echo number_format(is_array($dados_fabrica['revendedores'] ?? null) ? count($dados_fabrica['revendedores']) : 0, 0, ',', '.'); ?></p>
                            <p style="margin: 5px 0;"><strong>Vendas Do Mês:</strong> R$ <?php echo number_format((float)($stats['total_vendas_mes'] ?? 0), 2, ',', '.'); ?></p>
                            <p style="margin: 5px 0;"><strong>Vendas Geral:</strong> R$ <?php echo number_format((float)($stats['total_vendas_historico'] ?? 0), 2, ',', '.'); ?></p>
                            <p style="margin: 5px 0;"><strong>Pedidos Do Mês:</strong> <?php echo number_format((int)($stats['total_pedidos_mes'] ?? 0), 0, ',', '.'); ?></p>
                            <p style="margin: 5px 0;"><strong>Pedidos Geral:</strong> <?php echo number_format((int)($stats['total_pedidos_historico'] ?? 0), 0, ',', '.'); ?></p>
                            <p style="margin: 5px 0;"><strong>Taxa Conversão:</strong> <?php echo rtrim(rtrim($stats['taxa_conversao'] ?? '0.0%', '%'), '0') . '%'; ?></p>
                            <p style="margin: 5px 0;"><strong>Taxa de Fidelidade:</strong> <?php echo rtrim(rtrim($stats['cliente_fidelidade'] ?? '0.0%', '%'), '0') . '%'; ?></p>
                        </div>

                        <!-- Status dos Pedidos -->
                        <div style="background: #f8f9fa; padding: 15px; border-radius: 6px;">
                            <h4 style="margin: 0 0 10px; color: #2271b1;">Status dos Pedidos</h4>
                            <p style="margin: 5px 0;"><span style="color: #00a32a;">●</span> <strong>Concluídos:</strong> 
                                <?php echo intval($dados_fabrica['estatisticas_gerais']['status_vendas']['concluidos']); ?></p>
                            <p style="margin: 5px 0;"><span style="color: #dba617;">●</span> <strong>Pendentes:</strong> 
                                <?php echo intval($dados_fabrica['estatisticas_gerais']['status_vendas']['pendentes']); ?></p>
                            <p style="margin: 5px 0;"><span style="color: #0073aa;">●</span> <strong>Processando:</strong> 
                                <?php echo intval($dados_fabrica['estatisticas_gerais']['status_vendas']['processando']); ?></p>
                            <p style="margin: 5px 0;"><span style="color: #d63638;">●</span> <strong>Cancelados:</strong> 
                                <?php echo intval($dados_fabrica['estatisticas_gerais']['status_vendas']['cancelados']); ?></p>
                        </div>

                        <!-- Produtos -->
                        <div style="background: #f8f9fa; padding: 15px; border-radius: 6px;">
                            <h4 style="margin: 0 0 10px; color: #2271b1;">Informações de Produtos</h4>
                            <p style="margin: 5px 0;"><strong>Total de Produtos Sincronizados:</strong> <?php echo intval($dados_fabrica['produtos_total']); ?></p>
                        </div>
                    </div>

                    <?php if (!empty($dados_fabrica['mais_vendidos'])): ?>
                    <div style="background: #f8f9fa; padding: 15px; border-radius: 6px; margin-top: 15px;">
                        <h4 style="margin: 0 0 10px; color: #2271b1;">Top 5 Produtos Mais Vendidos</h4>
                        <ul style="margin: 0; padding-left: 20px;">
                            <?php foreach ($dados_fabrica['mais_vendidos'] as $produto): ?>
                                <li style="margin: 5px 0;">
                                    <a href="<?php echo esc_url($produto['url']); ?>" target="_blank" style="color: #2271b1; text-decoration: none;">
                                        <?php echo esc_html($produto['nome']); ?>
                                    </a>
                                    - <strong><?php echo intval($produto['vendas']); ?> vendas</strong>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>

                    <div style="margin-top: 10px; color: #666; font-size: 0.9em;">
                        <?php 
                        global $wpdb;
                        $tabela = $wpdb->prefix . 'painel_master_fabricas_dados';
                        $ultima_atualizacao = $wpdb->get_var($wpdb->prepare(
                            "SELECT ultima_atualizacao FROM $tabela WHERE fabrica_id = %s",
                            $fabrica_id
                        ));
                        if ($ultima_atualizacao) {
                            // Configura o timezone padrão
                            date_default_timezone_set('America/Sao_Paulo');
                            $dt = new DateTime($ultima_atualizacao);
                            echo 'Última atualização: ' . $dt->format('d/m/Y H:i:s');
                        }
                        ?>
                    </div>
                <?php else: ?>
                    <div style="background: #f8f9fa; padding: 15px; border-radius: 6px; color: #666;">
                        Aguardando primeira sincronização de dados...
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php if ($total_paginas > 1): ?>
    <div class="tablenav"><div class="tablenav-pages" style="margin-top:12px;">
        <?php
        $base_url = remove_query_arg(['fab_page']) . ($busca !== '' ? '&fab_search=' . urlencode($busca) : '');
        for ($p = 1; $p <= $total_paginas; $p++): ?>
            <?php if ($p == $pagina): ?>
                <span class="tablenav-page-numbers current" style="background:#4caf50;color:#fff;border-radius:4px;padding:4px 10px;"><?php echo $p; ?></span>
            <?php else: ?>
                <a class="tablenav-page-numbers" style="padding:4px 10px;border-radius:4px;" href="<?php echo esc_url(add_query_arg(['fab_page'=>$p], $base_url)); ?>"><?php echo $p; ?></a>
            <?php endif; ?>
        <?php endfor; ?>
    </div></div>
    <?php endif; ?>
</div>
<?php
// Mensagens de feedback via transient + settings_errors
$messages = get_transient('painel_master_admin_messages');
if (!empty($messages) && is_array($messages)) {
    foreach ($messages as $m) {
        if (function_exists('add_settings_error')) {
            add_settings_error('painel_master', $m['code'] ?? '', $m['message'] ?? '', $m['type'] ?? '');
        }
    }
    delete_transient('painel_master_admin_messages');
}
if (function_exists('settings_errors')) {
    settings_errors('painel_master');
}
?>
<script>
// Confirmação antes de remover uma fábrica
;(function(){
    document.addEventListener('DOMContentLoaded', function(){
        var forms = document.querySelectorAll('.painel-master-remover-form');
        forms.forEach(function(f){
            f.addEventListener('submit', function(e){
                var ok = confirm('Tem certeza que deseja remover esta fábrica? Clique "OK" para Sim ou "Cancelar" para Não.');
                if (!ok) {
                    e.preventDefault();
                }
            });
        });
    });
})();
</script>
