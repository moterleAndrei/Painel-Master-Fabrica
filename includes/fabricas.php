<?php
// Funções para integração com as fábricas/revendedores

// ================= BANCO DE DADOS =================
/**
 * Cria a tabela de dados das fábricas se não existir
 */
function painel_master_criar_tabela_fabricas() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    $tabela = $wpdb->prefix . 'painel_master_fabricas_dados';

    $sql = "CREATE TABLE IF NOT EXISTS $tabela (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        fabrica_id varchar(32) NOT NULL,
        dados longtext NOT NULL,
        ultima_atualizacao datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        UNIQUE KEY fabrica_id (fabrica_id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}
register_activation_hook(__FILE__, 'painel_master_criar_tabela_fabricas');

// Registra o evento cron na ativação do plugin
register_activation_hook(__FILE__, 'painel_master_agendar_atualizacao');
register_deactivation_hook(__FILE__, 'painel_master_desagendar_atualizacao');

function painel_master_agendar_atualizacao() {
    if (!wp_next_scheduled('painel_master_atualizar_fabricas_hook')) {
        wp_schedule_event(time(), 'daily', 'painel_master_atualizar_fabricas_hook');
    }
}

function painel_master_desagendar_atualizacao() {
    $timestamp = wp_next_scheduled('painel_master_atualizar_fabricas_hook');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'painel_master_atualizar_fabricas_hook');
    }
}

// Função que será executada pelo cron
function painel_master_atualizar_todas_fabricas() {
    $fabricas = painel_master_get_fabricas();
    foreach ($fabricas as $fabrica) {
        $fabrica_id = md5($fabrica['url'] . ($fabrica['token'] ?? ''));
        delete_transient('force_update_fabrica_' . $fabrica_id);
        painel_master_buscar_info_fabrica($fabrica); // Força atualização
    }
}
add_action('painel_master_atualizar_fabricas_hook', 'painel_master_atualizar_todas_fabricas');

/**
 * Salva os dados da fábrica no banco
 */
function painel_master_salvar_dados_fabrica($fabrica_id, $dados) {
    global $wpdb;
    $tabela = $wpdb->prefix . 'painel_master_fabricas_dados';
    
    $dados_json = wp_json_encode($dados);
    
    // Define o fuso horário para São Paulo
    date_default_timezone_set('America/Sao_Paulo');
    
    // Cria um objeto DateTime com o timezone correto
    $dt = new DateTime('now', new DateTimeZone('America/Sao_Paulo'));
    
    return $wpdb->replace(
        $tabela,
        array(
            'fabrica_id' => $fabrica_id,
            'dados' => $dados_json,
            'ultima_atualizacao' => $dt->format('Y-m-d H:i:s') // Usa a hora de São Paulo
        ),
        array('%s', '%s', '%s')
    );
}

/**
 * Busca os dados da fábrica do banco
 */
function painel_master_get_dados_fabrica($fabrica_id) {
    global $wpdb;
    $tabela = $wpdb->prefix . 'painel_master_fabricas_dados';
    
    $resultado = $wpdb->get_var($wpdb->prepare(
        "SELECT dados FROM $tabela WHERE fabrica_id = %s",
        $fabrica_id
    ));
    
    if ($resultado === null) {
        return false;
    }
    
    return json_decode($resultado, true);
}

/**
 * Verifica se os dados da fábrica precisam ser atualizados
 */
function painel_master_dados_fabrica_expirados($fabrica_id) {
    global $wpdb;
    $tabela = $wpdb->prefix . 'painel_master_fabricas_dados';
    
    $ultima_atualizacao = $wpdb->get_var($wpdb->prepare(
        "SELECT ultima_atualizacao FROM $tabela WHERE fabrica_id = %s",
        $fabrica_id
    ));
    
    if ($ultima_atualizacao === null) {
        return true;
    }
    
    // Considera expirado se a última atualização foi há mais de 24 horas
    $expirado = strtotime($ultima_atualizacao) < (time() - (24 * 60 * 60));
    
    // Ou se foi forçada uma atualização via transient
    $force_update = get_transient('force_update_fabrica_' . $fabrica_id);
    
    return $expirado || $force_update;
}

