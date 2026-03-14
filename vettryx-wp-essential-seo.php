<?php
/**
 * Plugin Name: VETTRYX WP Essential SEO
 * Plugin URI:  https://github.com/vettryx/vettryx-wp-core
 * Description: Módulo para otimização de SEO On-Page, sitemaps e redirecionamentos. Foco em performance e zero bloatware.
 * Version:     1.3.0
 * Author:      VETTRYX Tech
 * Author URI:  https://vettryx.com.br
 * License:     Proprietária (Uso Comercial Exclusivo)
 * Vettryx Icon: dashicons-search
 */

// Segurança: Impede o acesso direto ao arquivo
if (!defined('ABSPATH')) {
    exit;
}

/**
 * ==============================================================================
 * 1. FUNDAÇÕES DE SEO (ON-PAGE)
 * Responsável por injetar Meta Titles, Descriptions e Open Graph no <head>.
 * ==============================================================================
 */

// 1.1 Cria os campos (Metabox) na tela de edição de Posts e Páginas
add_action('add_meta_boxes', 'vettryx_seo_register_metabox');
function vettryx_seo_register_metabox() {
    $screens = ['post', 'page']; 
    foreach ($screens as $screen) {
        add_meta_box(
            'vettryx_seo_meta_box',              // ID interno
            '🚀 VETTRYX SEO - Otimização On-Page', // Título da caixa
            'vettryx_seo_metabox_html',          // Função que desenha o HTML
            $screen,                             // Onde vai aparecer
            'normal',                            // Posição (abaixo do texto)
            'high'                               // Prioridade
        );
    }
}

// 1.2 Desenha o HTML do formulário do Metabox
function vettryx_seo_metabox_html($post) {
    // Segurança: Cria um Nonce (Token único) para validar a origem dos dados
    wp_nonce_field('vettryx_seo_save_data', 'vettryx_seo_meta_box_nonce');

    // Busca os dados já salvos no banco (se existirem)
    $meta_title = get_post_meta($post->ID, '_vettryx_meta_title', true);
    $meta_desc = get_post_meta($post->ID, '_vettryx_meta_description', true);

    // Interface limpa e direta
    echo '<div style="display:flex; flex-direction:column; gap:12px; margin-top:10px;">';
    
    echo '<div>';
    echo '<label for="vettryx_meta_title" style="font-weight:600; display:block; margin-bottom:5px;">Título SEO (Meta Title):</label>';
    echo '<input type="text" id="vettryx_meta_title" name="vettryx_meta_title" value="' . esc_attr($meta_title) . '" style="width:100%; padding:5px;" placeholder="Deixe em branco para usar o título original do post" />';
    echo '</div>';
    
    echo '<div>';
    echo '<label for="vettryx_meta_description" style="font-weight:600; display:block; margin-bottom:5px;">Descrição SEO (Meta Description):</label>';
    echo '<textarea id="vettryx_meta_description" name="vettryx_meta_description" rows="3" style="width:100%; padding:5px;" placeholder="Resumo chamativo para o Google (recomendado máx 160 caracteres)">' . esc_textarea($meta_desc) . '</textarea>';
    echo '</div>';
    
    echo '</div>';
}

// 1.3 Salva os dados no banco quando o usuário clica em "Atualizar" ou "Publicar"
add_action('save_post', 'vettryx_seo_save_metabox_data');
function vettryx_seo_save_metabox_data($post_id) {
    // Validações de segurança e fluxo
    if (!isset($_POST['vettryx_seo_meta_box_nonce']) || !wp_verify_nonce($_POST['vettryx_seo_meta_box_nonce'], 'vettryx_seo_save_data')) {
        return;
    }
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;

    // Sanitiza e salva o Título
    if (isset($_POST['vettryx_meta_title'])) {
        update_post_meta($post_id, '_vettryx_meta_title', sanitize_text_field($_POST['vettryx_meta_title']));
    }
    // Sanitiza e salva a Descrição
    if (isset($_POST['vettryx_meta_description'])) {
        update_post_meta($post_id, '_vettryx_meta_description', sanitize_textarea_field($_POST['vettryx_meta_description']));
    }
}

