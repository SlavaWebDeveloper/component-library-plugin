<?php
/**
 * Фронтенд библиотеки компонентов
 */

// Автоматически настраиваем контекст
global $post, $product, $wp_query;

// Загружаем случайный товар для WooCommerce компонентов
if (class_exists('WooCommerce')) {
    $products = get_posts(array(
        'post_type' => 'product',
        'posts_per_page' => 1,
        'post_status' => 'publish',
        'orderby' => 'rand'
    ));
    
    if (!empty($products)) {
        $post = $products[0];
        setup_postdata($post);
        $product = wc_get_product($post->ID);
    }
}

// Сканируем компоненты
function scan_components_frontend() {
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
                $info = array(
                    'path' => str_replace(
                        array($template_parts_dir . '/', $template_parts_dir . '\\', '.php'),
                        '',
                        $file->getPathname()
                    ),
                    'name' => '',
                    'description' => '',
                    'params' => array()
                );
                
                // Название
                $lines = explode("\n", $doc);
                foreach ($lines as $line) {
                    $line = trim($line, " \t\n\r\0\x0B*");
                    if (!empty($line) && empty($info['name'])) {
                        $info['name'] = $line;
                        break;
                    }
                }
                
                // Описание
                preg_match('/\* (.*?)(?=\n \* \n|\n \* Параметры:)/s', $doc, $desc_match);
                if (isset($desc_match[1])) {
                    $info['description'] = trim(str_replace('* ', '', $desc_match[1]));
                }
                
                // Параметры
                preg_match_all('/\* \$args\[\'([^\']+)\'\]\s*-\s*(.+)/m', $doc, $param_matches);
                if (!empty($param_matches[1])) {
                    foreach ($param_matches[1] as $i => $param_name) {
                        $info['params'][] = array(
                            'name' => $param_name,
                            'description' => trim($param_matches[2][$i])
                        );
                    }
                }
                
                $components[] = $info;
            }
        }
    }
    
    return $components;
}

$components = scan_components_frontend();
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Component Library - <?php bloginfo('name'); ?></title>
    <?php wp_head(); ?>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            line-height: 1.6;
            color: #333;
            background: #fff;
        }
        
        .library {
            max-width: 1200px;
            margin: 0 auto;
            padding: 60px 20px;
        }
        
        .library-header {
            margin-bottom: 60px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        
        .library-header h1 {
            font-size: 32px;
            font-weight: 300;
            margin-bottom: 10px;
        }
        
        .library-header p {
            color: #666;
            font-size: 16px;
        }
        
        .component {
            margin-bottom: 80px;
        }
        
        .component-title {
            font-size: 24px;
            font-weight: 400;
            margin-bottom: 8px;
        }
        
        .component-path {
            font-size: 13px;
            color: #999;
            font-family: 'Monaco', monospace;
            margin-bottom: 20px;
        }
        
        .component-desc {
            color: #666;
            margin-bottom: 30px;
            line-height: 1.8;
        }
        
        .params {
            background: #fafafa;
            padding: 20px;
            margin-bottom: 30px;
            border-radius: 4px;
        }
        
        .params-title {
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 15px;
            color: #666;
        }
        
        .param-item {
            margin-bottom: 10px;
            font-size: 14px;
        }
        
        .param-name {
            font-family: 'Monaco', monospace;
            color: #0066cc;
            font-size: 13px;
        }
        
        .preview {
            border: 1px solid #eee;
            padding: 30px;
            margin-bottom: 20px;
            background: #fff;
        }
        
        .code-toggle {
            font-size: 13px;
            color: #0066cc;
            cursor: pointer;
            border: none;
            background: none;
            padding: 8px 0;
        }
        
        .code-toggle:hover {
            text-decoration: underline;
        }
        
        .code-block {
            display: none;
            background: #f5f5f5;
            padding: 20px;
            border-radius: 4px;
            margin-top: 10px;
        }
        
        .code-block.visible {
            display: block;
        }
        
        .code-block pre {
            font-family: 'Monaco', 'Courier New', monospace;
            font-size: 13px;
            line-height: 1.6;
            color: #333;
            overflow-x: auto;
        }
    </style>
</head>
<body>

<div class="library">
    <div class="library-header">
        <h1>Component Library</h1>
        <p><?php echo count($components); ?> компонентов найдено</p>
    </div>
    
    <?php foreach ($components as $component): 
        $normalized_path = str_replace('\\', '/', $component['path']);
    ?>
        <div class="component">
            <h2 class="component-title"><?php echo esc_html($component['name']); ?></h2>
            <div class="component-path">template-parts/<?php echo esc_html($normalized_path); ?>.php</div>
            
            <?php if ($component['description']): ?>
                <div class="component-desc"><?php echo nl2br(esc_html($component['description'])); ?></div>
            <?php endif; ?>
            
            <?php if (!empty($component['params'])): ?>
                <div class="params">
                    <div class="params-title">Parameters</div>
                    <?php foreach ($component['params'] as $param): ?>
                        <div class="param-item">
                            <span class="param-name">$args['<?php echo esc_html($param['name']); ?>']</span>
                            — <?php echo esc_html($param['description']); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <div class="preview">
                <?php 
                ob_start();
                get_template_part('template-parts/' . $normalized_path);
                $output = ob_get_clean();
                
                if (empty(trim($output))) {
                    echo '<p style="color: #999; text-align: center;">No output</p>';
                } else {
                    echo $output;
                }
                ?>
            </div>
            
            <button class="code-toggle" onclick="this.nextElementSibling.classList.toggle('visible')">
                View code
            </button>
            <div class="code-block">
                <pre><code><?php echo esc_html("<?php get_template_part('template-parts/{$normalized_path}'); ?>"); ?></code></pre>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php wp_footer(); ?>
</body>
</html>
