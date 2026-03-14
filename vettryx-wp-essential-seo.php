<?php
/**
 * Plugin Name: VETTRYX WP Essential SEO
 * Plugin URI:  https://github.com/vettryx/vettryx-wp-core
 * Description: Módulo para otimização de SEO On-Page, sitemaps e redirecionamentos. Foco em performance e zero bloatware.
 * Version:     1.1.0
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

// 2.1 Adiciona o submenu "SEO Manager" abaixo de "VETTRYX Tech"
add_action('admin_menu', 'vettryx_seo_add_submenu', 99);
function vettryx_seo_add_submenu() {
    add_submenu_page(
        'vettryx-core-modules',       // Slug do menu pai
        'SEO Manager - VETTRYX Tech', // Título da página
        'SEO Manager',                // Título do submenu
        'manage_options',             // Permissão
        'vettryx-seo-manager',        // Slug da página
        'vettryx_seo_manager_html'    // Função que desenha a página
    );
}

// 2.2 Desenha a interface do SEO Manager
function vettryx_seo_manager_html() {
    if (!current_user_can('manage_options')) return;

    // Processa o salvamento do formulário
    if (isset($_POST['vettryx_seo_save_settings']) && check_admin_referer('vettryx_seo_settings_action', 'vettryx_seo_settings_nonce')) {
        $config = [
            'exclude_tags' => isset($_POST['exclude_tags']) ? '1' : '0',
            'exclude_categories' => isset($_POST['exclude_categories']) ? '1' : '0',
        ];
        update_option('vettryx_seo_sitemap_config', $config);
        echo '<div class="notice notice-success is-dismissible"><p>Configurações de SEO atualizadas com sucesso!</p></div>';
        
        // Dá um flush nas rotas para garantir que o alias funcione
        flush_rewrite_rules();
    }

    // Busca as opções salvas (por padrão, tags são excluídas para evitar duplicidade)
    $config = get_option('vettryx_seo_sitemap_config', ['exclude_tags' => '1', 'exclude_categories' => '0']);
    $url_sitemap = site_url('/sitemap_index.xml');
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
            <p>Seu sitemap está disponível na URL padrão de mercado: <a href="<?php echo esc_url($url_sitemap); ?>" target="_blank"><strong>/sitemap_index.xml</strong></a></p>
            
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Controle de Taxonomias</th>
                    <td>
                        <label style="display:block; margin-bottom: 10px;">
                            <input type="checkbox" name="exclude_tags" value="1" <?php checked($config['exclude_tags'], '1'); ?> />
                            Excluir <strong>Tags</strong> do Sitemap <span style="color:#666;">(Recomendado para evitar conteúdo duplicado no Google)</span>
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

// 2.3 Cria o alias para sitemap_index.xml funcionar
add_action('init', 'vettryx_seo_sitemap_alias');
function vettryx_seo_sitemap_alias() {
    add_rewrite_rule('^sitemap_index\.xml$', 'index.php?sitemap=index', 'top');
}

// 2.4 Filtros Cirúrgicos do Sitemap Nativo
// Remove Autores
add_filter('wp_sitemaps_add_provider', 'vettryx_seo_remove_users_sitemap', 10, 2);
function vettryx_seo_remove_users_sitemap($provider, $name) {
    return ('users' === $name) ? false : $provider;
}

// Limita a Posts e Pages
add_filter('wp_sitemaps_post_types', 'vettryx_seo_filter_sitemap_post_types');
function vettryx_seo_filter_sitemap_post_types($post_types) {
    $allowed = ['post', 'page']; // Se criar CPTs como "portfolio", adicione aqui
    foreach ($post_types as $post_type => $object) {
        if (!in_array($post_type, $allowed)) unset($post_types[$post_type]);
    }
    return $post_types;
}

// Aplica a lógica dinâmica das Taxonomias baseada na escolha do painel
add_filter('wp_sitemaps_taxonomies', 'vettryx_seo_filter_sitemap_taxonomies');
function vettryx_seo_filter_sitemap_taxonomies($taxonomies) {
    $config = get_option('vettryx_seo_sitemap_config', ['exclude_tags' => '1', 'exclude_categories' => '0']);
    
    if (isset($config['exclude_tags']) && $config['exclude_tags'] === '1' && isset($taxonomies['post_tag'])) {
        unset($taxonomies['post_tag']); 
    }
    if (isset($config['exclude_categories']) && $config['exclude_categories'] === '1' && isset($taxonomies['category'])) {
        unset($taxonomies['category']); 
    }
    return $taxonomies;
}

/**
 * ==============================================================================
 * 3. AUTOMAÇÕES (IMAGENS E SCHEMA)
 * Responsável por injetar atributos alt dinâmicos e JSON-LD.
 * ==============================================================================
 */

// add_filter('the_content', 'vettryx_seo_auto_image_alt');
// add_action('wp_head', 'vettryx_seo_inject_schema_markup', 99);

/**
 * ==============================================================================
 * 4. INDEXAÇÃO INSTANTÂNEA (APIs)
 * Responsável por pingar o Google/Bing ao atualizar conteúdos.
 * ==============================================================================
 */

// add_action('transition_post_status', 'vettryx_seo_instant_indexing', 10, 3);