// 1.4 Filtra a tag <title> nativa do WordPress
add_filter('pre_get_document_title', 'vettryx_seo_custom_title');
function vettryx_seo_custom_title($title) {
    if (is_singular()) {
        global $post;
        $meta_title = get_post_meta($post->ID, '_vettryx_meta_title', true);
        if (!empty($meta_title)) {
            return $meta_title; // Sobrescreve pelo título VETTRYX
        }
    }
    return $title;
}

// 1.5 Injeta as tags Description e Open Graph no <head> com Lógica de Automação (Fallback)
add_action('wp_head', 'vettryx_seo_inject_meta_tags', 1);
function vettryx_seo_inject_meta_tags() {
    if (is_singular()) {
        global $post;
        
        // 1. AUTOMAÇÃO DO TÍTULO: Pega o customizado, senão usa o padrão do post
        $meta_title = get_post_meta($post->ID, '_vettryx_meta_title', true);
        $final_title = !empty($meta_title) ? $meta_title : get_the_title();

        // 2. AUTOMAÇÃO DA DESCRIÇÃO (Cascata)
        $meta_desc = get_post_meta($post->ID, '_vettryx_meta_description', true);
        
        if (empty($meta_desc)) {
            // Se tiver o "Resumo" nativo do WP preenchido, usa ele
            if (has_excerpt($post->ID)) {
                $meta_desc = wp_strip_all_tags(get_the_excerpt($post->ID));
            } else {
                // Se não, varre o conteúdo da página, limpa os códigos e pega as primeiras 25 palavras
                $content = get_post_field('post_content', $post->ID);
                $content_clean = wp_strip_all_tags(strip_shortcodes($content));
                $meta_desc = wp_trim_words($content_clean, 25, '...');
            }
        }

        echo "\n\n";

        // Imprime as descriptions geradas pela automação
        if (!empty($meta_desc)) {
            echo '<meta name="description" content="' . esc_attr($meta_desc) . '" />' . "\n";
            echo '<meta property="og:description" content="' . esc_attr($meta_desc) . '" />' . "\n";
        }

        // Injeta Open Graph
        echo '<meta property="og:title" content="' . esc_attr($final_title) . '" />' . "\n";
        echo '<meta property="og:type" content="article" />' . "\n";
        echo '<meta property="og:url" content="' . esc_url(get_permalink()) . '" />' . "\n";
        
        // 3. AUTOMAÇÃO DA IMAGEM: Puxa a imagem destacada nativa automaticamente
        if (has_post_thumbnail($post->ID)) {
            $image_url = get_the_post_thumbnail_url($post->ID, 'large');
            echo '<meta property="og:image" content="' . esc_url($image_url) . '" />' . "\n";
        }
        
        echo "\n\n";
    }
}

/**
 * ==============================================================================
 * 2. GERENCIADOR DE ROTAS E SITEMAP (SEO MANAGER)
 * Responsável pela interface no painel, alias do sitemap e filtros dinâmicos.
 * ==============================================================================
 */

// 2.1 Adiciona o submenu "SEO Manager"
add_action('admin_menu', 'vettryx_seo_add_submenu', 99);
function vettryx_seo_add_submenu() {
    add_submenu_page(
        'vettryx-core-modules',
        'SEO Manager - VETTRYX Tech',
        'SEO Manager',
        'manage_options',
        'vettryx-seo-manager',
        'vettryx_seo_manager_html'
    );
}

