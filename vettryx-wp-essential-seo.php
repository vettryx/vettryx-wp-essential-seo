<?php
/**
 * Plugin Name: VETTRYX WP Essential SEO
 * Plugin URI:  https://github.com/vettryx/vettryx-wp-core
 * Description: Módulo para otimização de SEO On-Page, sitemaps e redirecionamentos. Foco em performance e zero bloatware.
 * Version:     1.0.0
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

// 1.5 Injeta as tags Description e Open Graph no <head>
add_action('wp_head', 'vettryx_seo_inject_meta_tags', 1);
function vettryx_seo_inject_meta_tags() {
    if (is_singular()) {
        global $post;
        $meta_desc = get_post_meta($post->ID, '_vettryx_meta_description', true);
        $meta_title = get_post_meta($post->ID, '_vettryx_meta_title', true);
        $final_title = !empty($meta_title) ? $meta_title : get_the_title();

        echo "\n\n";

        // Injeta a Description
        if (!empty($meta_desc)) {
            echo '<meta name="description" content="' . esc_attr($meta_desc) . '" />' . "\n";
            echo '<meta property="og:description" content="' . esc_attr($meta_desc) . '" />' . "\n";
        }

        // Injeta Open Graph Básico (para links bonitos no WhatsApp/LinkedIn)
        echo '<meta property="og:title" content="' . esc_attr($final_title) . '" />' . "\n";
        echo '<meta property="og:type" content="article" />' . "\n";
        echo '<meta property="og:url" content="' . esc_url(get_permalink()) . '" />' . "\n";
        
        // Puxa a imagem destacada do post, se existir
        if (has_post_thumbnail()) {
            $image_url = get_the_post_thumbnail_url($post->ID, 'large');
            echo '<meta property="og:image" content="' . esc_url($image_url) . '" />' . "\n";
        }
        
        echo "\n\n";
    }
}

/**
 * ==============================================================================
 * 2. GERENCIADOR DE ROTAS E SITEMAP
 * Responsável por limpar o sitemap nativo do WP e monitorar erros 404/301.
 * ==============================================================================
 */

// add_filter('wp_sitemaps_post_types', 'vettryx_seo_filter_sitemap_post_types');
// add_action('template_redirect', 'vettryx_seo_monitor_404_and_redirects');

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