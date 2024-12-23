<?php
/*
Plugin Name: WooCommerce Role-Based Discounts
Description: Apply discounts to specific user roles for selected products in WooCommerce.
* Version: 1.0
 * Author: Mikiyas Shiferaw
 * Author URI: https://t.me/mikiyas_sh
 * License: GPL2
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class WC_Role_Based_Discounts {
    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('woocommerce_cart_calculate_fees', [$this, 'apply_discounts']);
    }

    public function add_admin_menu() {
        add_menu_page(
            'Role-Based Discounts',
            'Role Discounts',
            'manage_options',
            'wc-role-based-discounts',
            [$this, 'settings_page'],
            'dashicons-cart',
            56
        );
    }

    public function settings_page() {
        if (isset($_POST['save_role_discounts'])) {
            // Save settings for the current site (multisite compatible)
            update_option('wc_role_discounts', sanitize_text_field($_POST['role']));
            update_option('wc_discount_type', sanitize_text_field($_POST['discount_type']));
            update_option('wc_discount_amount', sanitize_text_field($_POST['discount_amount']));
            update_option('wc_discount_products', sanitize_text_field($_POST['product_ids']));
            echo '<div class="updated"><p>Settings Saved.</p></div>';
        }

        // Retrieve settings for the current site (multisite compatible)
        $role = get_option('wc_role_discounts', '');
        $discount_type = get_option('wc_discount_type', 'percentage');
        $discount_amount = get_option('wc_discount_amount', '');
        $product_ids = get_option('wc_discount_products', '');

        ?>
        <div class="wrap">
            <h1>Role-Based Discounts</h1>
            <form method="post" action="">
                <table class="form-table">
                    <tr>
                        <th scope="row">User Role</th>
                        <td>
                            <select name="role">
                                <?php
                                global $wp_roles;
                                foreach ($wp_roles->roles as $key => $role_data) {
                                    echo "<option value='{$key}' " . selected($role, $key, false) . ">{$role_data['name']}</option>";
                                }
                                ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Discount Type</th>
                        <td>
                            <select name="discount_type">
                                <option value="percentage" <?php selected($discount_type, 'percentage'); ?>>Percentage</option>
                                <option value="fixed" <?php selected($discount_type, 'fixed'); ?>>Fixed Amount</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Discount Amount</th>
                        <td>
                            <input type="number" name="discount_amount" value="<?php echo esc_attr($discount_amount); ?>" required>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Product IDs (comma-separated)</th>
                        <td>
                            <input type="text" name="product_ids" value="<?php echo esc_attr($product_ids); ?>" placeholder="e.g., 12,45,78">
                        </td>
                    </tr>
                </table>
                <?php submit_button('Save Settings', 'primary', 'save_role_discounts'); ?>
            </form>
        </div>
        <?php
    }

    public function apply_discounts($cart) {
        if (is_admin() || !defined('DOING_AJAX')) {
            return;
        }

        // Get site-specific settings
        $role = get_option('wc_role_discounts', '');
        $discount_type = get_option('wc_discount_type', 'percentage');
        $discount_amount = (float)get_option('wc_discount_amount', 0);
        $product_ids = array_map('trim', explode(',', get_option('wc_discount_products', '')));

        if (empty($role) || empty($discount_amount) || empty($product_ids)) {
            return;
        }

        $current_user = wp_get_current_user();

        if (in_array($role, $current_user->roles)) {
            foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
                if (in_array($cart_item['product_id'], $product_ids)) {
                    $product_price = $cart_item['data']->get_price();

                    if ($discount_type === 'percentage') {
                        $discount = $product_price * ($discount_amount / 100);
                    } else {
                        $discount = $discount_amount;
                    }

                    $cart->add_fee('Role-Based Discount', -$discount * $cart_item['quantity']);
                }
            }
        }
    }
}

new WC_Role_Based_Discounts();