// 2.2 Desenha a interface do SEO Manager 
function vettryx_seo_manager_html() {
    if (!current_user_can('manage_options')) return;

    // Busca os tipos de post
    $args = array('public' => true, '_builtin' => false); 
    $custom_post_types = get_post_types($args, 'objects');
    $interface_types = array(
        'post' => (object) array('labels' => (object) array('name' => 'Artigos (Posts)')),
        'page' => (object) array('labels' => (object) array('name' => 'Páginas (Pages)')),
    );
    foreach ($custom_post_types as $cpt_slug => $cpt_obj) {
        $interface_types[$cpt_slug] = $cpt_obj;
    }

    // Processa o salvamento do formulário
    if (isset($_POST['vettryx_seo_save_settings']) && check_admin_referer('vettryx_seo_settings_action', 'vettryx_seo_settings_nonce')) {
        
        $included_types = isset($_POST['include_types']) && is_array($_POST['include_types']) ? array_map('sanitize_text_field', $_POST['include_types']) : [];
        $exclude_tags = isset($_POST['exclude_tags']) ? '1' : '0';
        $exclude_categories = isset($_POST['exclude_categories']) ? '1' : '0';

        // Sanitização pesada para a URL do Sitemap (Garante slug amigável e sufixo .xml)
        $custom_url = isset($_POST['sitemap_custom_url']) ? sanitize_text_field($_POST['sitemap_custom_url']) : 'sitemap_index.xml';
        if (empty($custom_url)) $custom_url = 'sitemap_index.xml'; // Fallback
        $custom_url = sanitize_title(str_replace('.xml', '', $custom_url)) . '.xml';

        $config = [
            'included_post_types' => $included_types,
            'exclude_tags' => $exclude_tags,
            'exclude_categories' => $exclude_categories,
            'sitemap_custom_url' => $custom_url // Salva a URL customizada
        ];
        
        update_option('vettryx_seo_sitemap_config', $config);
        echo '<div class="notice notice-success is-dismissible"><p>Configurações de SEO atualizadas com sucesso!</p></div>';
        
        // REQUISITO OBRIGATÓRIO: Limpa as rotas do WP para a nova URL passar a existir
        flush_rewrite_rules();
    }

    // Busca as opções salvas
    $config = get_option('vettryx_seo_sitemap_config', [
        'included_post_types' => ['post', 'page'], 
        'exclude_tags' => '1', 
        'exclude_categories' => '0',
        'sitemap_custom_url' => 'sitemap_index.xml'
    ]);
    
    // Monta o link para o usuário clicar na tela
    $url_sitemap = site_url('/' . $config['sitemap_custom_url']);
    ?>
    <div class="wrap">
        <h1 style="display:flex; align-items:center; gap:10px;">
            <span class="dashicons dashicons-search" style="font-size: 28px; width: 28px; height: 28px;"></span> 
            VETTRYX SEO Manager
        </h1>
        <p>Gerencie as preferências de tráfego orgânico e a indexação do site.</p>
        
        <form method="post" action="" style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04); max-width: 800px; margin-top: 20px;">
            <?php wp_nonce_field('vettryx_seo_settings_action', 'vettryx_seo_settings_nonce'); ?>
            
            <h2 style="margin-top:0; border-bottom: 1px solid #eee; padding-bottom: 10px;">Sitemap XML</h2>
            <p>Acesse seu sitemap atual: <a href="<?php echo esc_url($url_sitemap); ?>" target="_blank"><strong>/<?php echo esc_html($config['sitemap_custom_url']); ?></strong></a></p>
            
            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><label for="sitemap_custom_url">URL do Sitemap Index</label></th>
                    <td>
                        <input type="text" id="sitemap_custom_url" name="sitemap_custom_url" value="<?php echo esc_attr($config['sitemap_custom_url']); ?>" class="regular-text" />
                        <p class="description">Personalize a URL principal do seu sitemap (ex: <code>meu-sitemap.xml</code>). O sistema ajusta automaticamente para o padrão seguro.</p>
                    </td>
                </tr>

                <tr valign="top">
                    <th scope="row">Controle de Tipos de Post</th>
                    <td>
                        <div style="display:flex; flex-direction:column; gap:8px;">
                        <?php foreach ($interface_types as $type_slug => $type_obj) : ?>
                            <label style="display:block;">
                                <input type="checkbox" name="include_types[]" value="<?php echo esc_attr($type_slug); ?>" <?php checked(in_array($type_slug, $config['included_post_types'])); ?> />
                                <?php echo esc_html($type_obj->labels->name); ?>
                            </label>
                        <?php endforeach; ?>
                        </div>
                    </td>
                </tr>

                <tr valign="top">
                    <th scope="row">Controle de Taxonomias</th>
                    <td>
                        <label style="display:block; margin-bottom: 10px;">
                            <input type="checkbox" name="exclude_tags" value="1" <?php checked($config['exclude_tags'], '1'); ?> />
                            Excluir <strong>Tags</strong> do Sitemap
                        </label>
                        <label style="display:block;">
                            <input type="checkbox" name="exclude_categories" value="1" <?php checked($config['exclude_categories'], '1'); ?> />
                            Excluir <strong>Categorias</strong> do Sitemap
                        </label>
                    </td>
                </tr>
            </table>
            
            <hr style="border: 0; border-top: 1px solid #eee; margin: 20px 0;">
            
            <?php submit_button('Salvar Configurações', 'primary', 'vettryx_seo_save_settings', false); ?>
        </form>
    </div>
    <?php
}

