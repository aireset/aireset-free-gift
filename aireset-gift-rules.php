<?php
/**
 * Plugin Name: Aireset Gift Rules
 * Description: Cria regras de brinde com lógica personalizada e escolha de produto via carrinho ou checkout.
 * Author: Felipe Almeman
 * Version: 1.0.0
 */

if (!defined('ABSPATH')) exit;

class AiresetGiftRules {
    public function __construct() {
        add_action('init', [$this, 'register_gift_rule_post_type']);
        add_action('add_meta_boxes', [$this, 'add_rule_metaboxes']);
        add_action('save_post', [$this, 'save_rule_metabox'], 10, 2);
        add_action('woocommerce_before_cart', [$this, 'check_cart_rules']);
        add_action('woocommerce_review_order_before_payment', [$this, 'check_cart_rules']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('wp_ajax_aireset_add_gift_to_cart', [$this, 'ajax_add_gift_to_cart']);
        add_action('wp_ajax_nopriv_aireset_add_gift_to_cart', [$this, 'ajax_add_gift_to_cart']);
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
        echo '<label>Grupo A (produtos separados por vírgula - SKU, ID ou nome):</label><br />';
        echo '<input type="text" name="aireset_group_a" value="' . esc_attr($group_a) . '" style="width: 100%;" /><br /><br />';
        echo '<label>Grupo B (produto obrigatório - SKU, ID ou nome):</label><br />';
        echo '<input type="text" name="aireset_group_b" value="' . esc_attr($group_b) . '" style="width: 100%;" /><br /><br />';
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
        update_post_meta($post_id, '_aireset_group_a', sanitize_text_field($_POST['aireset_group_a']));
        update_post_meta($post_id, '_aireset_group_b', sanitize_text_field($_POST['aireset_group_b']));
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

    private function match_product_in_cart($product_terms, $cart_items) {
        foreach ($cart_items as $item) {
            $product = $item['data'];
            $id = $product->get_id();
            $sku = $product->get_sku();
            $name = $product->get_name();
            foreach ($product_terms as $term) {
                if ($term === $sku || $term === (string)$id || stripos($name, $term) !== false) {
                    return true;
                }
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
            $group_a = array_map('trim', explode(',', get_post_meta($rule->ID, '_aireset_group_a', true)));
            $group_b = array_map('trim', explode(',', get_post_meta($rule->ID, '_aireset_group_b', true)));
            $message = get_post_meta($rule->ID, '_aireset_rule_message', true);
            $gifts = array_map('trim', explode(',', get_post_meta($rule->ID, '_aireset_rule_gifts', true)));

            if ($this->match_product_in_cart($group_a, $cart) && $this->match_product_in_cart($group_b, $cart)) {
                echo '<div class="woocommerce-message aireset-gift-message">' . esc_html($message) . '</div>';
                echo '<form class="aireset-gift-form" method="post">';
                echo '<select name="gift_product_id">';
                foreach ($gifts as $gift_term) {
                    $gift_id = wc_get_product_id_by_sku($gift_term);
                    if (!$gift_id && is_numeric($gift_term)) {
                        $gift_id = (int) $gift_term;
                    }
                    if (!$gift_id) {
                        $gift_id = wc_get_product_id_by_name($gift_term);
                    }
                    if ($gift_id) {
                        $product = wc_get_product($gift_id);
                        if ($product) {
                            echo '<option value="' . esc_attr($gift_id) . '">' . esc_html($product->get_name()) . '</option>';
                        }
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
}

new AiresetGiftRules();
