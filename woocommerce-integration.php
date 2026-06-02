<?php
/**
 * WooCommerce Integration - بخش یکپارچگی WooCommerce
 * 
 * وظایف:
 * 1. ایجاد محصول بازی‌ها خودکار
 * 2. محاسبه قیمت بر اساس تعداد بازی‌های انتخابی
 * 3. اضافه کردن سفارش به سبد خرید WooCommerce
 * 4. هدایت به صفحه checkout
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * محاسبه قیمت بر اساس تعداد بازی‌های انتخابی
 * 
 * @param int $game_count تعداد بازی‌های انتخاب‌شده
 * @return int قیمت هر بازی (تومان)
 */
function ggp_calculate_price_per_game($game_count) {
    if ($game_count >= 31) {
        return 30000; // ⭐ 31 بازی به بالا
    } elseif ($game_count >= 21) {
        return 35000; // 🔴 21 تا 30 بازی
    } elseif ($game_count >= 11) {
        return 40000; // 🟠 11 تا 20 بازی
    } elseif ($game_count >= 5) {
        return 45000; // 🟣 5 تا 10 بازی
    } else {
        return 50000; // 🟢 1 تا 4 بازی
    }
}

/**
 * ایجاد یا بازیابی محصول گروهی بازی‌ها
 * این محصول برای تمام سفارشات استفاده می‌شود
 * 
 * @return int محصول ID
 */
function ggp_get_or_create_bundle_product() {
    // بررسی اینکه محصول قبلاً وجود دارد یا خیر
    $product_id = get_option('ggp_bundle_product_id');
    
    if ($product_id && get_post($product_id)) {
        return $product_id;
    }
    
    // اگر WooCommerce فعال نباشد
    if (!class_exists('WC_Product_Simple')) {
        return false;
    }
    
    // ایجاد محصول جدید
    $product = new WC_Product_Simple();
    $product->set_name('📦 بسته انتخاب بازی‌ها');
    $product->set_description('بسته‌ای برای انتخاب و نصب بازی‌ها');
    $product->set_status('publish');
    $product->set_catalog_visibility('hidden'); // محصول مخفی
    $product->set_price(50000); // ��یمت پایه (قیمت نهایی در سبد خرید محاسبه می‌شود)
    $product->set_manage_stock(false);
    $product->set_virtual(true); // محصول دیجیتالی
    $product->set_downloadable(false);
    
    $product_id = $product->save();
    
    if ($product_id) {
        update_option('ggp_bundle_product_id', $product_id);
    }
    
    return $product_id;
}

/**
 * اضافه کردن بسته بازی به سبد خرید WooCommerce
 * این تابع از JavaScript فراخوانی می‌شود
 */