// 2.3 Cria o alias DINÂMICO para o sitemap
add_action('init', 'vettryx_seo_sitemap_alias');
function vettryx_seo_sitemap_alias() {
    $config = get_option('vettryx_seo_sitemap_config', []);
    $alias = isset($config['sitemap_custom_url']) && !empty($config['sitemap_custom_url']) ? $config['sitemap_custom_url'] : 'sitemap_index.xml';
    
    // Transforma o texto do usuário em uma regra Regex segura para o WordPress
    $regex = '^' . preg_quote($alias) . '$';
    add_rewrite_rule($regex, 'index.php?sitemap=index', 'top');
}

// 2.4 Filtros Cirúrgicos do Sitemap Nativo 
add_filter('wp_sitemaps_add_provider', 'vettryx_seo_remove_users_sitemap', 10, 2);
function vettryx_seo_remove_users_sitemap($provider, $name) {
    return ('users' === $name) ? false : $provider;
}

add_filter('wp_sitemaps_post_types', 'vettryx_seo_filter_sitemap_post_types');
function vettryx_seo_filter_sitemap_post_types($post_types) {
    $config = get_option('vettryx_seo_sitemap_config', ['included_post_types' => ['post', 'page']]);
    $allowed_types = isset($config['included_post_types']) ? $config['included_post_types'] : ['post', 'page'];
    foreach ($post_types as $post_type => $object) {
        if (!in_array($post_type, $allowed_types)) unset($post_types[$post_type]);
    }
    return $post_types;
}

add_filter('wp_sitemaps_taxonomies', 'vettryx_seo_filter_sitemap_taxonomies');
function vettryx_seo_filter_sitemap_taxonomies($taxonomies) {
    $config = get_option('vettryx_seo_sitemap_config', ['exclude_tags' => '1', 'exclude_categories' => '0']);
    if (isset($config['exclude_tags']) && $config['exclude_tags'] === '1' && isset($taxonomies['post_tag'])) unset($taxonomies['post_tag']); 
    if (isset($config['exclude_categories']) && $config['exclude_categories'] === '1' && isset($taxonomies['category'])) unset($taxonomies['category']); 
    return $taxonomies;
}

// 2.5 Impede o WordPress de forçar o redirecionamento para o wp-sitemap.xml padrão
add_filter('redirect_canonical', 'vettryx_seo_prevent_sitemap_redirect');
function vettryx_seo_prevent_sitemap_redirect($redirect_url) {
    // Se a página atual for qualquer parte do sitemap, bloqueia o redirecionamento
    if (get_query_var('sitemap')) {
        return false; 
    }
    return $redirect_url;
}

/**
 * ==============================================================================
 * 3. GUARDIÃO DE TRÁFEGO (MONITOR 404 E REDIRECIONAMENTOS 301)
 * ==============================================================================
 */

// 3.1 Auto-Updater: Cria e verifica a tabela no banco de dados silenciosamente
function vettryx_seo_create_redirects_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'vettryx_seo_redirects';
    $charset_collate = $wpdb->get_charset_collate();

    // SQL estrito para o dbDelta (sem comentários, espaçamento exato)
    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        url_origem varchar(255) NOT NULL,
        url_destino varchar(255) DEFAULT '' NOT NULL,
        tipo varchar(10) DEFAULT '404' NOT NULL,
        hits int(11) DEFAULT 0 NOT NULL,
        ultimo_acesso datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY url_origem (url_origem)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

// Roda a verificação toda vez que o painel admin for carregado
add_action('admin_init', 'vettryx_seo_check_and_create_table');
function vettryx_seo_check_and_create_table() {
    // Flag de controle de versão do banco de dados
    $db_version = '1.0.0';
    
    // Se a versão instalada for diferente da versão do código, força a recriação/atualização da tabela
    if (get_option('vettryx_seo_redirects_db_version') !== $db_version) {
        vettryx_seo_create_redirects_table();
        update_option('vettryx_seo_redirects_db_version', $db_version);
    }
}