// ================= LÓGICA/API =================
/**
 * Busca informações de uma fábrica via REST API protegida por token.
 * Usa cache (transient) para performance e trata erros de conexão/segurança.
 *
 * @param array $fabrica Dados da fábrica (nome, url, token)
 * @return array Dados retornados pela API ou mensagem de erro
 */
function painel_master_buscar_info_fabrica($fabrica) {
    // Valida HTTPS
    if (stripos($fabrica['url'], 'https://') !== 0) {
        return painel_master_erro_padrao(__('URL não segura (HTTPS obrigatório)', 'painel-master'));
    }

    $fabrica_id = md5($fabrica['url'] . ($fabrica['token'] ?? ''));
    
    // Verifica se tem dados no banco e se não estão expirados
    if (!painel_master_dados_fabrica_expirados($fabrica_id)) {
        $dados_banco = painel_master_get_dados_fabrica($fabrica_id);
        if ($dados_banco !== false) {
            return $dados_banco;
        }
    }
    
    // Se não tem dados ou estão expirados, busca da API
    $url = trailingslashit($fabrica['url']) . 'wp-json/sincronizador-wc/v1/master/fabrica-status';
    $args = [
        'headers' => [
            'Authorization' => 'Bearer ' . ($fabrica['token'] ?? ''),
        ],
        'timeout' => 15,
        'sslverify' => true,
    ];
    $response = wp_remote_get($url, $args);
    if (is_wp_error($response)) {
        $erro = $response->get_error_message();
        $ret = painel_master_erro_padrao(__('Erro de conexão: ', 'painel-master') . $erro);
        set_transient($cache_key, $ret, 60);
        return $ret;
    }
    $code = wp_remote_retrieve_response_code($response);
    $body = json_decode(wp_remote_retrieve_body($response), true);
    if ($code !== 200 || !is_array($body)) {
        $ret = painel_master_erro_padrao(__('Resposta inválida da API', 'painel-master'));
        set_transient($cache_key, $ret, 60);
        return $ret;
    }

    // Processa os dados recebidos da API
    $total_vendas_mes = 0;
    $total_pedidos_mes = 0;
    $produtos_vendidos_mes = 0;
    $produtos_ativos = 0;
    $produtos_rascunho = 0;
    $produtos_total = 0;
    $status_vendas = [
        'concluidos' => 0,
        'pendentes' => 0,
        'processando' => 0,
        'cancelados' => 0,
        'reembolsados' => 0
    ];
    $taxa_conversao = 0;

    // Soma as estatísticas de todos os revendedores
    if (!empty($body['revendedores'])) {
        foreach ($body['revendedores'] as $revendedor) {
            if (isset($revendedor['estatisticas_gerais'])) {
                // Vendas e Pedidos
                $total_vendas_mes = floatval($revendedor['estatisticas_gerais']['total_vendas_mes'] ?? 0);
                $total_pedidos_mes = intval($revendedor['estatisticas_gerais']['total_pedidos_mes'] ?? 0);
                $produtos_vendidos_mes = intval($revendedor['estatisticas_gerais']['produtos_vendidos_mes'] ?? 0);

                // Taxa de conversão
                $taxa_conversao = floatval(str_replace(['%', ','], ['', '.'], $revendedor['estatisticas_gerais']['taxa_conversao'] ?? '0'));
                
                // Status das vendas (mantendo os nomes corretos do WooCommerce)
                $status = $revendedor['estatisticas_gerais']['status_vendas'] ?? [];
                $status_vendas['concluidos'] = intval($status['vendas_concluidas'] ?? 0);
                $status_vendas['pendentes'] = intval($status['vendas_pendentes'] ?? 0);
                $status_vendas['processando'] = intval($status['vendas_processando'] ?? 0);
                $status_vendas['cancelados'] = intval($status['vendas_canceladas'] ?? 0);
                $status_vendas['reembolsados'] = intval($status['vendas_reembolsadas'] ?? 0);
                
                // Produtos e estatísticas gerais - tenta encontrar o total de produtos
                if (isset($revendedor['total_produtos'])) {
                    $produtos_total = intval($revendedor['total_produtos']);
                } elseif (isset($revendedor['produtos_ativos'])) {
                    $produtos_total = intval($revendedor['produtos_ativos']);
                } elseif (isset($revendedor['estatisticas_produtos']['ativos'])) {
                    $produtos_total = intval($revendedor['estatisticas_produtos']['ativos']);
                } elseif (isset($revendedor['produtos_sincronizados'])) {
                    $produtos_total = intval($revendedor['produtos_sincronizados']);
                }
                
                // Se encontrou algum valor, podemos parar pois todos têm o mesmo total
                if ($produtos_total > 0) {
                    break;
                }
                // Se já encontramos algum revendedor, podemos parar aqui pois todos têm o mesmo total
                break;
            }
        }

        // Taxa de conversão média (convertendo string "X.X%" para número)
        $taxas = array_map(function($rev) {
            return floatval(str_replace(['%', ','], ['', '.'], $rev['estatisticas_gerais']['taxa_conversao'] ?? '0'));
        }, $body['revendedores']);
        $taxa_conversao = !empty($taxas) ? array_sum($taxas) / count($taxas) : 0;
    }

    // Processa os top 5 produtos mais vendidos do geral
    $todos_produtos = [];
    if (!empty($body['top_5_produtos_geral'])) {
        foreach ($body['top_5_produtos_geral'] as $produto) {
            $key = $produto['sku'];
            // Constrói a URL do produto usando a URL do lojista associado
            $produto_url = '';
            if (!empty($body['revendedores'])) {
                foreach ($body['revendedores'] as $rev) {
                    if ($rev['nome'] === $produto['lojista']) {
                        $produto_url = trailingslashit($rev['url']) . '?p=' . ($produto['id'] ?? '');
                        break;
                    }
                }
            }
            $todos_produtos[$key] = [
                'nome' => strip_tags($produto['nome']),
                'vendas' => intval($produto['quantidade_vendida']),
                'receita' => floatval($produto['receita_total']),
                'url' => $produto_url
            ];
        }
    }

    // Ordena os produtos por vendas e pega os top 5
    uasort($todos_produtos, function($a, $b) {
        return $b['vendas'] <=> $a['vendas'];
    });
    $mais_vendidos = array_slice($todos_produtos, 0, 5);

    $dados_processados = [
        'nome' => $body['fabrica']['nome'] ?? '',
        'url' => $fabrica['url'] ?? '',
        'status' => $body['fabrica']['status'] ?? '',
        'revendedores' => $body['revendedores'] ?? [],
        'estatisticas_gerais' => [
            'total_vendas_mes' => $total_vendas_mes,
            'total_pedidos_mes' => $total_pedidos_mes,
            'total_vendas_historico' => floatval($body['revendedores'][0]['estatisticas_gerais']['total_vendas_historico'] ?? 0),
            'total_pedidos_historico' => intval($body['revendedores'][0]['estatisticas_gerais']['total_pedidos_historico'] ?? 0),
            'produtos_vendidos_mes' => $produtos_vendidos_mes,
            'taxa_conversao' => floatval($taxa_conversao),
            'cliente_fidelidade' => floatval($body['revendedores'][0]['estatisticas_gerais']['cliente_fidelidade'] ?? 0) . '%',
            'status_vendas' => $status_vendas
        ],
        'mais_vendidos' => array_values($mais_vendidos),
        'produtos_total' => intval($body['estatisticas']['total_produtos_sincronizados'] ?? 0)
    ];

    // Salva no banco de dados
    painel_master_salvar_dados_fabrica($fabrica_id, $dados_processados);
    return $dados_processados;
}

