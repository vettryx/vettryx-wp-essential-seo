<?php
/**
 * Plugin Name: VETTRYX WP Essential SEO
 * Plugin URI:  https://github.com/vettryx/vettryx-wp-core
 * Description: Módulo para otimização de SEO On-Page, sitemaps e redirecionamentos. Foco em performance e zero bloatware.
 * Version:     1.5.0
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

// 2.1 Adiciona os submenus do SEO Manager
add_action('admin_menu', 'vettryx_seo_add_submenu', 99);
function vettryx_seo_add_submenu() {
    // Aba de Sitemap
    add_submenu_page(
        'vettryx-core-modules',
        'SEO Manager - VETTRYX Tech',
        'SEO Manager',
        'manage_options',
        'vettryx-seo-manager',
        'vettryx_seo_manager_html'
    );

    // NOVA ABA: Local SEO (Schema)
    add_submenu_page(
        'vettryx-core-modules',
        'Local SEO (Schema) - VETTRYX Tech',
        'Local SEO (Schema)',
        'manage_options',
        'vettryx-seo-local',
        'vettryx_seo_local_html'
    );
}

// 2.2 Desenha a interface do Local SEO (Cartão de Visitas do Google)
function vettryx_seo_local_html() {
    if (!current_user_can('manage_options')) return;

    // Processa o salvamento do formulário Local SEO
    if (isset($_POST['vettryx_local_seo_action']) && check_admin_referer('vettryx_local_seo_nonce')) {
        if (isset($_POST['local_seo']) && is_array($_POST['local_seo'])) {
            $sanitized_data = array_map('sanitize_text_field', $_POST['local_seo']);
            update_option('vettryx_seo_local_settings', $sanitized_data);
            echo '<div class="notice notice-success is-dismissible"><p>Sucesso! Dados do LocalBusiness salvos e injetados no código fonte.</p></div>';
        }
    }

    // Puxa os dados salvos ou os valores padrão
    $local_seo = get_option('vettryx_seo_local_settings', [
        'type' => 'Organization', 'name' => get_bloginfo('name'), 'phone' => '', 'street' => '', 'city' => '', 'state' => '', 'zip' => ''
    ]);
    ?>
    <div class="wrap">
        <h1 style="display:flex; align-items:center; gap:10px; margin-bottom: 20px;">
            <span class="dashicons dashicons-store" style="font-size: 28px; width: 28px; height: 28px;"></span>
            VETTRYX Local SEO (Schema Markup)
        </h1>
        <p>Configure os dados da empresa para o Google gerar "Rich Snippets" (Resultados Ricos) na pesquisa.</p>

        <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; max-width: 800px; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
            <form method="post" action="">
                <?php wp_nonce_field('vettryx_local_seo_nonce'); ?>
                <input type="hidden" name="vettryx_local_seo_action" value="save">

                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="seo_type">Tipo de Negócio</label></th>
                        <td>
                            <select name="local_seo[type]" id="seo_type" class="regular-text">
                                <option value="Organization" <?php selected($local_seo['type'], 'Organization'); ?>>Organização / Empresa Digital</option>
                                <option value="LocalBusiness" <?php selected($local_seo['type'], 'LocalBusiness'); ?>>Negócio Local (Loja Física, Clínica, etc)</option>
                            </select>
                            <p class="description">Escolha "Negócio Local" se você atende clientes em um endereço físico.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="seo_name">Nome da Empresa</label></th>
                        <td><input type="text" name="local_seo[name]" id="seo_name" value="<?php echo esc_attr($local_seo['name']); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="seo_phone">Telefone / WhatsApp</label></th>
                        <td><input type="text" name="local_seo[phone]" id="seo_phone" value="<?php echo esc_attr($local_seo['phone']); ?>" class="regular-text" placeholder="+55 (31) 99999-9999"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="seo_street">Endereço (Rua, Número)</label></th>
                        <td><input type="text" name="local_seo[street]" id="seo_street" value="<?php echo esc_attr($local_seo['street']); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="seo_city">Cidade</label></th>
                        <td><input type="text" name="local_seo[city]" id="seo_city" value="<?php echo esc_attr($local_seo['city']); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="seo_state">Estado (UF)</label></th>
                        <td><input type="text" name="local_seo[state]" id="seo_state" value="<?php echo esc_attr($local_seo['state']); ?>" class="regular-text" placeholder="Ex: MG"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="seo_zip">CEP</label></th>
                        <td><input type="text" name="local_seo[zip]" id="seo_zip" value="<?php echo esc_attr($local_seo['zip']); ?>" class="regular-text"></td>
                    </tr>
                </table>
                <p class="submit">
                    <button type="submit" class="button button-primary button-large">Atualizar Schema</button>
                </p>
            </form>
        </div>
    </div>
    <?php
}

// 2.3 Desenha a interface do SEO Manager (Sitemap)
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

// 2.4 Cria o alias DINÂMICO para o sitemap
add_action('init', 'vettryx_seo_sitemap_alias');
function vettryx_seo_sitemap_alias() {
    $config = get_option('vettryx_seo_sitemap_config', []);
    $alias = isset($config['sitemap_custom_url']) && !empty($config['sitemap_custom_url']) ? $config['sitemap_custom_url'] : 'sitemap_index.xml';
    
    // Transforma o texto do usuário em uma regra Regex segura para o WordPress
    $regex = '^' . preg_quote($alias) . '$';
    add_rewrite_rule($regex, 'index.php?sitemap=index', 'top');
}

// 2.5 Filtros Cirúrgicos do Sitemap Nativo 
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

// 2.6 Impede o WordPress de forçar o redirecionamento para o wp-sitemap.xml padrão
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
add_action('admin_init', 'vettryx_seo_check_and_create_table');
function vettryx_seo_check_and_create_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'vettryx_seo_redirects';
    $db_version = '1.0.3'; // Incrementado para forçar a verificação
    
    // Se a versão instalada for diferente ou a tabela não existir, força a criação
    if (get_option('vettryx_seo_redirects_db_version') !== $db_version || $wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        $charset_collate = $wpdb->get_charset_collate();

        // CORREÇÃO CRÍTICA: SQL estrito, sem NENHUM comentário, para o dbDelta não falhar
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
        update_option('vettryx_seo_redirects_db_version', $db_version);
    }
}

// 3.2 Intercepta o tráfego (O Guardião)
add_action('template_redirect', 'vettryx_seo_traffic_guardian', 1);
function vettryx_seo_traffic_guardian() {
    if (is_admin()) return;

    global $wpdb;
    $table_name = $wpdb->prefix . 'vettryx_seo_redirects';
    
    // Pega a URL exata que o usuário tentou acessar (sem o domínio e garantindo a barra inicial)
    $requested_url = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $requested_url = untrailingslashit($requested_url); 
    if(empty($requested_url)) return; // Ignora home vazia

    // Evita loop infinito tentando redirecionar a própria URL que está quebrando as vezes no wp-admin
    if(strpos($requested_url, 'wp-content') !== false || strpos($requested_url, 'wp-admin') !== false) return;
    
    // Busca a regra
    $rule = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE url_origem = %s", $requested_url));

    if (is_404()) {
        if ($rule) {
            // Se o destino foi preenchido por você no painel, ele DEVE virar 301
            if (!empty($rule->url_destino) && $rule->tipo === '301') {
                 $wpdb->query($wpdb->prepare("UPDATE $table_name SET hits = hits + 1, ultimo_acesso = CURRENT_TIMESTAMP WHERE id = %d", $rule->id));
                 wp_redirect(home_url($rule->url_destino), 301);
                 exit;
            } else {
                 // É só um 404 sendo acessado novamente
                 $wpdb->query($wpdb->prepare("UPDATE $table_name SET hits = hits + 1, ultimo_acesso = CURRENT_TIMESTAMP WHERE id = %d", $rule->id));
            }
        } else {
            // Novo erro 404 descoberto
            $wpdb->insert(
                $table_name,
                array('url_origem' => $requested_url, 'tipo' => '404', 'hits' => 1)
            );
        }
    } elseif ($rule && $rule->tipo === '301' && !empty($rule->url_destino)) {
        // Rota normal que você mandou redirecionar (mesmo não sendo 404 nativo, a regra força o redirecionamento)
        $wpdb->query($wpdb->prepare("UPDATE $table_name SET hits = hits + 1, ultimo_acesso = CURRENT_TIMESTAMP WHERE id = %d", $rule->id));
        wp_redirect(home_url($rule->url_destino), 301);
        exit;
    }
}

// 3.3 Adiciona o submenu "Redirect Manager"
add_action('admin_menu', 'vettryx_seo_redirects_submenu', 99);
function vettryx_seo_redirects_submenu() {
    add_submenu_page('vettryx-core-modules', 'Redirect Manager - VETTRYX Tech', 'Redirect Manager', 'manage_options', 'vettryx-seo-redirects', 'vettryx_seo_redirects_html');
}

// 3.4 Interface do Redirect Manager (Separada)
function vettryx_seo_redirects_html() {
    if (!current_user_can('manage_options')) return;
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'vettryx_seo_redirects';

    // AVISO: Se mesmo após o F5 a tabela não existir
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        echo '<div class="notice notice-error"><p>Aguarde... Configurando o Banco de Dados. Dê um F5 (Atualizar) nesta página.</p></div>';
        return; // Retorna para não quebrar a página
    }

    // Processa Ações
    if (isset($_POST['vettryx_redirect_action']) && check_admin_referer('vettryx_redirect_nonce')) {
        if ($_POST['vettryx_redirect_action'] === 'save_redirect') {
            $id = intval($_POST['id']);
            $destino = sanitize_text_field($_POST['url_destino']);
            $destino = untrailingslashit($destino); // Limpeza de URL
            
            // CORREÇÃO CRÍTICA: Se preencheu destino, VIRA 301 obrigatoriamente
            $tipo = !empty($destino) ? '301' : '404'; 
            
            $wpdb->update($table_name, ['url_destino' => $destino, 'tipo' => $tipo], ['id' => $id]);
            echo '<div class="notice notice-success is-dismissible"><p>Sucesso! A regra foi alterada.</p></div>';
        } 
        elseif ($_POST['vettryx_redirect_action'] === 'delete_rule') {
            $id = intval($_POST['id']);
            $wpdb->delete($table_name, ['id' => $id]);
            echo '<div class="notice notice-info is-dismissible"><p>Registro apagado. Se acessarem de novo, será um novo Erro 404.</p></div>';
        }
        elseif ($_POST['vettryx_redirect_action'] === 'add_manual') {
            $origem = untrailingslashit(sanitize_text_field($_POST['url_origem']));
            $destino = untrailingslashit(sanitize_text_field($_POST['url_destino']));
            
            if (!empty($origem) && !empty($destino) && $origem !== $destino) {
                // Se preencheu os dois campos e são diferentes, cria 301
                $wpdb->replace($table_name, [
                    'url_origem' => $origem,
                    'url_destino' => $destino,
                    'tipo' => '301',
                    'hits' => 0
                ]);
                echo '<div class="notice notice-success is-dismissible"><p>Redirecionamento manual ativado!</p></div>';
            } else {
                 echo '<div class="notice notice-error is-dismissible"><p>Erro: Origem e Destino devem ser diferentes e preenchidos.</p></div>';
            }
        }
    }

    // BUSCAS SEPARADAS PARA A INTERFACE
    $erros_404 = $wpdb->get_results("SELECT * FROM $table_name WHERE tipo = '404' ORDER BY ultimo_acesso DESC LIMIT 50");
    $regras_301 = $wpdb->get_results("SELECT * FROM $table_name WHERE tipo = '301' ORDER BY ultimo_acesso DESC LIMIT 50");
    ?>
    
    <div class="wrap">
        <h1 style="display:flex; align-items:center; gap:10px; margin-bottom: 20px;">
            <span class="dashicons dashicons-randomize" style="font-size: 28px; width: 28px; height: 28px;"></span> 
            VETTRYX Redirect Manager
        </h1>

        <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; margin-bottom: 30px; max-width: 800px; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
            <h3 style="margin-top:0; color: #0073aa; border-bottom: 1px solid #eee; padding-bottom: 10px;">Adicionar Regra 301 (Manual)</h3>
            <p style="color: #666; font-size: 13px;">Crie um redirecionamento forçado de uma página que mudou de link ou que você quer desviar o tráfego.</p>
            <form method="post" style="display:flex; gap:15px; align-items:flex-end; flex-wrap: wrap;">
                <?php wp_nonce_field('vettryx_redirect_nonce'); ?>
                <input type="hidden" name="vettryx_redirect_action" value="add_manual">
                
                <div style="flex:1; min-width: 250px;">
                    <label style="display:block; font-weight:600; margin-bottom:5px;">De: (URL Antiga / Incorreta)</label>
                    <input type="text" name="url_origem" placeholder="Ex: /quem-somos" required style="width:100%;">
                </div>
                <div style="flex:1; min-width: 250px;">
                    <label style="display:block; font-weight:600; margin-bottom:5px;">Para: (Nova URL de Destino)</label>
                    <input type="text" name="url_destino" placeholder="Ex: /sobre-nos" required style="width:100%;">
                </div>
                <div>
                    <button type="submit" class="button button-primary">Ativar Redirecionamento</button>
                </div>
            </form>
        </div>

        <h2 style="margin-bottom: 10px;">Regras Ativas (Interceptando Tráfego)</h2>
        <table class="wp-list-table widefat fixed striped" style="margin-bottom: 30px;">
            <thead>
                <tr>
                    <th style="width: 80px;">Status</th>
                    <th style="width: 30%;">Usuário acessa...</th>
                    <th>É jogado para...</th>
                    <th style="width: 100px;">Interceptações</th>
                    <th style="width: 150px;">Última vez</th>
                    <th style="width: 80px;">Remover</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($regras_301)) : ?>
                    <tr><td colspan="6" style="padding: 15px; text-align: center; color: #666;">Nenhuma regra de redirecionamento salva.</td></tr>
                <?php else : ?>
                    <?php foreach ($regras_301 as $rule) : ?>
                        <tr>
                            <td><span style="background:#46b450; color:#fff; padding:4px 8px; border-radius:3px; font-weight:bold; font-size:12px;">301</span></td>
                            <td><strong><?php echo esc_html($rule->url_origem); ?></strong></td>
                            <td>
                                <form method="post" style="display:flex; gap:5px;">
                                    <?php wp_nonce_field('vettryx_redirect_nonce'); ?>
                                    <input type="hidden" name="vettryx_redirect_action" value="save_redirect">
                                    <input type="hidden" name="id" value="<?php echo esc_attr($rule->id); ?>">
                                    <input type="text" name="url_destino" value="<?php echo esc_attr($rule->url_destino); ?>" style="width: 100%;" required>
                                    <button type="submit" class="button">Salvar</button>
                                </form>
                            </td>
                            <td><?php echo esc_html($rule->hits); ?></td>
                            <td><span style="font-size:12px; color:#666;"><?php echo esc_html(wp_date('d/m/Y H:i', strtotime($rule->ultimo_acesso))); ?></span></td>
                            <td>
                                <form method="post" onsubmit="return confirm('Deseja excluir esta regra permanentemente?');">
                                    <?php wp_nonce_field('vettryx_redirect_nonce'); ?>
                                    <input type="hidden" name="vettryx_redirect_action" value="delete_rule">
                                    <input type="hidden" name="id" value="<?php echo esc_attr($rule->id); ?>">
                                    <button type="submit" class="button button-link-delete" style="color:#a00;">Excluir</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <hr style="border: 0; border-top: 2px solid #ccc; margin: 40px 0;">

        <h2 style="margin-bottom: 10px;">Monitor de Erros (Páginas Não Encontradas)</h2>
        <p style="color: #666; font-size: 13px; margin-top: -5px;">Preencha o campo "Criar Destino" para transformar um erro em um redirecionamento permanente (301).</p>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 80px;">Erro</th>
                    <th style="width: 30%;">Alguém tentou acessar...</th>
                    <th style="width: 100px;">Vezes</th>
                    <th style="width: 150px;">Última vez</th>
                    <th>Criar Destino (Transformar em 301)</th>
                    <th style="width: 80px;">Ignorar</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($erros_404)) : ?>
                    <tr><td colspan="6" style="padding: 15px; text-align: center; color: #666;">Seu site está limpo. Nenhum erro 404 registrado.</td></tr>
                <?php else : ?>
                    <?php foreach ($erros_404 as $rule) : ?>
                        <tr>
                            <td><span style="background:#dc3232; color:#fff; padding:4px 8px; border-radius:3px; font-weight:bold; font-size:12px;">404</span></td>
                            <td><strong style="color: #cf2e2e;"><?php echo esc_html($rule->url_origem); ?></strong></td>
                            <td><?php echo esc_html($rule->hits); ?></td>
                            <td><span style="font-size:12px; color:#666;"><?php echo esc_html(wp_date('d/m/Y H:i', strtotime($rule->ultimo_acesso))); ?></span></td>
                            <td>
                                <form method="post" style="display:flex; gap:5px;">
                                    <?php wp_nonce_field('vettryx_redirect_nonce'); ?>
                                    <input type="hidden" name="vettryx_redirect_action" value="save_redirect">
                                    <input type="hidden" name="id" value="<?php echo esc_attr($rule->id); ?>">
                                    <input type="text" name="url_destino" placeholder="/pagina-correta" style="width: 100%;">
                                    <button type="submit" class="button button-primary">Mudar para 301</button>
                                </form>
                            </td>
                            <td>
                                <form method="post" onsubmit="return confirm('Deseja limpar este erro do log?');">
                                    <?php wp_nonce_field('vettryx_redirect_nonce'); ?>
                                    <input type="hidden" name="vettryx_redirect_action" value="delete_rule">
                                    <input type="hidden" name="id" value="<?php echo esc_attr($rule->id); ?>">
                                    <button type="submit" class="button" style="color:#666;">Ignorar</button>
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
        // Pega o título do post e garante que ele seja seguro para entrar em um atributo HTML
        $title = esc_attr(get_the_title($post->ID));
        
        // 1. Substitui atributos alt vazios explícitos: alt="" ou alt=''
        // A Regex procura exatamente por alt="" ou alt='' dentro de qualquer tag img
        $content = preg_replace('/(<img[^>]*)\balt=(["\'])\2([^>]*>)/i', '$1alt="' . $title . '"$3', $content);
        
        // 2. Injeta o alt se a tag <img> não tiver o atributo alt de forma alguma
        // Esta Regex é muito mais permissiva com espaços e outros atributos do Elementor/LiteSpeed
        $content = preg_replace_callback(
            '/<img([^>]+)>/i',
            function($matches) use ($title) {
                $img_tag = $matches[0];
                // Só injeta se a palavra "alt=" NÃO existir dentro da string capturada da imagem
                if (stripos($img_tag, 'alt=') === false) {
                    return str_replace('<img ', '<img alt="' . $title . '" ', $img_tag);
                }
                return $img_tag;
            },
            $content
        );
    }
    return $content;
}

// 4.2 Gera e Injeta o Schema Markup (JSON-LD) no <head> COM SUPORTE A LOCAL BUSINESS
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

    // NOVA LÓGICA: Busca os dados do Local SEO do banco de dados (que preenchemos na tela nova)
    $local_seo = get_option('vettryx_seo_local_settings', []);
    $org_type = !empty($local_seo['type']) ? $local_seo['type'] : 'Organization';
    $org_name = !empty($local_seo['name']) ? $local_seo['name'] : $site_name;

    // Inicializa a estrutura do Schema
    $schema = [
        '@context' => 'https://schema.org',
        '@graph' => []
    ];

    // 1. Schema da Organização/Site (Dinâmico para Empresa ou Negócio Local)
    $org_schema = [
        '@type' => $org_type,
        '@id' => $site_url . '#' . strtolower($org_type),
        'name' => $org_name,
        'url' => $site_url
    ];

    // Adiciona o telefone se existir
    if (!empty($local_seo['phone'])) {
        $org_schema['telephone'] = $local_seo['phone'];
    }

    // Adiciona o endereço (PostalAddress) se pelo menos a rua ou cidade existirem
    if (!empty($local_seo['street']) || !empty($local_seo['city'])) {
        $org_schema['address'] = [
            '@type' => 'PostalAddress',
            'streetAddress' => isset($local_seo['street']) ? $local_seo['street'] : '',
            'addressLocality' => isset($local_seo['city']) ? $local_seo['city'] : '',
            'addressRegion' => isset($local_seo['state']) ? $local_seo['state'] : '',
            'postalCode' => isset($local_seo['zip']) ? $local_seo['zip'] : '',
            'addressCountry' => 'BR'
        ];
    }
    
    // Insere o schema da empresa no JSON final
    $schema['@graph'][] = $org_schema;

    // 2. Schema do Artigo/Página
    $type = is_singular('post') ? 'Article' : 'WebPage'; 
    
    $article_schema = [
        '@type' => $type,
        '@id' => $post_url . '#' . strtolower($type),
        'isPartOf' => ['@id' => $site_url . '#website'],
        'headline' => $post_title,
        'description' => $description,
        'url' => $post_url,
        'datePublished' => get_the_date('c', $post->ID),
        'dateModified' => get_the_modified_date('c', $post->ID),
        'publisher' => ['@id' => $site_url . '#' . strtolower($org_type)], // Associa quem publicou com a entidade correta
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
 * 5. INDEXAÇÃO INSTANTÂNEA (API PUSH)
 * Responsável por notificar motores de busca ao atualizar conteúdos.
 * ==============================================================================
 */