// 3.2 Intercepta o tráfego (O Guardião)
add_action('template_redirect', 'vettryx_seo_traffic_guardian', 1);
function vettryx_seo_traffic_guardian() {
    // Ignora o painel admin
    if (is_admin()) return;

    global $wpdb;
    $table_name = $wpdb->prefix . 'vettryx_seo_redirects';
    
    // Pega a URL exata que o usuário tentou acessar (sem o domínio)
    $requested_url = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $requested_url = untrailingslashit($requested_url); // Remove barra final para padronizar
    
    // 1. Busca no banco se existe uma regra para essa URL
    $rule = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE url_origem = %s", $requested_url));

    // 2. Se a URL não existe (Erro 404 real do WordPress)
    if (is_404()) {
        if ($rule) {
            // Se já tem no banco, apenas atualiza o contador de 'hits'
            $wpdb->query($wpdb->prepare("UPDATE $table_name SET hits = hits + 1, ultimo_acesso = CURRENT_TIMESTAMP WHERE id = %d", $rule->id));
        } else {
            // Se é um 404 novo, registra na tabela para o usuário ver no painel depois
            $wpdb->insert(
                $table_name,
                array(
                    'url_origem' => $requested_url,
                    'tipo' => '404',
                    'hits' => 1
                )
            );
        }
    } 
    // 3. Se a URL tem uma regra de redirecionamento (301) configurada no banco
    elseif ($rule && $rule->tipo === '301' && !empty($rule->url_destino)) {
        // Atualiza o contador de hits do redirecionamento
        $wpdb->query($wpdb->prepare("UPDATE $table_name SET hits = hits + 1, ultimo_acesso = CURRENT_TIMESTAMP WHERE id = %d", $rule->id));
        
        // Executa o redirecionamento permanente
        wp_redirect(home_url($rule->url_destino), 301);
        exit;
    }
}

// 3.3 Adiciona o submenu "Redirect Manager"
add_action('admin_menu', 'vettryx_seo_redirects_submenu', 99);
function vettryx_seo_redirects_submenu() {
    add_submenu_page(
        'vettryx-core-modules',
        'Redirect Manager - VETTRYX Tech',
        'Redirect Manager', // Mantendo seu padrão de nomenclatura
        'manage_options',
        'vettryx-seo-redirects',
        'vettryx_seo_redirects_html'
    );
}

