<?php

require_once 'init.php';
if (in_array($user->data()->id, $master_account)) {
    $db = DB::getInstance();
    include 'plugin_info.php';

    $coupons_dir = "{$abs_us_root}{$us_url_root}coupons/";
    if (!is_dir($coupons_dir)) {
        logger($user->data()->id, 'Coupons', '[UNINSTALL] [FILES] [ERROR] Coupons Directory does not exist');
    } else {
        $files = array_diff(scandir($coupons_dir), ['..', '.']);
        foreach ($files as $file) {
            if (unlink("{$coupons_dir}{$file}")) {
                logger($user->data()->id, 'Coupons', "[UNINSTALL] [FILES] [SUCCESS] Removed {$file}");
            } else {
                logger($user->data()->id, 'Coupons', "[UNINSTALL] [FILES] [ERROR] Failed to Remove {$file}");
            }
        }
    }

    if (rmdir($coupons_dir)) {
        logger($user->data()->id, 'Coupons', '[UNINSTALL] [FILES] [SUCCESS] Removed Coupons Directory');
    } else {
        logger($user->data()->id, 'Coupons', '[UNINSTALL] [FILES] [ERROR] Failed to Remove Coupons Directory');
    }

    $queries = [
      [
        'Description' => 'Delete coupons table',
        'SQL' => 'DROP TABLE coupons',
      ],
      [
        'Description' => 'Delete coupons_history table',
        'SQL' => 'DROP TABLE coupons_history',
      ],
      [
        'Description' => 'Delete coupons_permissions table',
        'SQL' => 'DROP TABLE coupons_permissions',
      ],
      [
        'Description' => 'Delete coupons_rewards table',
        'SQL' => 'DROP TABLE coupons_rewards',
      ],
    ];

    foreach ($queries as $query) {
        $db->query($query['SQL']);
        if (!$db->error()) {
            logger($user->data()->id, 'USPlugins', "[Coupons] [SUCCESS] {$query['Description']}");
        } else {
            logger($user->data()->id, 'USPlugins', "[Coupons] [WARNING] [Database Error] {$query['Description']}", json_encode(['ERROR' => $db->errorString()]));
        }
    }

    $db->query('DELETE FROM us_plugins WHERE plugin = ?', [$plugin_name]);
    deRegisterHooks($plugin_name);
    if (!$db->error()) {
        err($plugin_name.' uninstalled');
        logger($user->data()->id, 'USPlugins', $plugin_name.' uninstalled');
    } else {
        err($plugin_name.' was not uninstalled');
        logger($user->data()->id, 'USPlugins', 'Failed to uninstall Plugin, Error: '.$db->errorString());
    }
}
