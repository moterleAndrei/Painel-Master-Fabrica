<?php
/*
Plugin Name: Painel Master de Fábricas
Description: Painel centralizado para controle e acompanhamento de todas as fábricas e revendedores.
Version: 1.2.1
Author: Andrei Moterle

*/

if (!defined('ABSPATH')) exit;

require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/fabricas.php';

// Registra a função de ativação para criar a tabela
register_activation_hook(__FILE__, 'painel_master_plugin_activate');

function painel_master_plugin_activate() {
    // Cria a tabela de dados das fábricas
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

    // Registra o cron
    if (!wp_next_scheduled('painel_master_atualizar_fabricas_hook')) {
        wp_schedule_event(time(), 'daily', 'painel_master_atualizar_fabricas_hook');
    }
}

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
    // Página do dashboard principal
    if (isset($_GET['page']) && $_GET['page'] === 'painel-master') {
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
    }
    // Página de fábricas
    if (isset($_GET['page']) && $_GET['page'] === 'painel-master-fabricas') {
        wp_enqueue_style('painel-master-dashboard-css', plugins_url('assets/css/dashboard.css', __FILE__), [], '1.0');
        wp_enqueue_script('painel-master-fabricas-busca', plugins_url('assets/js/fabricas-busca.js', __FILE__), [], '1.0', true);
    }
});

// ================= ADMIN MENU =================
if (!function_exists('painel_master_dashboard')) {
    function painel_master_dashboard() {
        include __DIR__ . '/templates/dashboard.php';
    }
}
if (!function_exists('painel_master_fabricas_page')) {
    function painel_master_fabricas_page() {
        include __DIR__ . '/templates/fabricas.php';
    }
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
    $url_raw = trim($_POST['nova_fabrica_url'] ?? '');
    $url = esc_url_raw($url_raw);
    $token = sanitize_text_field($_POST['nova_fabrica_token'] ?? '');
    $fabricas = painel_master_get_fabricas();
    $msg = '';
    $erro_conexao = '';

    if (empty($nome) || empty($url) || empty($token)) {
        $msg = 'campos';
    } elseif (!filter_var($url, FILTER_VALIDATE_URL)) {
        $msg = 'url';
    } elseif (stripos($url, 'https://') !== 0) {
        // Força uso de HTTPS
        $msg = 'url';
    } elseif (strlen($token) < 8) {
        $msg = 'token';
    } else {
        // Usa helper para normalizar URLs antes de checar duplicatas
        if (!function_exists('painel_master_normalizar_url')) {
            require_once __DIR__ . '/includes/helpers.php';
        }
        $normalized_new = painel_master_normalizar_url($url);
        $duplicada = array_filter($fabricas, function($f) use ($normalized_new) {
            $ex = painel_master_normalizar_url($f['url'] ?? '');
            return $ex === $normalized_new;
        });
        if (!empty($duplicada)) {
            $msg = 'duplicada';
        } else {
            // Teste de conexão antes de salvar
            require_once __DIR__ . '/includes/fabricas.php';
            $test = painel_master_buscar_info_fabrica(['nome'=>$nome,'url'=>$url,'token'=>$token]);
            if (!empty($test['erro'])) {
                $msg = 'conexao';
                $erro_conexao = urlencode($test['erro']);
            } else {
                // Armazena URL normalizada
                $url_store = painel_master_normalizar_url($url);
                $fabricas[] = [ 'nome' => $nome, 'url' => $url_store, 'token' => $token ];
                painel_master_salvar_fabricas($fabricas);
                if (function_exists('error_log')) {
                    $user = wp_get_current_user();
                    $ip = $_SERVER['REMOTE_ADDR'] ?? 'cli';
                    error_log('[Painel Master] Fábrica adicionada por ' . $user->user_login . ' (' . $ip . ') em ' . date('Y-m-d H:i:s') . ': ' . $nome);
                }
                $msg = 'ok';
            }
        }
    }
    // Guarda a mensagem para exibir na página do plugin
    $messages = get_transient('painel_master_admin_messages') ?: [];
    if ($msg === 'ok') {
        $messages[] = ['code' => 'ok', 'message' => __('Fábrica adicionada!', 'painel-master'), 'type' => 'updated'];
    } elseif ($msg === 'campos') {
        $messages[] = ['code' => 'campos', 'message' => __('Preencha todos os campos.', 'painel-master'), 'type' => 'error'];
    } elseif ($msg === 'url') {
        $messages[] = ['code' => 'url', 'message' => __('URL inválida.', 'painel-master'), 'type' => 'error'];
    } elseif ($msg === 'token') {
        $messages[] = ['code' => 'token', 'message' => __('O token deve ter pelo menos 8 caracteres.', 'painel-master'), 'type' => 'error'];
    } elseif ($msg === 'duplicada') {
        $messages[] = ['code' => 'duplicada', 'message' => __('Já existe uma fábrica cadastrada com esta URL.', 'painel-master'), 'type' => 'error'];
    } elseif ($msg === 'conexao') {
        $erro = $erro_conexao ? urldecode($erro_conexao) : __('Não foi possível conectar à API da fábrica. Verifique a URL e o token.', 'painel-master');
        $messages[] = ['code' => 'conexao', 'message' => $erro, 'type' => 'error'];
    }
    set_transient('painel_master_admin_messages', $messages, 30);
    wp_safe_redirect(admin_url('admin.php?page=painel-master-fabricas'));
    exit;
});

add_action('admin_post_painel_master_remover_fabrica', function() {
    if (!current_user_can('manage_options')) wp_die('Sem permissão');
    check_admin_referer('painel_master_fabricas_action', 'painel_master_fabricas_nonce');
    $idx_raw = isset($_POST['remover_fabrica']) ? $_POST['remover_fabrica'] : null;
    $fabricas = painel_master_get_fabricas();
    if ($idx_raw !== null) {
        $idx = absint($idx_raw);
    } else {
        $idx = null;
    }
    if ($idx !== null && array_key_exists($idx, $fabricas)) {
        $nome_removida = $fabricas[$idx]['nome'];
        unset($fabricas[$idx]);
        painel_master_salvar_fabricas(array_values($fabricas));
        if (function_exists('error_log')) {
            $user = wp_get_current_user();
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'cli';
            error_log('[Painel Master] Fábrica removida por ' . $user->user_login . ' (' . $ip . ') em ' . date('Y-m-d H:i:s') . ': ' . $nome_removida);
        }
        $messages = get_transient('painel_master_admin_messages') ?: [];
        $messages[] = ['code' => 'removida', 'message' => __('Fábrica removida!', 'painel-master'), 'type' => 'updated'];
        set_transient('painel_master_admin_messages', $messages, 30);
    } else {
        $messages = get_transient('painel_master_admin_messages') ?: [];
        $messages[] = ['code' => 'erroremover', 'message' => __('Erro ao remover fábrica.', 'painel-master'), 'type' => 'error'];
        set_transient('painel_master_admin_messages', $messages, 30);
    }
    $redirect_url = admin_url('admin.php?page=painel-master-fabricas');
    wp_safe_redirect($redirect_url);
    exit;
});
