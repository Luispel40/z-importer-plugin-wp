<?php

/**
 * Plugin Name: z-importer
 * Description: Importa um arquivo ZIP com HTML/CSS/JS e cria uma página no WordPress que exibe esse conteúdo diretamente.
 * Version: 1.0
 * Author: alpha web
 */

// Registrar template customizado
add_filter('theme_page_templates', function ($templates) {
    $templates['html-zip-template.php'] = 'HTML Zip Template';
    return $templates;
});

add_filter('template_include', function ($template) {
    if (is_page()) {
        $custom = get_page_template_slug(get_queried_object_id());
        if ($custom === 'html-zip-template.php') {
            $plugin_template = plugin_dir_path(__FILE__) . 'templates/html-zip-template.php';
            if (file_exists($plugin_template)) return $plugin_template;
        }
    }
    return $template;
});

add_action('admin_menu', function () {
    // Menu principal (apenas container)
    add_menu_page(
        'alpha web',
        'alpha web',
        'manage_options',
        'alpha_web_menu',
        function () {
            // Redirecionar ao clicar no primeiro item
            wp_redirect('https://designerluiscoms.com');
            exit;
        },
        'dashicons-admin-site',
        20
    );

    // Submenu: z-importer
    add_submenu_page(
        'alpha_web_menu',
        'z-importer',
        'z-importer',
        'manage_options',
        'html_zip_importer',
        'html_zip_importer_page'
    );
});


// Carregar CSS e JS da interface apenas na página do plugin
add_action('admin_enqueue_scripts', 'z_importer_admin_assets');
function z_importer_admin_assets($hook) {
    // Checamos pelo parâmetro page (slug do submenu)
    $page = isset($_GET['page']) ? $_GET['page'] : '';
    if ($page !== 'html_zip_importer') return;

    // Força https caso site esteja em https (evita mixed content)
    $plugin_url = plugin_dir_url(__FILE__);
    $plugin_url = set_url_scheme($plugin_url, 'https');

    wp_enqueue_style(
        'z-importer-admin',
        $plugin_url . 'assets/admin-style.css',
        [],
        '1.0.0'
    );

    wp_enqueue_script(
        'z-importer-admin',
        $plugin_url . 'assets/admin-script.js',
        ['jquery'],
        '1.0.0',
        true
    );
}

function html_zip_importer_page()
{
?>
    <div class="wrap html-zip-importer">
        <img src="<?php echo plugin_dir_url(__FILE__) . 'img/logo.webp'; ?>" alt="z-importer logo" style="max-width:160px;margin-bottom:16px;" />
        <div class="tabs">
            <button class="tab-button active" data-tab="importar">Importar</button>
            <button class="tab-button" data-tab="configuracoes">Configurações</button>
        </div>
        <div class="tab-content active" id="importar">
            <?php process_html_zip_upload(); ?>
            <form method="post" enctype="multipart/form-data">
                <input type="file" name="zip_file" accept=".zip" required>
                <br><br>
                <input type="submit" class="button button-primary" value="Importar e Criar Página">
            </form>
        </div>
        <div class="tab-content" id="configuracoes">
            <p>Em breve teremos uma interface de configurações.</p>
        </div>
    </div>

<?php
}



function process_html_zip_upload()
{
    if (!current_user_can('manage_options')) return;

    if (!isset($_FILES['zip_file']) || $_FILES['zip_file']['error'] !== UPLOAD_ERR_OK) return;

    $upload_dir = wp_upload_dir();
    $base_url = str_replace('http://', 'https://', $upload_dir['baseurl']) . '/html-imports/';
    $base_dir = $upload_dir['basedir'] . '/html-imports/';
    wp_mkdir_p($base_dir);

    $file_name = sanitize_file_name($_FILES['zip_file']['name']);
    $zip_path = $base_dir . $file_name;
    move_uploaded_file($_FILES['zip_file']['tmp_name'], $zip_path);

    $zip = new ZipArchive;
    if ($zip->open($zip_path) !== TRUE) {
        echo '<div class="notice notice-error"><p>Falha ao abrir o arquivo ZIP.</p></div>';
        return;
    }

    $extract_slug = sanitize_title(pathinfo($file_name, PATHINFO_FILENAME));
    $extract_dir = $base_dir . $extract_slug;
    $extract_url = $base_url . $extract_slug;
    wp_mkdir_p($extract_dir);
    $zip->extractTo($extract_dir);
    $zip->close();

    $html_file = $extract_dir . '/index.html';
    if (!file_exists($html_file)) {
        echo '<div class="notice notice-error"><p>Arquivo index.html não encontrado no ZIP.</p></div>';
        return;
    }

    $html_content = file_get_contents($html_file);

    // Corrigir links CSS
    $html_content = preg_replace_callback(
        '/<link\s+([^>]*?)href=[\'"]([^\'"]+\.css)[\'"]/i',
        fn($m) => '<link ' . $m[1] . 'href="' . (
            preg_match('#^https?://#i', $m[2]) ? preg_replace('#^http://#i', 'https://', $m[2]) : $extract_url . '/' . ltrim($m[2], '/')
        ) . '"',
        $html_content
    );

    // Corrigir links JS
    $html_content = preg_replace_callback(
        '/<script\s+([^>]*?)src=[\'"]([^\'"]+\.js)[\'"]/i',
        fn($m) => '<script ' . $m[1] . 'src="' . (
            preg_match('#^https?://#i', $m[2]) ? preg_replace('#^http://#i', 'https://', $m[2]) : $extract_url . '/' . ltrim($m[2], '/')
        ) . '"',
        $html_content
    );

    // Corrigir imagens
    $html_content = preg_replace_callback(
        '/<img\s+([^>]*?)src=[\'"]([^\'"]+)[\'"]/i',
        fn($m) => '<img ' . $m[1] . 'src="' . (
            preg_match('#^https?://#i', $m[2]) ? preg_replace('#^http://#i', 'https://', $m[2]) : $extract_url . '/' . ltrim($m[2], '/')
        ) . '"',
        $html_content
    );

    // Remover <p> vazios
    $html_content = preg_replace('/<p>(\s|&nbsp;|<br\s*\/?>)*<\/p>/i', '', $html_content);

    // Injetar Content-Security-Policy
    $html_content = str_replace(
        '<head>',
        '<head><meta http-equiv="Content-Security-Policy" content="upgrade-insecure-requests">',
        $html_content
    );

    // Salvar página
    remove_filter('content_save_pre', 'wp_filter_post_kses');
    remove_filter('content_filtered_save_pre', 'wp_filter_post_kses');

    $existing = get_page_by_path($extract_slug);
    if ($existing) wp_delete_post($existing->ID, true);

    $new_page = [
        'post_title'    => ucfirst($extract_slug),
        'post_name'     => $extract_slug,
        'post_content'  => $html_content,
        'post_status'   => 'publish',
        'post_type'     => 'page',
        'page_template' => 'html-zip-template.php',
    ];

    wp_insert_post($new_page);
    $page_url = home_url('/') . $extract_slug;
    echo '<div class="notice notice-success"><p>Sua página está pronta para ser usada!</p>';
    echo '<a href="' . esc_url($page_url) . '" target="_blank" class="button button-secondary" style="margin-top: 10px;">Ver Página</a></div>';
}