// 3.4 Interface do Redirect Manager (CRUD)
function vettryx_seo_redirects_html() {
    if (!current_user_can('manage_options')) return;
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'vettryx_seo_redirects';

    // Garante que a tabela exista antes de consultar (Evita erro fatal se o plugin não foi reativado)
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        echo '<div class="notice notice-error"><p>Erro: Tabela de redirecionamentos não encontrada. Por favor, desative e ative o plugin VETTRYX WP Essential SEO novamente para criar a base de dados.</p></div>';
        return;
    }

    // Processa as ações do formulário (Salvar Destino, Excluir ou Criar Manual)
    if (isset($_POST['vettryx_redirect_action']) && check_admin_referer('vettryx_redirect_nonce')) {
        
        if ($_POST['vettryx_redirect_action'] === 'save_redirect') {
            $id = intval($_POST['id']);
            $destino = sanitize_text_field($_POST['url_destino']);
            $tipo = !empty($destino) ? '301' : '404'; // Se você preencheu um destino, a rota vira 301 permanente
            
            $wpdb->update($table_name, ['url_destino' => $destino, 'tipo' => $tipo], ['id' => $id]);
            echo '<div class="notice notice-success is-dismissible"><p>Regra atualizada com sucesso!</p></div>';
        } 
        elseif ($_POST['vettryx_redirect_action'] === 'delete_rule') {
            $id = intval($_POST['id']);
            $wpdb->delete($table_name, ['id' => $id]);
            echo '<div class="notice notice-success is-dismissible"><p>Registro apagado da memória.</p></div>';
        }
        elseif ($_POST['vettryx_redirect_action'] === 'add_manual') {
            $origem = untrailingslashit(sanitize_text_field($_POST['url_origem']));
            $destino = sanitize_text_field($_POST['url_destino']);
            
            if (!empty($origem) && !empty($destino)) {
                $wpdb->replace($table_name, [
                    'url_origem' => $origem,
                    'url_destino' => $destino,
                    'tipo' => '301',
                    'hits' => 0
                ]);
                echo '<div class="notice notice-success is-dismissible"><p>Redirecionamento manual criado e ativo!</p></div>';
            }
        }
    }

    // Busca os registros no banco (Ordena pelos links mais acessados e mais recentes)
    $rules = $wpdb->get_results("SELECT * FROM $table_name ORDER BY hits DESC, ultimo_acesso DESC LIMIT 100");
    ?>
    <div class="wrap">
        <h1 style="display:flex; align-items:center; gap:10px;">
            <span class="dashicons dashicons-randomize" style="font-size: 28px; width: 28px; height: 28px;"></span> 
            VETTRYX Redirect Manager
        </h1>
        <p>Monitore erros 404 e crie redirecionamentos 301 para recuperar o tráfego de páginas apagadas.</p>

        <div style="background: #fff; padding: 15px; border: 1px solid #ccd0d4; margin-bottom: 20px; max-width: 800px; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
            <h3 style="margin-top:0;">Adicionar Regra Manual</h3>
            <form method="post" style="display:flex; gap:10px; align-items:flex-end; flex-wrap: wrap;">
                <?php wp_nonce_field('vettryx_redirect_nonce'); ?>
                <input type="hidden" name="vettryx_redirect_action" value="add_manual">
                
                <div style="flex:1; min-width: 250px;">
                    <label style="display:block; font-weight:600; margin-bottom:5px;">De: (URL Antiga / Quebrada)</label>
                    <input type="text" name="url_origem" placeholder="Ex: /quem-somos" required style="width:100%;">
                </div>
                <div style="flex:1; min-width: 250px;">
                    <label style="display:block; font-weight:600; margin-bottom:5px;">Para: (Nova URL)</label>
                    <input type="text" name="url_destino" placeholder="Ex: /sobre-nos" required style="width:100%;">
                </div>
                <div>
                    <button type="submit" class="button button-primary">Criar 301</button>
                </div>
            </form>
        </div>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 80px;">Status</th>
                    <th style="width: 30%;">URL Tentada (Origem)</th>
                    <th style="width: 80px;">Acessos</th>
                    <th style="width: 150px;">Último Registro</th>
                    <th>Destino (Preencha para virar 301)</th>
                    <th style="width: 80px;">Limpar</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rules)) : ?>
                    <tr><td colspan="6" style="padding: 20px; text-align: center; color: #666;">Nenhum erro 404 registrado ou regra de redirecionamento ativa. Seu tráfego está limpo.</td></tr>
                <?php else : ?>
                    <?php foreach ($rules as $rule) : ?>
                        <tr>
                            <td>
                                <?php if ($rule->tipo === '301') : ?>
                                    <span style="background:#46b450; color:#fff; padding:3px 8px; border-radius:3px; font-weight:bold;">301</span>
                                <?php else : ?>
                                    <span style="background:#dc3232; color:#fff; padding:3px 8px; border-radius:3px; font-weight:bold;">404</span>
                                <?php endif; ?>
                            </td>
                            <td><strong><?php echo esc_html($rule->url_origem); ?></strong></td>
                            <td><?php echo esc_html($rule->hits); ?></td>
                            <td><?php echo esc_html(wp_date('d/m/Y H:i', strtotime($rule->ultimo_acesso))); ?></td>
                            <td>
                                <form method="post" style="display:flex; gap:5px;">
                                    <?php wp_nonce_field('vettryx_redirect_nonce'); ?>
                                    <input type="hidden" name="vettryx_redirect_action" value="save_redirect">
                                    <input type="hidden" name="id" value="<?php echo esc_attr($rule->id); ?>">
                                    <input type="text" name="url_destino" value="<?php echo esc_attr($rule->url_destino); ?>" placeholder="/nova-pagina (Deixe vazio para voltar a ser 404)" style="width: 100%; max-width: 250px;">
                                    <button type="submit" class="button">Atualizar</button>
                                </form>
                            </td>
                            <td>
                                <form method="post" onsubmit="return confirm('Apagar este registro da memória?');">
                                    <?php wp_nonce_field('vettryx_redirect_nonce'); ?>
                                    <input type="hidden" name="vettryx_redirect_action" value="delete_rule">
                                    <input type="hidden" name="id" value="<?php echo esc_attr($rule->id); ?>">
                                    <button type="submit" class="button button-link-delete" style="color:#a00;">X</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}

