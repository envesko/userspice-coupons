<?php

require_once 'init.php';
if (in_array($user->data()->id, $master_account)) {
    $db = DB::getInstance();
    include 'plugin_info.php';

    $dirExists = false;
    $coupons_dir = "{$abs_us_root}{$us_url_root}coupons/";
    $plugin_dir = "{$abs_us_root}{$us_url_root}usersc/plugins/{$plugin_name}/files/";

    if (file_exists($coupons_dir)) {
        $dirExists = true;
    } else {
        if (mkdir($coupons_dir, 0777, true)) {
            $dirExists = true;
            logger($user->data()->id, 'Coupons', '[INSTALL] [FILES] [SUCCESS] Created Coupons Directory');
        } else {
            logger($user->data()->id, 'Coupons', '[INSTALL] [FILES] [ERROR] Failed to Create Coupons Directory');
        }
    }

    if ($dirExists) {
        $files = array_diff(scandir($plugin_dir), ['..', '.']);
        foreach ($files as $file) {
            $file_source_path = "{$plugin_dir}{$file}";
            $file_dest_path = "{$coupons_dir}{$file}";
            if (copy($file_source_path, $file_dest_path)) {
                logger($user->data()->id, 'Coupons', "[INSTALL] [FILES] [SUCCESS] Copied {$file}");
            } else {
                logger($user->data()->id, 'Coupons', "[INSTALL] [FILES] [ERROR] Failed to Copy {$file}");
            }
        }
    } else {
        logger($user->data()->id, 'Coupons', '[INSTALL] [FILES] [ERROR] Cannot Copy Files, Missing Directory');
    }

    $queries = [
      [
        'Description' => 'Create coupons table',
        'SQL' => 'CREATE TABLE coupons( kCouponID int(11) UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT, Coupon varchar(255) NOT NULL, CouponType varchar(255) NULL, CouponGeneratedByUserId int(11) UNSIGNED NOT NULL, CouponGeneratedDate datetime NOT NULL DEFAULT CURRENT_TIMESTAMP(), CouponUseLimit int(11) UNSIGNED NULL, CouponExpirationDate datetime NULL)',
      ],
      [
        'Description' => 'Create coupons_history table',
        'SQL' => 'CREATE TABLE coupons_history( kCouponHistoryID int(11) UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT, fkCouponID int(11) UNSIGNED NOT NULL, fkUserID int(11) UNSIGNED NOT NULL, CouponHistoryDate datetime NOT NULL DEFAULT CURRENT_TIMESTAMP())',
      ],
      [
        'Description' => 'Create coupons_permissions table',
        'SQL' => 'CREATE TABLE coupons_permissions( kCouponPermissionID int(11) UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT, fkCouponID int(11) UNSIGNED NOT NULL, fkPermissionID int(11) UNSIGNED NOT NULL)',
      ],
      [
        'Description' => 'Create coupons_required_permissions table',
        'SQL' => 'CREATE TABLE coupons_required_permissions( kCouponRequiredPermissionID int(11) UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT, fkCouponID int(11) UNSIGNED NOT NULL, fkPermissionID int(11) UNSIGNED NOT NULL)',
      ],
      [
        'Description' => 'Create coupons_rewards table',
        'SQL' => 'CREATE TABLE coupons_rewards( kCouponRewardID int(11) UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT, RewardGiven datetime NOT NULL DEFAULT CURRENT_TIMESTAMP(), RewardRevoked datetime NULL, RewardType varchar(255) NULL, RewardId varchar(255) NULL)',
      ],
    ];

    foreach ($queries as $query) {
        $db->query($query['SQL']);
        if (!$db->error()) {
            logger($user->data()->id, 'Coupons', "[INSTALL] [SQL] [SUCCESS] {$query['Description']}");
        } else {
            logger($user->data()->id, 'Coupons', "[INSTALL] [SQL] [WARNING] {$query['Description']} failed", json_encode(['ERROR' => $db->errorString()]));
        }
    }

    $hooks = [];
    registerHooks($hooks, $plugin_name);

    $check = $db->query('SELECT * FROM us_plugins WHERE plugin = ?', [$plugin_name])->count();
    if ($check > 0) {
        err($plugin_name.' has already been installed!');
    } else {
        $fields = [
            'plugin' => $plugin_name,
            'status' => 'installed',
        ];
        $db->insert('us_plugins', $fields);
        if (!$db->error()) {
            err($plugin_name.' installed');
            logger($user->data()->id, 'USPlugins', $plugin_name.' installed');
        } else {
            err($plugin_name.' was not installed');
            logger($user->data()->id, 'USPlugins', 'Failed to to install plugin, Error: '.$db->errorString());
        }
    }
}