/**
 * Retorna array padrão de erro para fábrica
 */
function painel_master_erro_padrao($msg) {
    return [
        'revendedores' => '-',
        'ativos' => '-',
        'inativos' => '-',
        'desligados' => '-',
        'erro' => $msg
    ];
}

// ================= RENDERIZAÇÃO =================
/**
 * Renderiza a lista de produtos (mais vendidos ou mais acessados)
 */
function painel_master_render_produtos($produtos, $titulo) {
    if (empty($produtos)) return '';
    $html = '<div class="pm-produtos"><h4>' . esc_html($titulo) . ':</h4><ul>';
    foreach ($produtos as $p) {
        $html .= '<li>';
        $html .= '<a href="' . esc_url($p['url']) . '" target="_blank">' . esc_html($p['nome']) . '</a>';
        if (isset($p['vendas'])) {
            $html .= ' <span style="color:#888;">(' . intval($p['vendas']) . ' ' . __('vendas', 'painel-master') . ')</span>';
        }
        $html .= '</li>';
    }
    $html .= '</ul></div>';
    return $html;
}

/**
 * Renderiza o status de uma fábrica (ativos, inativos, desligados)
 */
function painel_master_render_status($fab) {
    // Só mostra status se pelo menos um campo for numérico
    if (!is_numeric($fab['ativos'] ?? null) && !is_numeric($fab['inativos'] ?? null) && !is_numeric($fab['desligados'] ?? null)) {
        return '';
    }
    return '<div class="painel-master-status">'
        . '<span class="pm-ativos">🟢 ' . __('Ativos', 'painel-master') . ': ' . esc_html($fab['ativos'] ?? '-') . '</span>'
        . '<span class="pm-inativos">🟡 ' . __('Inativos', 'painel-master') . ': ' . esc_html($fab['inativos'] ?? '-') . '</span>'
        . '<span class="pm-desligados">🔴 ' . __('Desligados', 'painel-master') . ': ' . esc_html($fab['desligados'] ?? '-') . '</span>'
        . '</div>';
}

