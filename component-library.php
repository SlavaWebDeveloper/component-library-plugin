<?php
/**
 * Plugin Name: Component Library
 * Plugin URI: https://github.com/your-username/component-library
 * Description: Библиотека компонентов с автоматической генерацией тестовых данных для WordPress тем
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://site100.ru
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: component-library
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) exit;

// Определяем константы плагина
define('COMPONENT_LIBRARY_VERSION', '1.0.0');
define('COMPONENT_LIBRARY_PATH', plugin_dir_path(__FILE__));
define('COMPONENT_LIBRARY_URL', plugin_dir_url(__FILE__));

class ComponentLibrary {
    
    private $sample_images = array(
        'https://images.unsplash.com/photo-1618221195710-dd6b41faaea6?w=800&h=600&fit=crop',
        'https://images.unsplash.com/photo-1615529328331-f8917597711f?w=800&h=600&fit=crop',
        'https://images.unsplash.com/photo-1618005182384-a83a8bd57fbe?w=800&h=600&fit=crop',
        'https://images.unsplash.com/photo-1616486338812-3dadae4b4ace?w=800&h=600&fit=crop',
        'https://images.unsplash.com/photo-1615529182904-14819c35db37?w=800&h=600&fit=crop',
    );
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_menu_page'));
        add_action('wp_ajax_generate_sample_data', array($this, 'generate_sample_data'));
        add_action('wp_ajax_delete_sample_data', array($this, 'delete_sample_data'));
    }
    
    public function add_menu_page() {
        add_menu_page(
            'Component Library',
            'Components',
            'manage_options',
            'component-library',
            array($this, 'render_library_page'),
            'dashicons-layout',
            30
        );
    }
    
    /**
     * Генерирует тестовые данные
     */
    public function generate_sample_data() {
        check_ajax_referer('component_library_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('No permission');
        }
        
        $results = array(
            'products' => 0,
            'categories' => 0,
            'posts' => 0,
            'pages' => 0
        );
        
        // Создаем категории товаров
        if (class_exists('WooCommerce')) {
            $categories = array('Мебель', 'Освещение', 'Декор', 'Текстиль');
            
            foreach ($categories as $cat_name) {
                $term = wp_insert_term($cat_name, 'product_cat');
                if (!is_wp_error($term)) {
                    $results['categories']++;
                }
            }
            
            // Создаем товары
            $product_names = array(
                'Диван "Комфорт"',
                'Кресло "Уют"',
                'Стол журнальный',
                'Стеллаж "Лофт"',
                'Торшер "Модерн"',
                'Подушка декоративная',
                'Ковер "Скандинавия"',
                'Зеркало настенное'
            );
            
            foreach ($product_names as $index => $name) {
                $product = new WC_Product_Simple();
                $product->set_name($name);
                $product->set_description('Описание товара ' . $name);
                $product->set_short_description('Краткое описание товара');
                $product->set_regular_price(rand(5000, 50000));
                $product->set_status('publish');
                $product->set_catalog_visibility('visible');
                
                // Устанавливаем случайное изображение
                $image_id = $this->create_attachment_from_url(
                    $this->sample_images[array_rand($this->sample_images)],
                    $name
                );
                if ($image_id) {
                    $product->set_image_id($image_id);
                }
                
                // Добавляем категорию
                $terms = get_terms(array('taxonomy' => 'product_cat', 'hide_empty' => false, 'number' => 1));
                if (!empty($terms)) {
                    $product->set_category_ids(array($terms[0]->term_id));
                }
                
                $product->save();
                $results['products']++;
            }
        }
        
        // Создаем страницы
        $page_titles = array(
            'О компании',
            'Контакты',
            'Доставка и оплата',
            'Портфолио'
        );
        
        foreach ($page_titles as $title) {
            $page_id = wp_insert_post(array(
                'post_title' => $title,
                'post_content' => '<p>Это тестовое содержимое страницы ' . $title . '</p>',
                'post_status' => 'publish',
                'post_type' => 'page'
            ));
            
            if ($page_id && !is_wp_error($page_id)) {
                $results['pages']++;
            }
        }
        
        // Создаем посты
        $post_titles = array(
            'Тренды дизайна интерьера 2024',
            'Как выбрать правильную мебель',
            'Скандинавский стиль в интерьере',
            'Советы по освещению комнаты'
        );
        
        foreach ($post_titles as $index => $title) {
            $post_id = wp_insert_post(array(
                'post_title' => $title,
                'post_content' => '<p>Содержимое поста о ' . strtolower($title) . '.</p><p>Здесь могла быть полезная информация.</p>',
                'post_status' => 'publish',
                'post_type' => 'post',
                'post_category' => array(1)
            ));
            
            if ($post_id && !is_wp_error($post_id)) {
                // Добавляем изображение
                $image_id = $this->create_attachment_from_url(
                    $this->sample_images[array_rand($this->sample_images)],
                    $title
                );
                if ($image_id) {
                    set_post_thumbnail($post_id, $image_id);
                }
                $results['posts']++;
            }
        }
        
        // Сохраняем метку что данные созданы
        update_option('component_library_sample_data_created', time());
        
        wp_send_json_success($results);
    }
    
    /**
     * Удаляет тестовые данные
     */
    public function delete_sample_data() {
        check_ajax_referer('component_library_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('No permission');
        }
        
        $results = array(
            'products' => 0,
            'categories' => 0,
            'posts' => 0,
            'pages' => 0
        );
        
        // Удаляем товары
        if (class_exists('WooCommerce')) {
            $products = get_posts(array(
                'post_type' => 'product',
                'posts_per_page' => -1,
                'date_query' => array(
                    array(
                        'after' => date('Y-m-d H:i:s', get_option('component_library_sample_data_created', 0))
                    )
                )
            ));
            
            foreach ($products as $product) {
                wp_delete_post($product->ID, true);
                $results['products']++;
            }
            
            // Удаляем категории
            $categories = get_terms(array(
                'taxonomy' => 'product_cat',
                'hide_empty' => false
            ));
            
            foreach ($categories as $category) {
                if (in_array($category->name, array('Мебель', 'Освещение', 'Декор', 'Текстиль'))) {
                    wp_delete_term($category->term_id, 'product_cat');
                    $results['categories']++;
                }
            }
        }
        
        delete_option('component_library_sample_data_created');
        
        wp_send_json_success($results);
    }
    
    /**
     * Создает attachment из URL
     */
    private function create_attachment_from_url($url, $title) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        
        $tmp = download_url($url);
        
        if (is_wp_error($tmp)) {
            return false;
        }
        
        $file_array = array(
            'name' => basename($url) . '.jpg',
            'tmp_name' => $tmp
        );
        
        $id = media_handle_sideload($file_array, 0, $title);
        
        if (is_wp_error($id)) {
            @unlink($file_array['tmp_name']);
            return false;
        }
        
        return $id;
    }
    
    /**
     * Сканирует компоненты
     */
    private function scan_components() {
        $components = array();
        $template_parts_dir = get_template_directory() . '/template-parts';
        
        if (!is_dir($template_parts_dir)) {
            return $components;
        }
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($template_parts_dir)
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $content = file_get_contents($file->getPathname());
                
                if (preg_match('/\/\*\*(.*?)\*\//s', $content, $matches)) {
                    $doc = $matches[1];
                    $name = '';
                    
                    $lines = explode("\n", $doc);
                    foreach ($lines as $line) {
                        $line = trim($line, " \t\n\r\0\x0B*");
                        if (!empty($line)) {
                            $name = $line;
                            break;
                        }
                    }
                    
                    if ($name) {
                        $components[] = array(
                            'name' => $name,
                            'path' => str_replace(
                                array($template_parts_dir . '/', $template_parts_dir . '\\', '.php'),
                                '',
                                $file->getPathname()
                            )
                        );
                    }
                }
            }
        }
        
        return $components;
    }
    
    /**
     * Отрисовка страницы библиотеки
     */
    public function render_library_page() {
        $components = $this->scan_components();
        $data_exists = get_option('component_library_sample_data_created', false);
        ?>
        <div class="wrap">
            <h1>Component Library</h1>
            
            <div class="card" style="max-width: 800px; margin: 20px 0;">
                <h2>Тестовые данные</h2>
                <p>Для корректного отображения компонентов необходимы тестовые данные (товары, категории, страницы).</p>
                
                <?php if ($data_exists): ?>
                    <p style="color: green;">✓ Тестовые данные созданы</p>
                    <button type="button" class="button button-secondary" id="delete-sample-data">
                        Удалить тестовые данные
                    </button>
                <?php else: ?>
                    <p style="color: orange;">⚠ Тестовые данные не созданы</p>
                    <button type="button" class="button button-primary" id="generate-sample-data">
                        Создать тестовые данные
                    </button>
                <?php endif; ?>
                
                <div id="sample-data-result" style="margin-top: 15px;"></div>
            </div>
            
            <h2>Найдено компонентов: <?php echo count($components); ?></h2>
            
            <div style="margin-top: 20px;">
                <a href="<?php echo home_url('/?component-library=1'); ?>" class="button button-primary" target="_blank">
                    Открыть библиотеку компонентов
                </a>
            </div>
            
            <table class="wp-list-table widefat fixed striped" style="margin-top: 20px;">
                <thead>
                    <tr>
                        <th>Компонент</th>
                        <th>Путь</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($components as $component): ?>
                        <tr>
                            <td><strong><?php echo esc_html($component['name']); ?></strong></td>
                            <td><code>template-parts/<?php echo esc_html(str_replace('\\', '/', $component['path'])); ?>.php</code></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#generate-sample-data').on('click', function() {
                var $btn = $(this);
                var $result = $('#sample-data-result');
                
                $btn.prop('disabled', true).text('Создание...');
                $result.html('<p>Пожалуйста, подождите...</p>');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'generate_sample_data',
                        nonce: '<?php echo wp_create_nonce('component_library_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $result.html(
                                '<div class="notice notice-success"><p><strong>Успешно создано:</strong></p>' +
                                '<ul>' +
                                '<li>Товаров: ' + response.data.products + '</li>' +
                                '<li>Категорий: ' + response.data.categories + '</li>' +
                                '<li>Страниц: ' + response.data.pages + '</li>' +
                                '<li>Постов: ' + response.data.posts + '</li>' +
                                '</ul></div>'
                            );
                            setTimeout(function() {
                                location.reload();
                            }, 2000);
                        } else {
                            $result.html('<div class="notice notice-error"><p>Ошибка: ' + response.data + '</p></div>');
                            $btn.prop('disabled', false).text('Создать тестовые данные');
                        }
                    },
                    error: function() {
                        $result.html('<div class="notice notice-error"><p>Ошибка при создании данных</p></div>');
                        $btn.prop('disabled', false).text('Создать тестовые данные');
                    }
                });
            });
            
            $('#delete-sample-data').on('click', function() {
                if (!confirm('Вы уверены, что хотите удалить тестовые данные?')) {
                    return;
                }
                
                var $btn = $(this);
                var $result = $('#sample-data-result');
                
                $btn.prop('disabled', true).text('Удаление...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'delete_sample_data',
                        nonce: '<?php echo wp_create_nonce('component_library_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $result.html('<div class="notice notice-success"><p>Тестовые данные удалены</p></div>');
                            setTimeout(function() {
                                location.reload();
                            }, 1500);
                        }
                    }
                });
            });
        });
        </script>
        <?php
    }
}

// Инициализация плагина
new ComponentLibrary();

// Добавляем фронтенд страницу библиотеки
add_action('template_redirect', function() {
    if (isset($_GET['component-library']) && current_user_can('manage_options')) {
        include plugin_dir_path(__FILE__) . 'library-frontend.php';
        exit;
    }
});
