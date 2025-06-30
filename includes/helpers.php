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