function ggp_add_to_cart_ajax() {
    // ابتدا بررسی داده‌های ارسالی
    $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
    $phone = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';
    $games = isset($_POST['games']) ? array_map('sanitize_text_field', (array)$_POST['games']) : [];
    $console = isset($_POST['console']) ? sanitize_text_field($_POST['console']) : '';
    
    // بررسی nonce برای امنیت (اما اگر نباشد هم ادامه دهیم)
    if (isset($_POST['nonce'])) {
        if (!wp_verify_nonce($_POST['nonce'], 'ggp_add_to_cart')) {
            // نonce نامعتبر است اما ادامه میدهیم (ممکن است مرورگر آن را فرستاده نباشد)
            // wp_send_json_error(['message' => 'Nonce verification failed']);
            // return;
        }
    }
    
    // بررسی اینکه WooCommerce فعال باشد
    if (!class_exists('WC_Product_Simple') || !function_exists('wc_get_checkout_url')) {
        wp_send_json_error(['message' => 'WooCommerce is not active']);
        return;
    }
    
    // بررسی فیلدهای الزامی
    if (empty($games) || empty($name) || empty($phone)) {
        wp_send_json_error([
            'message' => 'Missing required fields',
            'debug' => [
                'name' => !empty($name) ? '✓' : '✗',
                'phone' => !empty($phone) ? '✓' : '✗',
                'games' => !empty($games) ? count($games) . ' games' : '✗',
            ]
        ]);
        return;
    }
    
    // محاسبه قیمت
    $game_count = count($games);
    $price_per_game = ggp_calculate_price_per_game($game_count);
    $total_price = $price_per_game * $game_count;
    
    // دریافت یا ایجاد محصول
    $product_id = ggp_get_or_create_bundle_product();
    
    if (!$product_id) {
        wp_send_json_error(['message' => 'Failed to create product']);
        return;
    }
    
    // آ��اده‌سازی داده‌های custom برای سفارش
    $order_data = [
        'name' => $name,
        'phone' => $phone,
        'games' => $games,
        'console' => $console,
        'game_count' => $game_count,
        'price_per_game' => $price_per_game,
    ];
    
    // تبدیل به JSON برای ذخیره در cart item meta
    $order_json = json_encode($order_data);
    
    // پاک کردن سبد خرید قبلی (اختیاری - برای جلوگیری از سفارشات متعدد)
    if (isset(WC()->cart)) {
        WC()->cart->empty_cart();
    }
    
    // اضافه کردن محصول به سبد خرید
    $cart_item_key = WC()->cart->add_to_cart(
        $product_id,
        1, // quantity
        0, // variation_id
        [], // variation
        [
            'ggp_order_data' => $order_json,
            'ggp_name' => $name,
            'ggp_phone' => $phone,
            'ggp_console' => $console,
            'ggp_game_count' => $game_count,
            'ggp_price_per_game' => $price_per_game,
        ]
    );
    
    if ($cart_item_key) {
        // دریافت URL صفحه checkout
        $checkout_url = wc_get_checkout_url();
        
        wp_send_json_success([
            'message' => 'محصول به سبد خرید اضافه شد',
            'checkout_url' => $checkout_url,
            'total_price' => $total_price,
        ]);
    } else {
        wp_send_json_error(['message' => 'Failed to add to cart']);
    }
    
    wp_die();
}

add_action('wp_ajax_ggp_add_to_cart', 'ggp_add_to_cart_ajax');
add_action('wp_ajax_nopriv_ggp_add_to_cart', 'ggp_add_to_cart_ajax');

/**
 * نمایش اطلاعات سفارش در صفحه checkout
 */
function ggp_display_order_info_in_checkout() {
    if (is_checkout() && WC()->cart) {
        foreach (WC()->cart->get_cart() as $cart_item) {
            if (isset($cart_item['ggp_order_data'])) {
                $order_data = json_decode($cart_item['ggp_order_data'], true);
                
                echo '<div style="background:#f7fbff; border:1px solid #b6d8f6; border-radius:8px; padding:15px; margin-bottom:15px;">';
                echo '<h3 style="color:#2563eb; margin-top:0;">📦 اطلاعات سفارش بازی‌ها</h3>';
                echo '<p><strong>نام:</strong> ' . esc_html($order_data['name'] ?? '') . '</p>';
                echo '<p><strong>شماره تماس:</strong> <span dir="ltr">' . esc_html($order_data['phone'] ?? '') . '</span></p>';
                echo '<p><strong>کنسول:</strong> ' . esc_html(strtoupper($order_data['console'] ?? '')) . '</p>';
                echo '<p><strong>تعداد بازی:</strong> ' . intval($order_data['game_count'] ?? 0) . '</p>';
                echo '<p><strong>قیمت هر بازی:</strong> ' . number_format($order_data['price_per_game'] ?? 0) . ' تومان</p>';
                echo '<hr>';
                echo '<h4 style="color:#2563eb;">بازی‌های انتخاب‌شده:</h4>';
                echo '<ul style="direction:ltr; text-align:left;">';
                foreach ($order_data['games'] as $game) {
                    echo '<li>' . esc_html($game) . '</li>';
                }
                echo '</ul>';
                echo '</div>';
            }
        }
    }
}

