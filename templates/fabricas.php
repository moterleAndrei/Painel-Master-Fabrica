<div class="wrap">
    <h1><?php _e('Cadastro de Fábricas', 'painel-master'); ?></h1>
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
    <table class="widefat">
        <thead><tr><th><?php _e('Nome', 'painel-master'); ?></th><th><?php _e('URL', 'painel-master'); ?></th><th><?php _e('Token', 'painel-master'); ?></th><th><?php _e('Ações', 'painel-master'); ?></th></tr></thead>
        <tbody>
        <?php foreach ($fabricas_pagina as $real_i => $fab): ?>
            <tr>
                <td><?php echo esc_html($fab['nome']); ?></td>
                <td><a href="<?php echo esc_url($fab['url']); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($fab['url']); ?></a></td>
                <td><?php echo esc_html(function_exists('painel_master_mask_token') ? painel_master_mask_token($fab['token'] ?? '') : ($fab['token'] ?? '')); ?></td>
                <td>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline;" class="painel-master-remover-form">
                        <input type="hidden" name="action" value="painel_master_remover_fabrica">
                        <?php wp_nonce_field('painel_master_fabricas_action', 'painel_master_fabricas_nonce'); ?>
                        <input type="hidden" name="remover_fabrica" value="<?php echo esc_attr($real_i); ?>">
                        <input type="submit" class="button button-secondary" value="<?php esc_attr_e('Remover', 'painel-master'); ?>" aria-label="<?php esc_attr_e('Remover fábrica', 'painel-master'); ?>">
                    </form>
                </td>
            </tr>
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
