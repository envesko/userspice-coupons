<?php

require_once 'init.php';
// Called only when the plugin is actively DELETED (not on deactivate). Drops all coupon data.
if (in_array($user->data()->id, $master_account)) {
    $db = DB::getInstance();
    include 'plugin_info.php';

    foreach (['coupons_history', 'coupons_permissions', 'coupons_required_permissions', 'coupons_rewards', 'coupons'] as $table) {
        $db->query('DROP TABLE IF EXISTS ' . $table);
    }
    logger($user->data()->id, 'USPlugins', $plugin_name . ' tables dropped on delete');
}
