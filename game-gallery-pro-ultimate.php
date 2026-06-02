<?php
/*
Plugin Name: Game Gallery Pro Ultimate
Description: افزونه ثبت و انتخاب بازی، گالری، ارسال به واتساپ و تلگرام، ایمپورت خودکار پوشه‌ها
Version: 5.3
Author: شکوهی ❤️
Last Updated: 2025-06-02 14:00:00
Updated By: armiatr
*/

defined('ABSPATH') || exit;

require_once plugin_dir_path(__FILE__) . 'posttype-games.php';
require_once plugin_dir_path(__FILE__) . 'shortcode-gallery.php';
require_once plugin_dir_path(__FILE__) . 'telegram-send.php';
require_once plugin_dir_path(__FILE__) . 'admin-import.php';
require_once plugin_dir_path(__FILE__) . 'admin-orders.php';
require_once plugin_dir_path(__FILE__) . 'admin-stats.php';
include_once plugin_dir_path(__FILE__) . 'admin-customers.php';
require_once plugin_dir_path(__FILE__) . 'ajax-categories.php';
include_once plugin_dir_path(__FILE__) . 'admin-messages.php';
include_once plugin_dir_path(__FILE__) . 'admin-reminders.php';
require_once plugin_dir_path(__FILE__) . 'admin-customer-profile.php';
require_once plugin_dir_path(__FILE__) . 'woocommerce-integration.php';

add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style('ggp-style', plugin_dir_url(__FILE__) . 'style.css');
    wp_enqueue_script('ggp-script', plugin_dir_url(__FILE__) . 'script.js', ['jquery'], null, true);

    wp_localize_script('ggp-script', 'ggp_ajax', [
        'ajax_url'     => admin_url('admin-ajax.php'),
        'site_url'     => site_url(),
        'add_to_cart_nonce' => wp_create_nonce('ggp_add_to_cart'),
        'whatsapp_url' => 'https://wa.me/989900149809?text=سلام، لیست بازی‌هایم را انتخاب کردم.'
    ]);
});

add_action('wp_ajax_ggp_save_order', 'ggp_save_order');
add_action('wp_ajax_nopriv_ggp_save_order', 'ggp_save_order');

function ggp_save_order() {
    $name = sanitize_text_field($_POST['name']);
    $phone = sanitize_text_field($_POST['phone']);
    $games = array_map('sanitize_text_field', $_POST['games'] ?? []);
    $console = sanitize_text_field($_POST['console'] ?? '');

    // اعتبارسنجی شماره موبایل
    $clean_phone = preg_replace('/[^0-9]/', '', $phone);
    if (strlen($clean_phone) !== 11 || !preg_match('/^09\d{9}$/', $clean_phone)) {
        wp_send_json_error(['message' => 'شماره موبایل نامعتبر است. شماره باید با 09 شروع شود و 11 رقمی باشد.']);
        return;
    }

    if (empty($games)) {
        wp_send_json_error(['message' => 'هیچ بازی‌ای انتخاب نشده است.']);
        return;
    }

    $time = current_time('Y-m-d H:i');
    $console_name = strtoupper($console);
    $formatted_name = $name . ' [' . $console_name . ']';

    $orders = get_option('ggp_orders', []);
    $new_order = [
        'order_id' => uniqid('order_', true),
        'name'     => $formatted_name,
        'phone'    => $clean_phone,
        'games'    => $games,
        'console'  => $console,
        'time'     => $time,
        'date'     => date('Y-m-d'),
        'status'   => 'سفارش ثبت شده',
        'note'     => '',
        'price'    => '',
        'source'   => 'WooCommerce'
    ];

    array_unshift($orders, $new_order);
    update_option('ggp_orders', $orders);
    do_action('ggp_order_saved', $new_order);
    wp_send_json_success(['message' => 'سفارش با موفقیت ثبت شد.']);
}

add_action('wp_ajax_ggp_telegram', 'ggp_telegram');
add_action('wp_ajax_nopriv_ggp_telegram', 'ggp_telegram');

function ggp_telegram() {
    $name = sanitize_text_field($_POST['name']);
    $phone = sanitize_text_field($_POST['phone']);
    $games = array_map('sanitize_text_field', $_POST['games'] ?? []);
    $console = sanitize_text_field($_POST['console'] ?? '');

    $clean_phone = preg_replace('/[^0-9]/', '', $phone);
    if (strlen($clean_phone) !== 11 || !preg_match('/^09\d{9}$/', $clean_phone)) {
        wp_send_json_error(['message' => 'شماره موبایل نامعتبر است']);
        return;
    }

    $filename = sprintf('%s-%s-%s.txt', 
        $clean_phone,
        str_replace(' ', '_', $name),
        strtoupper($console)
    );
    $filepath = wp_upload_dir()['basedir'] . '/' . $filename;

    $content = implode("\n", $games) . "\n\n" . 
               "----------------------------------------\n" .
               "📞 ارتباط با آیدی زیر در تلگرام:\n" .
               "t.me/wp101\n\n" .
               "🎮 کنسول: " . strtoupper($console) . "\n\n" .
               "🌐 سایت: www.101bazi.ir\n\n" .
               "📣 101 بازی ✪ نصب بازی ps3 کپی خور ✌️✌️ بازی کپی خور ps4 ➽ لیست بازی های نینتندو سوییچ ✪ بازیهای ایکس باکس\n\n" .
               "❗️ لطفا توجه بفرمایید بازیها به دو صورت برای شما ارسال میشود.\n" .
               "❗️ فایل بازیهای زیرنویس فارسی ps4 از داخل تلگرام برای شما ارسال خواهد شد.\n" .
               "❗️ دیگر بازیها برای تمامی کنسول ها فقط به صورت کپی بروی هارد ( هارد از شما و یا هارد از ما) قابل ریختن و ارسال است.\n";
			   
    file_put_contents($filepath, "\xEF\xBB\xBF" . $content);
    if (function_exists('ggp_send_to_telegram')) {
        ggp_send_to_telegram($filepath);
    }
    wp_send_json_success();
    }
    ?>