/**
 * Renderiza um card de fábrica
 */
function painel_master_render_card($fab) {
    $revendedores_count = is_array($fab['revendedores']) ? count($fab['revendedores']) : 0;
    $html = '<div class="painel-master-card" style="padding: 20px; background: #fff; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px;">';
    
    // Cabeçalho da fábrica
    $html .= '<div style="margin: 0 0 15px;">';
    $html .= '<h3 style="margin: 0 0 5px; color: #1e1e1e; font-size: 1.5em;">' . esc_html($fab['nome']) . '</h3>';
    $html .= '<div style="display: flex; align-items: center; gap: 10px;">';
    $html .= '<a href="' . esc_url($fab['url']) . '" target="_blank" rel="noopener noreferrer" style="color: #2271b1; text-decoration: none;">' . esc_html($fab['url']) . '</a>';
    if (isset($fab['status'])) {
        $status_color = $fab['status'] === 'ativo' ? '#00a32a' : '#d63638';
        $html .= '<span style="color: ' . $status_color . '; font-weight: 500;">●&nbsp;' . ucfirst($fab['status']) . '</span>';
    }
    $html .= '</div>';
    $html .= '</div>';
    
    // Grid com informações principais
    $html .= '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 15px; margin-bottom: 20px;">';
    
    // Coluna 1: Informações Gerais
    $html .= '<div style="background: #f8f9fa; padding: 15px; border-radius: 6px;">';
    $html .= '<h4 style="margin: 0 0 10px; color: #2271b1;">Informações Gerais</h4>';
    $html .= '<p style="margin: 5px 0;"><strong>Total de Revendedores:</strong> ' . $revendedores_count . '</p>';
    $html .= '<p style="margin: 5px 0;"><strong>Total de Produtos Sincronizados:</strong> ' . intval($fab['produtos_total'] ?? 0) . '</p>';
    $html .= '</div>';
    
    // Coluna 2: Estatísticas de Vendas
    $html .= '<div style="background: #f8f9fa; padding: 15px; border-radius: 6px;">';
    $html .= '<h4 style="margin: 0 0 10px; color: #2271b1;">Estatísticas de Vendas</h4>';
    $html .= '<p style="margin: 5px 0;"><strong>Vendas do Mês:</strong> R$ ' . number_format($fab['estatisticas_gerais']['total_vendas_mes'] ?? 0, 2, ',', '.') . '</p>';
    $html .= '<p style="margin: 5px 0;"><strong>Pedidos do Mês:</strong> ' . intval($fab['estatisticas_gerais']['total_pedidos_mes'] ?? 0) . '</p>';
    $html .= '<p style="margin: 5px 0;"><strong>Produtos Vendidos:</strong> ' . intval($fab['estatisticas_gerais']['produtos_vendidos_mes'] ?? 0) . '</p>';
    $html .= '<p style="margin: 5px 0;"><strong>Taxa de Conversão:</strong> ' . floatval($fab['estatisticas_gerais']['taxa_conversao'] ?? 0) . '%</p>';
    $html .= '</div>';

    // Coluna 3: Status dos Pedidos
    $html .= '<div style="background: #f8f9fa; padding: 15px; border-radius: 6px;">';
    $html .= '<h4 style="margin: 0 0 10px; color: #2271b1;">Status dos Pedidos</h4>';
    $html .= '<p style="margin: 5px 0;"><span style="color: #00a32a;">●</span> <strong>Concluídos:</strong> ' . intval($fab['estatisticas_gerais']['status_vendas']['concluidos'] ?? 0) . '</p>';
    $html .= '<p style="margin: 5px 0;"><span style="color: #dba617;">●</span> <strong>Pendentes:</strong> ' . intval($fab['estatisticas_gerais']['status_vendas']['pendentes'] ?? 0) . '</p>';
    $html .= '<p style="margin: 5px 0;"><span style="color: #0073aa;">●</span> <strong>Processando:</strong> ' . intval($fab['estatisticas_gerais']['status_vendas']['processando'] ?? 0) . '</p>';
    $html .= '<p style="margin: 5px 0;"><span style="color: #d63638;">●</span> <strong>Cancelados:</strong> ' . intval($fab['estatisticas_gerais']['status_vendas']['cancelados'] ?? 0) . '</p>';
    if (isset($fab['estatisticas_gerais']['status_vendas']['reembolsados']) && $fab['estatisticas_gerais']['status_vendas']['reembolsados'] > 0) {
        $html .= '<p style="margin: 5px 0;"><span style="color: #674ea7;">●</span> <strong>Reembolsados:</strong> ' . intval($fab['estatisticas_gerais']['status_vendas']['reembolsados']) . '</p>';
    }
    $html .= '</div>';
    $html .= '</div>';  // Fim do grid
    
    // Produtos Mais Vendidos
    if (!empty($fab['mais_vendidos'])) {
        $html .= '<div style="background: #f8f9fa; padding: 15px; border-radius: 6px; margin-top: 15px;">';
        $html .= '<h4 style="margin: 0 0 10px; color: #2271b1;">Top 5 Produtos Mais Vendidos</h4>';
        $html .= '<ul style="margin: 0; padding-left: 20px;">';
        foreach ($fab['mais_vendidos'] as $produto) {
            $html .= '<li style="margin: 5px 0;">';
            $html .= '<a href="' . esc_url($produto['url']) . '" target="_blank" style="color: #2271b1; text-decoration: none;">' . 
                    esc_html($produto['nome']) . '</a> - ' .
                    '<strong>' . intval($produto['vendas']) . ' vendas</strong>';
            $html .= '</li>';
        }
        $html .= '</ul>';
        $html .= '</div>';
    }
    
    // Últimos Revendedores
    if (!empty($fab['revendedores'])) {
        $html .= '<div style="background: #f8f9fa; padding: 15px; border-radius: 6px; margin-top: 15px;">';
        $html .= '<h4 style="margin: 0 0 10px; color: #2271b1;">Últimos Revendedores</h4>';
        $html .= '<ul style="margin: 0; padding-left: 20px;">';
        $revendedores = array_slice($fab['revendedores'], 0, 5); // Mostra apenas os 5 primeiros
        foreach ($revendedores as $rev) {
            $html .= '<li style="margin: 5px 0;">';
            $html .= '<strong>' . esc_html($rev['nome']) . '</strong>';
            if (!empty($rev['data_cadastro'])) {
                $html .= ' <span style="color: #666;">(' . esc_html($rev['data_cadastro']) . ')</span>';
            }
            if (isset($rev['vendas'])) {
                $html .= ' - ' . intval($rev['vendas']) . ' vendas';
            }
            $html .= '</li>';
        }
        $html .= '</ul>';
        $html .= '</div>';
    }
    // Exibe o total de produtos sincronizados
    if (isset($fab['produtos_total'])) {
        $html .= '<div class="pm-produtos-info">';
        $html .= '<span>' . __('Total de produtos sincronizados', 'painel-master') . ': <b>' . intval($fab['produtos_total']) . '</b></span>';
        $html .= '</div>';
    }
    $html .= painel_master_render_status($fab);
    // Removidos os produtos pois já estão sendo mostrados acima no card
    // Exibe o último revendedor cadastrado
    if (!empty($fab['ultimo_revendedor'])) {
        $rev = $fab['ultimo_revendedor'];
        $html .= '<div class="pm-ultimo-revendedor"><strong>' . __('Último revendedor cadastrado', 'painel-master') . ':</strong> ';
        $html .= esc_html($rev['nome'] ?? '-') .
            (!empty($rev['data_cadastro']) ? ' <span style="color:#888;">(' . esc_html($rev['data_cadastro']) . ')</span>' : '');
        if (isset($rev['vendas'])) {
            $html .= ' <span style="color:#888;">- ' . intval($rev['vendas']) . ' ' . __('vendas', 'painel-master') . '</span>';
        }
        $html .= '</div>';
    }
    $html .= painel_master_render_produtos($fab['mais_vendidos'] ?? [], __('Mais vendidos', 'painel-master'));
    $html .= painel_master_render_produtos($fab['mais_acessados'] ?? [], __('Mais acessados', 'painel-master'));
    if (!empty($fab['erro'])) {
        $html .= '<div class="pm-erro">' . __('Erro', 'painel-master') . ': ' . esc_html($fab['erro']) . '</div>';
    }
    $html .= '</div>';
    return $html;
}

