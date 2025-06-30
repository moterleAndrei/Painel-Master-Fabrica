<?php
// Funções para integração com as fábricas/revendedores

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
    $cache_key = 'painel_master_fabrica_' . md5($fabrica['url'] . ($fabrica['token'] ?? ''));
    $cached = get_transient($cache_key);
    if ($cached !== false) {
        return $cached;
    }
    $url = trailingslashit($fabrica['url']) . 'wp-json/fabrica/v1/status';
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
    // Corrige campo 'vendas' para 'valor' em mais_vendidos e mais_acessados
    foreach (['mais_vendidos', 'mais_acessados'] as $key) {
        if (isset($body[$key]) && is_array($body[$key])) {
            foreach ($body[$key] as $i => $prod) {
                if (isset($prod['vendas'])) {
                    $body[$key][$i]['valor'] = $prod['vendas'];
                }
            }
        }
    }
    set_transient($cache_key, $body, 300);
    return $body;
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
    $html = '<div class="painel-master-card">';
    $html .= '<h3>' . esc_html($fab['nome']) . '</h3>';
    $html .= '<div><strong>' . __('Revendedores', 'painel-master') . ':</strong> ' . esc_html($fab['revendedores'] ?? '-') . '</div>';
    // Exibe produtos ativos, rascunho e total se existirem
    if (isset($fab['produtos_ativos']) || isset($fab['produtos_rascunho']) || isset($fab['produtos_total'])) {
        $html .= '<div class="pm-produtos-info">';
        if (isset($fab['produtos_ativos'])) {
            $html .= '<span style="margin-right:12px;">' . __('Produtos ativos', 'painel-master') . ': <b>' . intval($fab['produtos_ativos']) . '</b></span>';
        }
        if (isset($fab['produtos_rascunho'])) {
            $html .= '<span style="margin-right:12px;">' . __('Rascunhos', 'painel-master') . ': <b>' . intval($fab['produtos_rascunho']) . '</b></span>';
        }
        if (isset($fab['produtos_total'])) {
            $html .= '<span>' . __('Total de produtos', 'painel-master') . ': <b>' . intval($fab['produtos_total']) . '</b></span>';
        }
        $html .= '</div>';
    }
    $html .= painel_master_render_status($fab);
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
    foreach ($fabricas as $fabrica) {
        $info = painel_master_buscar_info_fabrica($fabrica);
        $dados[] = array_merge(['nome' => $fabrica['nome']], $info);
        if (isset($info['revendedores']) && is_numeric($info['revendedores'])) {
            $total_revendedores += intval($info['revendedores']);
        }
        if (isset($info['ativos']) && is_numeric($info['ativos'])) {
            $total_ativos += intval($info['ativos']);
        }
        if (isset($info['inativos']) && is_numeric($info['inativos'])) {
            $total_inativos += intval($info['inativos']);
        }
        if (isset($info['desligados']) && is_numeric($info['desligados'])) {
            $total_desligados += intval($info['desligados']);
        }
    }
    $html = [];
    $html[] = '<link rel="stylesheet" href="' . plugin_dir_url(__DIR__) . 'assets/css/dashboard.min.css?ver=1.0" type="text/css" media="all">';
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