add_action('transition_post_status', 'vettryx_seo_instant_indexing', 10, 3);
function vettryx_seo_instant_indexing($new_status, $old_status, $post) {
    // 1. Só dispara se o post estiver sendo publicado ou atualizado (deve estar público)
    if ($new_status !== 'publish') {
        return;
    }

    // 2. Só dispara para os tipos de post que o usuário escolheu indexar (Whitelist do Sitemap)
    $config = get_option('vettryx_seo_sitemap_config', ['included_post_types' => ['post', 'page']]);
    $allowed_types = isset($config['included_post_types']) ? $config['included_post_types'] : ['post', 'page'];
    
    if (!in_array($post->post_type, $allowed_types)) {
        return;
    }

    // 3. Monta a URL que precisa ser notificada
    $post_url = get_permalink($post->ID);

    // 4. API PUSH: Dispara o Ping-O-Matic (Padrão e Bing) silenciosamente
    $ping_urls = [
        'http://rpc.pingomatic.com/',
        'http://ping.feedburner.com/',
    ];

    // O WP HTTP API é assíncrono por padrão se o timeout for curto
    foreach ($ping_urls as $api_url) {
        wp_remote_post($api_url, [
            'timeout' => 2, 
            'blocking' => false, 
            'body' => [
                'title' => get_bloginfo('name'),
                'url' => home_url(),
                'changesURL' => $post_url
            ]
        ]);
    }
}