/**
 * Gera o HTML do dashboard do Painel Master, exibindo cards de fábricas, totais e produtos em destaque.
 * Busca os dados de cada fábrica via REST, soma totais e exibe notificações de erro.
 *
 * @return string HTML do dashboard
 */
function painel_master_gerar_dashboard_html() {
    $fabricas = painel_master_get_fabricas();
    $dados = [];
    $total_revendedores = 0;
    $total_ativos = 0;
    $total_inativos = 0;
    $total_desligados = 0;
    $fabricas_sem_revendedores = [];

    foreach ($fabricas as $fabrica) {
        $info = painel_master_buscar_info_fabrica($fabrica);
        $dados[] = array_merge(['nome' => $fabrica['nome']], $info);
        
        // Verifica revendedores
        if (isset($info['revendedores']) && is_array($info['revendedores'])) {
            $revendedores_count = count($info['revendedores']);
            $total_revendedores += $revendedores_count;
            
            // Conta revendedores ativos/inativos
            $revendedores_ativos = 0;
            $revendedores_inativos = 0;
            $revendedores_desligados = 0;
            
            foreach ($info['revendedores'] as $revendedor) {
                // Um revendedor é considerado ativo se estiver marcado como ativo
                if (isset($revendedor['ativo']) && $revendedor['ativo'] == 1) {
                    $revendedores_ativos++;
                } elseif (isset($revendedor['ativo']) && $revendedor['ativo'] == 0) {
                    $revendedores_desligados++;
                } else {
                    $revendedores_inativos++;
                }
            }
            
            $total_ativos += $revendedores_ativos;
            $total_inativos += $revendedores_inativos;
            $total_desligados += $revendedores_desligados;
            
            // Se não houver revendedores ativos, adiciona à lista de avisos
            if ($revendedores_ativos == 0) {
                $fabricas_sem_revendedores[] = $fabrica['nome'];
            }
        }
    }

    $html = [];
    $html[] = '<link rel="stylesheet" href="' . plugin_dir_url(__DIR__) . 'assets/css/dashboard.css?ver=1.0" type="text/css" media="all">';
    
    // Exibe avisos de fábricas sem revendedores ativos
    if (!empty($fabricas_sem_revendedores)) {
        foreach ($fabricas_sem_revendedores as $nome_fabrica) {
            $html[] = '<div class="notice notice-warning" style="margin: 10px 0;"><p>⚠️ ' . 
                     sprintf(__('A fábrica %s não possui revendedores ativos!', 'painel-master'), 
                     esc_html($nome_fabrica)) . '</p></div>';
        }
    }

    $html[] = '<div class="pm-totais">';
    $html[] = '<div class="pm-total-rev">' . __('Total de revendedores', 'painel-master') . ': ' . intval($total_revendedores) . '</div>';
    $html[] = '<div class="pm-total-ativos">' . __('Ativos', 'painel-master') . ': ' . intval($total_ativos) . '</div>';
    $html[] = '<div class="pm-total-inativos">' . __('Inativos', 'painel-master') . ': ' . intval($total_inativos) . '</div>';
    $html[] = '<div class="pm-total-desligados">' . __('Desligados', 'painel-master') . ': ' . intval($total_desligados) . '</div>';
    $html[] = '</div>';
    $html[] = '<div class="painel-master-cards">';
    foreach ($dados as $fab) {
        $html[] = painel_master_render_card($fab);
    }
    $html[] = '</div>';
    return implode("\n", $html);
}

