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
    $fabricas_pagina = array_slice($fabricas, $inicio, $por_pagina);
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
        <?php foreach ($fabricas_pagina as $i => $fab): $real_i = $inicio + $i; ?>
            <tr>
                <td><?php echo esc_html($fab['nome']); ?></td>
                <td><?php echo esc_html($fab['url']); ?></td>
                <td><?php echo esc_html($fab['token']); ?></td>
                <td>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline;">
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
// Mensagens de feedback
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'ok') {
        echo '<div class="pm-success updated"><p>' . __('Fábrica adicionada!', 'painel-master') . '</p></div>';
    } elseif ($_GET['msg'] === 'removida') {
        echo '<div class="pm-success updated"><p>' . __('Fábrica removida!', 'painel-master') . '</p></div>';
    } elseif ($_GET['msg'] === 'campos') {
        echo '<div class="pm-error error"><p>' . __('Preencha todos os campos.', 'painel-master') . '</p></div>';
    } elseif ($_GET['msg'] === 'url') {
        echo '<div class="pm-error error"><p>' . __('URL inválida.', 'painel-master') . '</p></div>';
    } elseif ($_GET['msg'] === 'token') {
        echo '<div class="pm-error error"><p>' . __('O token deve ter pelo menos 8 caracteres.', 'painel-master') . '</p></div>';
    } elseif ($_GET['msg'] === 'duplicada') {
        echo '<div class="pm-error error"><p>' . __('Já existe uma fábrica cadastrada com esta URL.', 'painel-master') . '</p></div>';
    } elseif ($_GET['msg'] === 'erroremover') {
        echo '<div class="pm-error error"><p>' . __('Erro ao remover fábrica.', 'painel-master') . '</p></div>';
    } elseif ($_GET['msg'] === 'conexao') {
        $erro = isset($_GET['erro_conexao']) ? urldecode($_GET['erro_conexao']) : __('Não foi possível conectar à API da fábrica. Verifique a URL e o token.', 'painel-master');
        echo '<div class="pm-error error"><p>' . esc_html($erro) . '</p></div>';
    }
}
?>
