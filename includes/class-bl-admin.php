<?php
namespace BL\Bilamina;

defined('ABSPATH') || exit;

class Admin {

    private static $instance = null;

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
            self::$instance->hooks();
        }
        return self::$instance;
    }

    private function hooks() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);

        // AJAX callbacks
        add_action('wp_ajax_bl_ic_search_products', [$this, 'ajax_search_products']);
        add_action('wp_ajax_bl_ic_search_categories', [$this, 'ajax_search_categories']);
        add_action('wp_ajax_bl_ic_search_tags', [$this, 'ajax_search_tags']);
    }

    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            __('Bilamina Installment Control', 'bilamina-iyzico-installment-control'),
            __('Installment Control', 'bilamina-iyzico-installment-control'),
            'manage_woocommerce',
            'bl-iyzico-installment-control',
            [$this, 'render_page']
        );
    }

    public function enqueue_assets() {
        wp_enqueue_script('selectWoo');
        wp_enqueue_style('woocommerce_admin_styles');

        wp_add_inline_script('selectWoo', "
            jQuery(document).ready(function($) {
                function init(selector, action) {
                    $(selector).selectWoo({
                        ajax: {
                            url: ajaxurl,
                            dataType: 'json',
                            delay: 250,
                            data: function(params) {
                                return { action: action, q: params.term };
                            },
                            processResults: function(data) {
                                return { results: data };
                            },
                            cache: true
                        },
                        multiple: true,
                        width: '50%',
                        placeholder: 'Select items...'
                    });
                }

                init('.bl-ic-categories-include', 'bl_ic_search_categories');
                init('.bl-ic-categories-exclude', 'bl_ic_search_categories');
                init('.bl-ic-tags-include', 'bl_ic_search_tags');
                init('.bl-ic-tags-exclude', 'bl_ic_search_tags');
                init('.bl-ic-products-include', 'bl_ic_search_products');
                init('.bl-ic-products-exclude', 'bl_ic_search_products');
            });
        ");
    }

    public function ajax_search_products() {
        $term = isset($_GET['q']) ? sanitize_text_field($_GET['q']) : '';
        $args = ['s' => $term, 'post_type' => 'product', 'posts_per_page' => 20];
        $query = new \WP_Query($args);

        $results = [];
        if ($query->have_posts()) {
            foreach ($query->posts as $post) {
                $results[] = ['id' => $post->ID, 'text' => $post->post_title];
            }
        }

        wp_send_json($results);
    }

    public function ajax_search_categories() {
        $term = isset($_GET['q']) ? sanitize_text_field($_GET['q']) : '';
        $categories = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false, 'search' => $term, 'number' => 20]);

        $results = [];
        if (!is_wp_error($categories)) {
            foreach ($categories as $cat) {
                $results[] = ['id' => $cat->slug, 'text' => $cat->name];
            }
        }

        wp_send_json($results);
    }

    public function ajax_search_tags() {
        $term = isset($_GET['q']) ? sanitize_text_field($_GET['q']) : '';
        $tags = get_terms(['taxonomy' => 'product_tag', 'hide_empty' => false, 'search' => $term, 'number' => 20]);

        $results = [];
        if (!is_wp_error($tags)) {
            foreach ($tags as $tag) {
                $results[] = ['id' => $tag->slug, 'text' => $tag->name];
            }
        }

        wp_send_json($results);
    }

    public function render_page() {
        $saved_rules = get_option('bl_ic_installment_rules', []);

        if (isset($_POST['bl_ic_nonce']) && wp_verify_nonce($_POST['bl_ic_nonce'], 'bl_ic_save_settings')) {
            $data = [
                'categories' => [
                    'include' => $_POST['bl_ic_categories_include'] ?? [],
                    'exclude' => $_POST['bl_ic_categories_exclude'] ?? [],
                    'months'  => isset($_POST['bl_ic_categories_months']) ? max(1, intval($_POST['bl_ic_categories_months'])) : 1,
                ],
                'tags' => [
                    'include' => $_POST['bl_ic_tags_include'] ?? [],
                    'exclude' => $_POST['bl_ic_tags_exclude'] ?? [],
                    'months'  => isset($_POST['bl_ic_tags_months']) ? max(1, intval($_POST['bl_ic_tags_months'])) : 1,
                ],
                'products' => [
                    'include' => $_POST['bl_ic_products_include'] ?? [],
                    'exclude' => $_POST['bl_ic_products_exclude'] ?? [],
                    'months'  => isset($_POST['bl_ic_products_months']) ? max(1, intval($_POST['bl_ic_products_months'])) : 1,
                ],
            ];

            update_option('bl_ic_installment_rules', $data);

            echo '<div class="notice notice-success is-dismissible"><p>'
                . esc_html__('Settings saved successfully.', 'bilamina-iyzico-installment-control')
                . '</p></div>';

            $saved_rules = $data;
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Bilamina Iyzico Installment Control', 'bilamina-iyzico-installment-control'); ?></h1>
            <form method="post">
                <?php wp_nonce_field('bl_ic_save_settings', 'bl_ic_nonce'); ?>

                <h2><?php esc_html_e('Categories', 'bilamina-iyzico-installment-control'); ?></h2>
                <p><?php esc_html_e('Allowed categories:', 'bilamina-iyzico-installment-control'); ?></p>
                <select name="bl_ic_categories_include[]" class="bl-ic-categories-include" multiple="multiple" style="width:50%;">
                    <?php
                    $include_cats = $saved_rules['categories']['include'] ?? [];
                    if (!empty($include_cats)) {
                        foreach ($include_cats as $slug) {
                            $term = get_term_by('slug', $slug, 'product_cat');
                            if ($term) {
                                echo '<option value="' . esc_attr($slug) . '" selected>' . esc_html($term->name) . '</option>';
                            }
                        }
                    }
                    ?>
                </select>

                <p><?php esc_html_e('Excluded categories:', 'bilamina-iyzico-installment-control'); ?></p>
                <select name="bl_ic_categories_exclude[]" class="bl-ic-categories-exclude" multiple="multiple" style="width:50%;">
                    <?php
                    $exclude_cats = $saved_rules['categories']['exclude'] ?? [];
                    if (!empty($exclude_cats)) {
                        foreach ($exclude_cats as $slug) {
                            $term = get_term_by('slug', $slug, 'product_cat');
                            if ($term) {
                                echo '<option value="' . esc_attr($slug) . '" selected>' . esc_html($term->name) . '</option>';
                            }
                        }
                    }
                    ?>
                </select>

                <p><?php esc_html_e('Max installments (months):', 'bilamina-iyzico-installment-control'); ?></p>
                <input type="number" name="bl_ic_categories_months" min="1" max="12" style="width:100px;" value="<?php echo isset($saved_rules['categories']['months']) ? intval($saved_rules['categories']['months']) : 1; ?>" />

                <hr>

                <h2><?php esc_html_e('Tags', 'bilamina-iyzico-installment-control'); ?></h2>
                <p><?php esc_html_e('Allowed tags:', 'bilamina-iyzico-installment-control'); ?></p>
                <select name="bl_ic_tags_include[]" class="bl-ic-tags-include" multiple="multiple" style="width:50%;">
                    <?php
                    $include_tags = $saved_rules['tags']['include'] ?? [];
                    if (!empty($include_tags)) {
                        foreach ($include_tags as $slug) {
                            $term = get_term_by('slug', $slug, 'product_tag');
                            if ($term) {
                                echo '<option value="' . esc_attr($slug) . '" selected>' . esc_html($term->name) . '</option>';
                            }
                        }
                    }
                    ?>
                </select>

                <p><?php esc_html_e('Excluded tags:', 'bilamina-iyzico-installment-control'); ?></p>
                <select name="bl_ic_tags_exclude[]" class="bl-ic-tags-exclude" multiple="multiple" style="width:50%;">
                    <?php
                    $exclude_tags = $saved_rules['tags']['exclude'] ?? [];
                    if (!empty($exclude_tags)) {
                        foreach ($exclude_tags as $slug) {
                            $term = get_term_by('slug', $slug, 'product_tag');
                            if ($term) {
                                echo '<option value="' . esc_attr($slug) . '" selected>' . esc_html($term->name) . '</option>';
                            }
                        }
                    }
                    ?>
                </select>

                <p><?php esc_html_e('Max installments (months):', 'bilamina-iyzico-installment-control'); ?></p>
                <input type="number" name="bl_ic_tags_months" min="1" max="12" style="width:100px;" value="<?php echo isset($saved_rules['tags']['months']) ? intval($saved_rules['tags']['months']) : 1; ?>" />

                <hr>

                <h2><?php esc_html_e('Products', 'bilamina-iyzico-installment-control'); ?></h2>
                <p><?php esc_html_e('Allowed products:', 'bilamina-iyzico-installment-control'); ?></p>
                <select name="bl_ic_products_include[]" class="bl-ic-products-include" multiple="multiple" style="width:50%;">
                    <?php
                    $include_products = $saved_rules['products']['include'] ?? [];
                    if (!empty($include_products)) {
                        foreach ($include_products as $id) {
                            $product = wc_get_product($id);
                            if ($product) {
                                echo '<option value="' . esc_attr($id) . '" selected>' . esc_html($product->get_name()) . '</option>';
                            }
                        }
                    }
                    ?>
                </select>

                <p><?php esc_html_e('Excluded products:', 'bilamina-iyzico-installment-control'); ?></p>
                <select name="bl_ic_products_exclude[]" class="bl-ic-products-exclude" multiple="multiple" style="width:50%;">
                    <?php
                    $exclude_products = $saved_rules['products']['exclude'] ?? [];
                    if (!empty($exclude_products)) {
                        foreach ($exclude_products as $id) {
                            $product = wc_get_product($id);
                            if ($product) {
                                echo '<option value="' . esc_attr($id) . '" selected>' . esc_html($product->get_name()) . '</option>';
                            }
                        }
                    }
                    ?>
                </select>

                <p><?php esc_html_e('Max installments (months):', 'bilamina-iyzico-installment-control'); ?></p>
                <input type="number" name="bl_ic_products_months" min="1" max="12" style="width:100px;" value="<?php echo isset($saved_rules['products']['months']) ? intval($saved_rules['products']['months']) : 1; ?>" />

                <?php submit_button(__('Save Settings', 'bilamina-iyzico-installment-control')); ?>
            </form>
        </div>
        <?php
    }
}