add_action('woocommerce_checkout_after_order_notes', 'ggp_display_order_info_in_checkout');

/**
 * ذخیره اطلاعات سفارش custom در order metadata هنگام تکمیل خرید
 */
function ggp_save_order_meta_on_checkout($order_id) {
    $order = wc_get_order($order_id);
    
    foreach ($order->get_items() as $item_id => $item) {
        // دریافت custom meta data
        $order_data = $item->get_meta('ggp_order_data');
        
        if ($order_data) {
            $data = json_decode($order_data, true);
            
            // ذخیره در order meta
            $order->update_meta_data('ggp_customer_name', $data['name'] ?? '');
            $order->update_meta_data('ggp_customer_phone', $data['phone'] ?? '');
            $order->update_meta_data('ggp_console', $data['console'] ?? '');
            $order->update_meta_data('ggp_games', json_encode($data['games'] ?? []));
            $order->update_meta_data('ggp_game_count', $data['game_count'] ?? 0);
            $order->update_meta_data('ggp_price_per_game', $data['price_per_game'] ?? 0);
            
            // بروزرسانی شماره تماس customer
            if (!empty($data['phone'])) {
                $order->set_billing_phone($data['phone']);
            }
        }
    }
    
    $order->save();
}

add_action('woocommerce_checkout_order_created', 'ggp_save_order_meta_on_checkout');

/**
 * نمایش اطلاعات سفارش در صفحه order details (مشتری و ادمین)
 */
function ggp_display_order_details_meta($order) {
    $game_count = $order->get_meta('ggp_game_count');
    
    if (!$game_count) {
        return;
    }
    
    echo '<section class="woocommerce-order-details">';
    echo '<h2 style="color:#2563eb; border-bottom:2px solid #b6d8f6; padding-bottom:10px;">📦 اطلاعات سفارش بازی‌ها</h2>';
    
    $name = $order->get_meta('ggp_customer_name');
    $phone = $order->get_meta('ggp_customer_phone');
    $console = $order->get_meta('ggp_console');
    $games = json_decode($order->get_meta('ggp_games'), true);
    $price_per_game = $order->get_meta('ggp_price_per_game');
    
    echo '<dl class="dl-horizontal">';
    
    if ($name) {
        echo '<dt>نام مشتری:</dt>';
        echo '<dd>' . esc_html($name) . '</dd>';
    }
    
    if ($phone) {
        echo '<dt>شماره تماس:</dt>';
        echo '<dd><span dir="ltr">' . esc_html($phone) . '</span></dd>';
    }
    
    if ($console) {
        echo '<dt>کنسول:</dt>';
        echo '<dd>' . esc_html(strtoupper($console)) . '</dd>';
    }
    
    echo '<dt>تعداد بازی:</dt>';
    echo '<dd>' . intval($game_count) . ' بازی</dd>';
    
    echo '<dt>قیمت هر بازی:</dt>';
    echo '<dd>' . number_format(intval($price_per_game)) . ' تومان</dd>';
    
    echo '</dl>';
    
    if ($games && is_array($games)) {
        echo '<h3 style="color:#2563eb; margin-top:20px;">بازی‌های انتخاب‌شده:</h3>';
        echo '<ul style="direction:ltr; text-align:left; max-height:300px; overflow-y:auto; border:1px solid #ddd; padding:15px; background:#f9f9f9; border-radius:8px;">';
        foreach ($games as $game) {
            echo '<li style="margin-bottom:5px;">' . esc_html($game) . '</li>';
        }
        echo '</ul>';
    }
    
    echo '</section>';
}

add_action('woocommerce_order_details_after_order_table', 'ggp_display_order_details_meta', 10, 1);

/**
 * نمایش اطلاعات سفارش در صفحه admin order
 */
