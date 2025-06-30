<?php
/*
Plugin Name: Painel Master de Fábricas
Description: Painel centralizado para controle e acompanhamento de todas as fábricas e revendedores.
Version: 1.1.8
Author: Andrei Moterle
*/

if (!defined('ABSPATH')) exit;

require_once __DIR__ . '/includes/helpers.php';

// ================= ADMIN MENU =================
add_action('admin_menu', function() {
    add_menu_page(
        __('Painel Master', 'painel-master'),
        __('Painel Master', 'painel-master'),
        'manage_options',
        'painel-master',
        'painel_master_dashboard',
        'dashicons-admin-multisite'
    );
    add_submenu_page('painel-master', __('Fábricas', 'painel-master'), __('Fábricas', 'painel-master'), 'manage_options', 'painel-master-fabricas', 'painel_master_fabricas_page');
});

// Enfileira JS e CSS apenas nas páginas do plugin
add_action('admin_enqueue_scripts', function($hook) {
    // Verifica se estamos nas páginas do Painel Master
    if (isset($_GET['page']) && in_array($_GET['page'], ['painel-master', 'painel-master-fabricas'])) {
        wp_enqueue_style('painel-master-dashboard-css', plugins_url('assets/css/dashboard.css', __FILE__), [], '1.0');
        // Chart.js como dependência externa
        wp_enqueue_script('chartjs', 'https://cdn.jsdelivr.net/npm/chart.js', [], null, true);
        wp_enqueue_script('painel-master-dashboard-js', plugins_url('assets/js/dashboard.js', __FILE__), ['jquery', 'chartjs'], '1.1', true);
        // Passa strings traduzidas e ajaxurl/nonce para o JS
        wp_localize_script('painel-master-dashboard-js', 'PainelMasterI18n', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('painel_master_dashboard'),
            'erroCarregar' => __('Erro ao carregar dados do dashboard.', 'painel-master'),
            'atencaoSemRevendedores' => __('A fábrica {nome} não possui revendedores ativos!', 'painel-master'),
            'erroFabrica' => __('Erro na fábrica {nome}: {erro}', 'painel-master'),
            'graficoTitulo' => __('Gráfico: Produto campeão de vendas por fábrica', 'painel-master'),
            'graficoLabel' => __('Vendas do produto campeão', 'painel-master'),
        ]);
        if(isset($_GET['page']) && $_GET['page']==='painel-master-fabricas'){
            wp_enqueue_script('painel-master-fabricas-busca', plugins_url('assets/js/fabricas-busca.js', __FILE__), [], '1.0', true);
        }
    }
});

/**
 * Exibe o dashboard principal
 */
function painel_master_dashboard() {
    include __DIR__ . '/templates/dashboard.php';
}

/**
 * Exibe a tela de fábricas
 */
function painel_master_fabricas_page() {
    include __DIR__ . '/templates/fabricas.php';
}

// ================= AJAX =================
add_action('wp_ajax_painel_master_get_dados', function() {
    require_once __DIR__ . '/includes/fabricas.php';
    $html = painel_master_gerar_dashboard_html();
    wp_send_json_success(['html' => $html]);
});

// ================= HANDLERS ADMIN_POST =================
add_action('admin_post_painel_master_add_fabrica', function() {
    if (!current_user_can('manage_options')) wp_die('Sem permissão');
    check_admin_referer('painel_master_fabricas_action', 'painel_master_fabricas_nonce');
    $nome = sanitize_text_field($_POST['nova_fabrica_nome'] ?? '');
    $url = esc_url_raw($_POST['nova_fabrica_url'] ?? '');
    $token = sanitize_text_field($_POST['nova_fabrica_token'] ?? '');
    $fabricas = painel_master_get_fabricas();
    $msg = '';
    $erro_conexao = '';
    if (empty($nome) || empty($url) || empty($token)) {
        $msg = 'campos';
    } elseif (!filter_var($url, FILTER_VALIDATE_URL)) {
        $msg = 'url';
    } elseif (strlen($token) < 8) {
        $msg = 'token';
    } elseif (array_filter($fabricas, function($f) use ($url) { return $f['url'] === $url; })) {
        $msg = 'duplicada';
    } else {
        // Teste de conexão antes de salvar
        require_once __DIR__ . '/includes/fabricas.php';
        $test = painel_master_buscar_info_fabrica(['nome'=>$nome,'url'=>$url,'token'=>$token]);
        if (!empty($test['erro'])) {
            $msg = 'conexao';
            $erro_conexao = urlencode($test['erro']);
        } else {
            $fabricas[] = [ 'nome' => $nome, 'url' => $url, 'token' => $token ];
            painel_master_salvar_fabricas($fabricas);
            if (function_exists('error_log')) {
                $user = wp_get_current_user();
                $ip = $_SERVER['REMOTE_ADDR'] ?? 'cli';
                error_log('[Painel Master] Fábrica adicionada por ' . $user->user_login . ' (' . $ip . ') em ' . date('Y-m-d H:i:s') . ': ' . $nome);
            }
            $msg = 'ok';
        }
    }
    $url_redirect = admin_url('admin.php?page=painel-master-fabricas');
    $url_redirect = add_query_arg('msg', $msg, $url_redirect);
    if ($erro_conexao) {
        $url_redirect = add_query_arg('erro_conexao', $erro_conexao, $url_redirect);
    }
    wp_redirect($url_redirect);
    exit;
});

add_action('admin_post_painel_master_remover_fabrica', function() {
    if (!current_user_can('manage_options')) wp_die('Sem permissão');
    check_admin_referer('painel_master_fabricas_action', 'painel_master_fabricas_nonce');
    $idx = intval($_POST['remover_fabrica'] ?? -1);
    $fabricas = painel_master_get_fabricas();
    if (isset($fabricas[$idx])) {
        $nome_removida = $fabricas[$idx]['nome'];
        unset($fabricas[$idx]);
        painel_master_salvar_fabricas(array_values($fabricas));
        if (function_exists('error_log')) {
            $user = wp_get_current_user();
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'cli';
            error_log('[Painel Master] Fábrica removida por ' . $user->user_login . ' (' . $ip . ') em ' . date('Y-m-d H:i:s') . ': ' . $nome_removida);
        }
        $msg = 'removida';
    } else {
        $msg = 'erroremover';
    }
    wp_redirect(add_query_arg('msg', $msg, menu_page_url('painel-master-fabricas', false)));
    exit;
});
