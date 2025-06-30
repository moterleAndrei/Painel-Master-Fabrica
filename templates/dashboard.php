<div class="wrap">
    <h1><?php _e('Painel Master de Fábricas', 'painel-master'); ?></h1>
    <p><?php _e('Veja abaixo o resumo das fábricas e seus revendedores.', 'painel-master'); ?></p>
    <div style="margin-bottom:20px;display:flex;gap:16px;align-items:center;">
        <label for="painel-master-filtro" style="font-weight:bold;"><?php _e('Ordenar por:', 'painel-master'); ?></label>
        <select id="painel-master-filtro" aria-label="<?php esc_attr_e('Ordenar por', 'painel-master'); ?>">
            <option value="padrao"><?php _e('Padrão', 'painel-master'); ?></option>
            <option value="mais-vendas"><?php _e('Mais vendas', 'painel-master'); ?></option>
            <option value="menos-vendas"><?php _e('Menos vendas', 'painel-master'); ?></option>
        </select>
        <?php
        // Select de fábricas cadastradas
        require_once __DIR__ . '/../includes/helpers.php';
        $fabricas = painel_master_get_fabricas();
        ?>
        <label for="painel-master-fabrica-select" style="font-weight:bold;margin-left:16px;">Fábrica:</label>
        <select id="painel-master-fabrica-select" aria-label="Selecionar fábrica">
            <option value="todas"><?php _e('Todas', 'painel-master'); ?></option>
            <?php foreach ($fabricas as $fab): ?>
                <option value="<?php echo esc_attr($fab['nome']); ?>"><?php echo esc_html($fab['nome']); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php // Bloco de feedback para mensagens de erro/sucesso vindas por GET (ex: ?msg=erro)
    if (isset($_GET['msg'])) {
        $msg = sanitize_text_field($_GET['msg']);
        if ($msg === 'erro') {
            echo '<div class="pm-error error" role="alert" tabindex="0"><p>' . __('Ocorreu um erro ao carregar o painel.', 'painel-master') . '</p></div>';
        } elseif ($msg === 'ok') {
            echo '<div class="pm-success updated" role="status" tabindex="0"><p>' . __('Operação realizada com sucesso!', 'painel-master') . '</p></div>';
        }
    }
    ?>
    <div id="painel-master-notificacoes" aria-live="polite"></div>
    <div id="painel-master-content"><?php _e('Carregando dados...', 'painel-master'); ?></div>
</div>
<?php // O dashboard.js e Chart.js são enfileirados via wp_enqueue_script ?>
