<?php
// Funções utilitárias para o Painel Master

/**
 * Retorna o array de fábricas cadastradas.
 * @return array
 */
function painel_master_get_fabricas() {
    $fabricas = get_option('painel_master_fabricas', []);
    return is_array($fabricas) ? $fabricas : [];
}

/**
 * Salva o array de fábricas cadastradas.
 * @param array $fabricas
 */
function painel_master_salvar_fabricas($fabricas) {
    update_option('painel_master_fabricas', $fabricas);
    // Log de ação para auditoria
    if (function_exists('error_log')) {
        $user = is_user_logged_in() ? wp_get_current_user()->user_login : 'sistema';
        error_log('[Painel Master] Fábricas atualizadas por ' . $user . ' em ' . date('Y-m-d H:i:s'));
    }
}

/**
 * Normaliza uma URL para comparação (remove barra final e espaços).
 * Não altera o esquema (exceto para comparação mais estável) nem decodifica a parte do caminho.
 * @param string $url
 * @return string
 */
function painel_master_normalizar_url($url) {
    $u = trim($url);
    // Remove query e fragmentos para comparar apenas a base
    if (function_exists('wp_parse_url')) {
        $parts = wp_parse_url($u);
    } else {
        $parts = parse_url($u);
    }
    if ($parts === false || empty($parts['host'])) return rtrim(strtolower($u), '/');
    $scheme = isset($parts['scheme']) ? strtolower($parts['scheme']) : '';
    $host = isset($parts['host']) ? strtolower($parts['host']) : '';
    $path = isset($parts['path']) ? rtrim($parts['path'], '/') : '';
    $normalized = $scheme . '://' . $host . $path;
    return rtrim($normalized, '/');
}

/**
 * Mascara um token sensível para exibição parcial.
 * Exibe os primeiros $visible caracteres e substitui o restante por asteriscos.
 * @param string $token
 * @param int $visible
 * @return string
 */
function painel_master_mask_token($token, $visible = 4) {
    $t = (string) $token;
    $len = strlen($t);
    if ($len <= $visible) return str_repeat('*', $len);
    return substr($t, 0, $visible) . str_repeat('*', max(0, $len - $visible));
}