/**
 * ==============================================================================
 * 4. AUTOMAÇÕES (IMAGENS E SCHEMA JSON-LD)
 * Responsável por injetar atributos alt dinâmicos e marcação estruturada.
 * ==============================================================================
 */

// 4.1 Injeta Alt Automático nas Imagens do Conteúdo
add_filter('the_content', 'vettryx_seo_auto_image_alt', 99);
function vettryx_seo_auto_image_alt($content) {
    if (is_singular() && in_the_loop() && is_main_query()) {
        global $post;
        $title = esc_attr(get_the_title($post->ID));
        
        // Magia Negra (Regex) 1: Encontra tags <img> que NÃO possuem o atributo alt e injeta o título
        $content = preg_replace(
            '/<img(?![^>]*\balt=["\'](.*?)["\'])(([^>]*)>)/i', 
            '<img alt="' . $title . '"$3', 
            $content
        );
        
        // Magia Negra (Regex) 2: Encontra tags <img> que têm o alt, mas ele está vazio (alt="") e injeta o título
        $content = preg_replace(
            '/<img([^>]*)\balt=["\']["\']([^>]*)>/i', 
            '<img$1alt="' . $title . '"$2>', 
            $content
        );
    }
    return $content;
}

// 4.2 Gera e Injeta o Schema Markup (JSON-LD) no <head>
add_action('wp_head', 'vettryx_seo_inject_schema_markup', 99);
function vettryx_seo_inject_schema_markup() {
    if (!is_singular()) return;

    global $post;
    $site_name = get_bloginfo('name');
    $site_url = home_url();
    $post_url = get_permalink();
    $post_title = get_the_title();
    
    // Puxa o resumo automatizado que criamos lá na Seção 1 (Fallback em cascata)
    $description = get_post_meta($post->ID, '_vettryx_meta_description', true);
    if (empty($description)) {
        $description = has_excerpt($post->ID) ? wp_strip_all_tags(get_the_excerpt($post->ID)) : wp_trim_words(wp_strip_all_tags(strip_shortcodes($post->post_content)), 25, '...');
    }

    // Inicializa a estrutura do Schema
    $schema = [
        '@context' => 'https://schema.org',
        '@graph' => []
    ];

    // 1. Schema da Organização/Site (Sempre presente para criar autoridade de marca)
    $schema['@graph'][] = [
        '@type' => 'Organization',
        '@id' => $site_url . '#organization',
        'name' => $site_name,
        'url' => $site_url
    ];

    // 2. Schema do Artigo/Página
    $type = is_singular('post') ? 'Article' : 'WebPage'; // Se for post de blog vira Article, se não, WebPage.
    
    $article_schema = [
        '@type' => $type,
        '@id' => $post_url . '#' . strtolower($type),
        'isPartOf' => ['@id' => $site_url . '#website'],
        'headline' => $post_title,
        'description' => $description,
        'url' => $post_url,
        'datePublished' => get_the_date('c', $post->ID),
        'dateModified' => get_the_modified_date('c', $post->ID),
        'publisher' => ['@id' => $site_url . '#organization'],
        'author' => [
            '@type' => 'Person',
            'name' => get_the_author_meta('display_name', $post->post_author)
        ]
    ];

    // Se tiver imagem destacada, avisa o Google para colocar na miniatura da pesquisa
    if (has_post_thumbnail($post->ID)) {
        $image_url = get_the_post_thumbnail_url($post->ID, 'large');
        $article_schema['image'] = [
            '@type' => 'ImageObject',
            'url' => $image_url
        ];
    }

    $schema['@graph'][] = $article_schema;

    // Imprime o JSON minificado e limpo no código-fonte
    echo "\n\n";
    echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>';
    echo "\n\n\n";
}

/**
 * ==============================================================================
 * 4. INDEXAÇÃO INSTANTÂNEA (APIs)
 * Responsável por pingar o Google/Bing ao atualizar conteúdos.
 * ==============================================================================
 */

// add_action('transition_post_status', 'vettryx_seo_instant_indexing', 10, 3);