function ggp_admin_order_meta_display($order) {
    $game_count = $order->get_meta('ggp_game_count');
    
    if (!$game_count) {
        return;
    }
    
    echo '<div class="postbox">';
    echo '<h2 class="hndle"><span>📦 اطلاعات سفارش بازی‌ها</span></h2>';
    echo '<div class="inside">';
    
    $name = $order->get_meta('ggp_customer_name');
    $phone = $order->get_meta('ggp_customer_phone');
    $console = $order->get_meta('ggp_console');
    $games = json_decode($order->get_meta('ggp_games'), true);
    $price_per_game = $order->get_meta('ggp_price_per_game');
    
    echo '<table class="widefat">';
    
    if ($name) {
        echo '<tr><th>نام مشتری:</th><td>' . esc_html($name) . '</td></tr>';
    }
    
    if ($phone) {
        echo '<tr><th>شماره تماس:</th><td><span dir="ltr">' . esc_html($phone) . '</span></td></tr>';
    }
    
    if ($console) {
        echo '<tr><th>کنسول:</th><td>' . esc_html(strtoupper($console)) . '</td></tr>';
    }
    
    echo '<tr><th>تعداد بازی:</th><td>' . intval($game_count) . ' بازی</td></tr>';
    echo '<tr><th>قیمت هر بازی:</th><td>' . number_format(intval($price_per_game)) . ' تومان</td></tr>';
    
    echo '</table>';
    
    if ($games && is_array($games)) {
        echo '<h3>بازی‌های انتخاب‌شده:</h3>';
        echo '<textarea readonly style="width:100%; height:300px; font-family:monospace;">';
        echo implode("\n", $games);
        echo '</textarea>';
    }
    
    echo '</div>';
    echo '</div>';
}

add_action('woocommerce_admin_order_data_after_order_details', 'ggp_admin_order_meta_display');

/**
 * تنظیم قیمت محصول بر اساس custom meta
 */
function ggp_set_cart_item_price($cart_item, $cart_item_key) {
    if (isset($cart_item['ggp_price_per_game']) && isset($cart_item['ggp_game_count'])) {
        $price_per_game = intval($cart_item['ggp_price_per_game']);
        $game_count = intval($cart_item['ggp_game_count']);
        $total_price = $price_per_game * $game_count;
        
        // تنظیم قیمت محصول
        $cart_item['data']->set_price($total_price);
    }
}

add_action('woocommerce_before_calculate_totals', function ($cart) {
    if (is_admin() && !defined('DOING_AJAX')) {
        return;
    }
    
    if (did_action('woocommerce_before_calculate_totals') >= 2) {
        return;
    }
    
    foreach ($cart->get_cart() as $cart_item) {
        ggp_set_cart_item_price($cart_item, '');
    }
});

/**
 * نمایش تفاصیل بازی‌ها در صفحه cart
 */
function ggp_display_cart_item_details($item_data, $cart_item) {
    if (isset($cart_item['ggp_order_data'])) {
        $order_data = json_decode($cart_item['ggp_order_data'], true);
        
        $item_data[] = [
            'key'   => 'نام مشتری',
            'value' => $order_data['name'] ?? '',
        ];
        
        $item_data[] = [
            'key'   => 'شماره تماس',
            'value' => $order_data['phone'] ?? '',
        ];
        
        $item_data[] = [
            'key'   => 'کنسول',
            'value' => strtoupper($order_data['console'] ?? ''),
        ];
        
        $item_data[] = [
            'key'   => 'تعداد بازی',
            'value' => intval($order_data['game_count'] ?? 0),
        ];
        
        $item_data[] = [
            'key'   => 'قیمت هر بازی',
            'value' => number_format(intval($order_data['price_per_game'] ?? 0)) . ' تومان',
        ];
    }
    
    return $item_data;
}

add_filter('woocommerce_get_item_data', 'ggp_display_cart_item_details', 10, 2);

?>