/**
 * Limpa o cache de todas as fábricas
 */
function painel_master_limpar_cache_fabricas() {
    if (!current_user_can('manage_options')) {
        return;
    }

    if (!isset($_POST['painel_master_limpar_cache_nonce']) || !wp_verify_nonce($_POST['painel_master_limpar_cache_nonce'], 'painel_master_limpar_cache_action')) {
        return;
    }

    global $wpdb;
    $fabricas = painel_master_get_fabricas();
    
    // Limpa a tabela de dados das fábricas
    $tabela = $wpdb->prefix . 'painel_master_fabricas_dados';
    $wpdb->query("TRUNCATE TABLE $tabela");
    
    // Para cada fábrica, marca para forçar atualização
    foreach ($fabricas as $fabrica) {
        $fabrica_id = md5($fabrica['url'] . ($fabrica['token'] ?? ''));
        set_transient('force_update_fabrica_' . $fabrica_id, true, 60);
        painel_master_buscar_info_fabrica($fabrica); // Força busca imediata
    }

    // Adiciona mensagem de sucesso
    $mensagens = get_transient('painel_master_admin_messages') ?: [];
    $mensagens[] = [
        'type' => 'success',
        'message' => __('Cache das fábricas limpo e dados atualizados com sucesso!', 'painel-master')
    ];
    set_transient('painel_master_admin_messages', $mensagens, 30);

    // Redireciona de volta
    wp_safe_redirect(wp_get_referer() ?: admin_url('admin.php?page=painel-master-fabricas'));
    exit;
}
add_action('admin_post_painel_master_limpar_cache', 'painel_master_limpar_cache_fabricas');
