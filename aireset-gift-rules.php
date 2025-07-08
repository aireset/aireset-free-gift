<?php
/**
 * Plugin Name: Aireset Gift Rules
 * Description: Cria regras de brinde com lógica personalizada e escolha de produto via carrinho ou checkout.
 * Author: Felipe Almeman
 * Version: 1.0.0
 * Update URI: https://github.com/felipealeman/aireset-free-gift
 */

if (!defined('ABSPATH')) exit;

class AiresetGiftRules {
    const VERSION = '1.0.0';
    const GITHUB_USER = 'felipealeman';
    const GITHUB_REPO = 'aireset-free-gift';
    private $plugin_file;

    public function __construct() {
        $this->plugin_file = plugin_basename(__FILE__);
        add_action('init', [$this, 'register_gift_rule_post_type']);
        add_action('add_meta_boxes', [$this, 'add_rule_metaboxes']);
        add_action('save_post', [$this, 'save_rule_metabox'], 10, 2);
        add_action('woocommerce_before_cart', [$this, 'check_cart_rules']);
        add_action('woocommerce_review_order_before_payment', [$this, 'check_cart_rules']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('wp_ajax_aireset_add_gift_to_cart', [$this, 'ajax_add_gift_to_cart']);
        add_action('wp_ajax_nopriv_aireset_add_gift_to_cart', [$this, 'ajax_add_gift_to_cart']);
        add_filter('pre_set_site_transient_update_plugins', [$this, 'github_check_for_update']);
        add_filter('plugins_api', [$this, 'github_plugin_info'], 10, 3);
    }

    public function register_gift_rule_post_type() {
        register_post_type('aireset_gift_rule', [
            'labels' => [
                'name' => 'Regras de Brinde',
                'singular_name' => 'Regra de Brinde',
            ],
            'public' => false,
            'show_ui' => true,
            'menu_icon' => 'dashicons-gift',
            'supports' => ['title'],
        ]);
    }

    public function add_rule_metaboxes() {
        add_meta_box('aireset_rule_conditions', 'Condições da Regra', [$this, 'render_rule_conditions'], 'aireset_gift_rule');
        add_meta_box('aireset_rule_gifts', 'Produtos de Brinde', [$this, 'render_rule_gifts'], 'aireset_gift_rule');
    }

    public function render_rule_conditions($post) {
        $group_a = get_post_meta($post->ID, '_aireset_group_a', true);
        $group_b = get_post_meta($post->ID, '_aireset_group_b', true);
        $message = get_post_meta($post->ID, '_aireset_rule_message', true);

        $group_a_ids = array_filter(array_map('absint', explode(',', $group_a)));
        $group_b_ids = array_filter(array_map('absint', explode(',', $group_b)));

        echo '<label>Grupo A:</label><br />';
        echo '<select id="aireset_group_a" name="aireset_group_a[]" multiple="multiple" style="width:100%" data-placeholder="Selecione produtos">';
        foreach ($group_a_ids as $id) {
            $product = wc_get_product($id);
            if ($product) {
                echo '<option value="' . esc_attr($id) . '" selected="selected">' . esc_html($product->get_name()) . '</option>';
            }
        }
        echo '</select><br /><br />';

        echo '<label>Grupo B:</label><br />';
        echo '<select id="aireset_group_b" name="aireset_group_b[]" multiple="multiple" style="width:100%" data-placeholder="Selecione produtos">';
        foreach ($group_b_ids as $id) {
            $product = wc_get_product($id);
            if ($product) {
                echo '<option value="' . esc_attr($id) . '" selected="selected">' . esc_html($product->get_name()) . '</option>';
            }
        }
        echo '</select><br /><br />';

        echo '<label>Mensagem personalizada:</label><br />';
        echo '<textarea name="aireset_rule_message" style="width: 100%;">' . esc_textarea($message) . '</textarea>';
    }

    public function render_rule_gifts($post) {
        $gifts = get_post_meta($post->ID, '_aireset_rule_gifts', true);
        $gift_ids = array_filter(array_map('absint', explode(',', $gifts)));
        echo '<label>Produtos de brinde:</label><br />';
        echo '<select id="aireset_rule_gifts" name="aireset_rule_gifts[]" multiple="multiple" style="width:100%" data-placeholder="Selecione produtos">';
        foreach ($gift_ids as $id) {
            $product = wc_get_product($id);
            if ($product) {
                echo '<option value="' . esc_attr($id) . '" selected="selected">' . esc_html($product->get_name()) . '</option>';
            }
        }
        echo '</select><br /><br />';
    }


    public function save_rule_metabox($post_id, $post) {
        if ($post->post_type != 'aireset_gift_rule') return;
        $group_a_input = isset($_POST['aireset_group_a']) ? array_map('absint', (array) $_POST['aireset_group_a']) : [];
        $group_b_input = isset($_POST['aireset_group_b']) ? array_map('absint', (array) $_POST['aireset_group_b']) : [];
        update_post_meta($post_id, '_aireset_group_a', implode(',', $group_a_input));
        update_post_meta($post_id, '_aireset_group_b', implode(',', $group_b_input));
        $gifts_input = isset($_POST['aireset_rule_gifts']) ? array_map('absint', (array) $_POST['aireset_rule_gifts']) : [];
        update_post_meta($post_id, '_aireset_rule_gifts', implode(',', $gifts_input));
        update_post_meta($post_id, '_aireset_rule_message', sanitize_textarea_field($_POST['aireset_rule_message']));
    }

    public function enqueue_assets() {
        wp_enqueue_script('aireset-gift-js', plugin_dir_url(__FILE__) . 'js/gift.js', ['jquery'], '1.0', true);
        wp_localize_script('aireset-gift-js', 'AiresetGiftAjax', [
            'ajax_url' => admin_url('admin-ajax.php'),
        ]);
    }

    public function enqueue_admin_assets() {
        wp_enqueue_style("select2-css", plugin_dir_url(__FILE__) . "assets/select2.min.css", [], "4.1.0");
        wp_enqueue_script("select2-js", plugin_dir_url(__FILE__) . "assets/select2.min.js", ["jquery"], "4.1.0", true);
        wp_enqueue_script("aireset-admin-js", plugin_dir_url(__FILE__) . "js/admin.js", ["jquery", "select2-js"], "1.0", true);
    }

    private function match_product_in_cart($product_ids, $cart_items) {
        foreach ($cart_items as $item) {
            $id = $item['data']->get_id();
            if (in_array($id, $product_ids, true)) {
                return true;
            }
        }
        return false;
    }

    public function check_cart_rules() {
        $rules = get_posts([
            'post_type' => 'aireset_gift_rule',
            'posts_per_page' => -1,
            'post_status' => 'publish'
        ]);

        if (empty($rules)) return;

        $cart = WC()->cart->get_cart();
        foreach ($rules as $rule) {
            $group_a = array_filter(array_map('absint', explode(',', get_post_meta($rule->ID, '_aireset_group_a', true))));
            $group_b = array_filter(array_map('absint', explode(',', get_post_meta($rule->ID, '_aireset_group_b', true))));
            $message = get_post_meta($rule->ID, '_aireset_rule_message', true);
            $gifts = array_filter(array_map('absint', explode(',', get_post_meta($rule->ID, '_aireset_rule_gifts', true))));

            if ($this->match_product_in_cart($group_a, $cart) && $this->match_product_in_cart($group_b, $cart)) {
                echo '<div class="woocommerce-message aireset-gift-message">' . esc_html($message) . '</div>';
                echo '<form class="aireset-gift-form" method="post">';
                echo '<select name="gift_product_id">';
                foreach ($gifts as $gift_id) {
                    $product = wc_get_product($gift_id);
                    if ($product) {
                        echo '<option value="' . esc_attr($gift_id) . '">' . esc_html($product->get_name()) . '</option>';
                    }
                }
                echo '</select> ';
                echo '<button type="button" class="button aireset-add-gift">Adicionar brinde</button>';
                echo '</form>';
                return;
            }
        }
    }

    public function ajax_add_gift_to_cart() {
        $product_id = intval($_POST['product_id'] ?? 0);
        if ($product_id && !WC()->cart->find_product_in_cart(WC()->cart->generate_cart_id($product_id))) {
            WC()->cart->add_to_cart($product_id, 1);
            wp_send_json_success(['added' => $product_id]);
        }
        wp_send_json_error(['message' => 'Brinde não adicionado.']);
    }

    private function get_github_release() {
        $url = sprintf('https://api.github.com/repos/%s/%s/releases/latest', self::GITHUB_USER, self::GITHUB_REPO);
        $response = wp_remote_get($url, [
            'headers' => ['Accept' => 'application/vnd.github+json']
        ]);
        if (is_wp_error($response)) {
            return false;
        }
        $data = json_decode(wp_remote_retrieve_body($response));
        if (empty($data->tag_name)) {
            return false;
        }
        return [
            'version' => ltrim($data->tag_name, 'v'),
            'download_url' => $data->zipball_url,
            'html_url' => $data->html_url
        ];
    }

    public function github_check_for_update($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }
        $remote = $this->get_github_release();
        if (!$remote) {
            return $transient;
        }
        if (version_compare(self::VERSION, $remote['version'], '<')) {
            $plugin = $this->plugin_file;
            $transient->response[$plugin] = (object) [
                'slug' => basename($plugin, '.php'),
                'plugin' => $plugin,
                'new_version' => $remote['version'],
                'url' => $remote['html_url'],
                'package' => $remote['download_url'],
            ];
        }
        return $transient;
    }

    public function github_plugin_info($res, $action, $args) {
        if ($action !== 'plugin_information') {
            return $res;
        }

        if ($args->slug !== basename($this->plugin_file, '.php')) {
            return $res;
        }

        $remote = $this->get_github_release();
        if (!$remote) {
            return $res;
        }

        $res = (object) [
            'name' => 'Aireset Gift Rules',
            'slug' => basename($this->plugin_file, '.php'),
            'version' => $remote['version'],
            'author' => '<a href="https://github.com/felipealeman">Felipe Almeman</a>',
            'homepage' => $remote['html_url'],
            'download_link' => $remote['download_url'],
            'sections' => [
                'description' => 'Plugin para criação de regras de brinde.'
            ]
        ];

        return $res;
    }
}

new AiresetGiftRules();