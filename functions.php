<?php

if (!function_exists('removeIndexPhp')) {
    function removeIndexPhp()
    {
        if (strpos('https://'.Server::get('HTTP_HOST').Server::get('REQUEST_URI'), 'index.php')) {
            Redirect::to(str_replace('index.php', '', 'https://'.Server::get('HTTP_HOST').Server::get('REQUEST_URI')));
        }
    }
}

if (!function_exists('generateRelativeRedirect')) {
    function generateRelativeRedirect($args = [], $placeholder = false)
    {
        // The $placeholder parameter is due to legacy code reasons, changing it at this time will do nothing
        $redirectDir = '';
        for ($i = 0; $i < count($args); ++$i) {
            $redirectDir .= '../';
        }

        return $redirectDir;
    }
}

if (!function_exists('parseUrl')) {
    function parseUrl()
    {
        $url = str_replace('index.php', '', Server::get('PHP_SELF'));
        $url = str_replace($url, '', Server::get('REQUEST_URI'));
        $url = explode('/', $url);
        $args = [];
        foreach ($url as $val) {
            if ($val == '') {
                continue;
            }
            $args[] = urldecode($val);
        }

        return $args;
    }
}

if (!function_exists('Coupons_onCouponRedeem')) {
    function Coupons_onCouponRedeem($couponData)
    {
        // This function is called when a coupon is redeemed
        // You should not edit it in this file, but rather add it to your custom_functions.php file in your usersc folder
        // You can use it for any logic you want before we display the coupon success page
    }
}

function Coupons_getCoupons($coupon = null)
{
    global $user, $db;
    $return = [];
    $return['state'] = false;

    $userId = 1;
    if ($user->isLoggedIn()) {
        $userId = $user->data()->id;
    }

    if ($coupon == null) {
        $db->query('SELECT c.* ,GROUP_CONCAT(cp.fkPermissionID) PermissionIds ,GROUP_CONCAT(crp.fkPermissionID) RequiredPermissionIds ,COUNT(DISTINCT ch.kCouponHistoryID) CouponUseCount FROM coupons c LEFT JOIN coupons_permissions cp ON cp.fkCouponID = c.kCouponID LEFT JOIN coupons_required_permissions crp ON crp.fkCouponID = c.kCouponID LEFT JOIN coupons_history ch on c.kCouponID = ch.fkCouponID GROUP BY c.kCouponID');
        if (!$db->error()) {
            if ($db->count() > 0) {
                $return['state'] = true;
                $return['data'] = $db->results();

                return $return;
            } else {
                $return['error'] = 'db_no_results';

                return $return;
            }
        } else {
            logger($userId, 'Coupons_getCoupons', 'Failed to retrieve coupons', json_encode(['ERROR' => $db->errorString()]));
            $return['error'] = 'db_error';

            return $return;
        }
    } else {
        $db->query('SELECT c.* ,GROUP_CONCAT(cp.fkPermissionID) PermissionIds , GROUP_CONCAT(crp.fkPermissionID) RequiredPermissionIds ,COUNT(DISTINCT ch.kCouponHistoryID) CouponUseCount FROM coupons c LEFT JOIN coupons_permissions cp ON cp.fkCouponID = c.kCouponID LEFT JOIN coupons_required_permissions crp ON crp.fkCouponID = c.kCouponID LEFT JOIN coupons_history ch on c.kCouponID = ch.fkCouponID WHERE c.Coupon = ? GROUP BY c.kCouponID', [$coupon]);
        if (!$db->error()) {
            $count = $db->count();
            if ($count == 1) {
                $return['state'] = true;
                $return['data'] = $db->first();

                return $return;
            } elseif ($count > 1) {
                $return['error'] = 'db_too_many_results';

                return $return;
            } else {
                $return['error'] = 'db_no_results';

                return $return;
            }
        } else {
            logger($userId, 'Coupons_getCoupons', "Failed to retrieve coupon {$coupon}", json_encode(['ERROR' => $db->errorString()]));
            $return['error'] = 'db_error';

            return $return;
        }
    }

    $return['error'] = 'request_unhandled';

    return $return;
}

function Coupons_generateCouponCode($length = 8)
{
    global $db, $user;
    $return = [];
    $return['state'] = false;

    $userId = 1;
    if ($user->isLoggedIn()) {
        $userId = $user->data()->id;
    }

    $couponCode = null;
    $attempts = 0;

    while ($couponCode === null && $attempts < 5) {
        ++$attempts;

        $attempted = strtoupper(randomstring($length));
        $db->query('SELECT Coupon Number FROM coupons WHERE Coupon = ?', [$attempted]);
        if (!$db->error()) {
            if ($db->count() == 0) {
                $couponCode = $attempted;
            }
        } else {
            logger($userId, 'generateCouponCode', "Failed to check Coupon Code {$attempted} against DB", json_encode(['ERROR' => $db->errorString()]));
        }
    }

    if ($couponCode !== null) {
        $return['state'] = true;
        $return['data'] = $couponCode;
    } else {
        $return['error'] = 'failed_to_generate';
    }

    return $return;
}

function Coupons_expireCoupon($coupon = null, $all = false)
{
    global $db, $user;
    $return = [];
    $return['state'] = false;

    if (!$user->isLoggedIn()) {
        $return['error'] = 'not_logged_in';

        return $return;
    }

    if ($coupon == null && $all) {
        $db->query('UPDATE coupons SET CouponExpirationDate = NOW() WHERE CouponExpirationDate IS NULL OR CouponExpirationDate > NOW()');
        if (!$db->error()) {
            $count = $db->count();
            if ($count == 1) {
                logger($user->data()->id, 'Coupons_expireCoupon', "Expired {$count} Coupon");
            } else {
                logger($user->data()->id, 'Coupons_expireCoupon', "Expired {$count} Coupons");
            }
            $return['state'] = true;
            $return['data'] = $count;

            return $return;
        } else {
            logger($user->data()->id, 'Coupons_expireCoupon', 'Failed to Expire Coupons', json_encode(['ERROR' => $db->errorString()]));
            $return['error'] = 'db_error';

            return $return;
        }
    }

    if ($coupon != null) {
        $db->query('UPDATE coupons SET CouponExpirationDate = NOW() WHERE Coupon = ?', [$coupon]);
        if (!$db->error()) {
            logger($user->data()->id, 'Coupons_expireCoupon', "Expired Coupon {$coupon}");
            $return['state'] = true;
            $return['data'] = $db->count();

            return $return;
        } else {
            logger($user->data()->id, 'Coupons_expireCoupon', "Failed to Expire Coupon {$coupon}", json_encode(['ERROR' => $db->errorString()]));
            $return['error'] = 'db_error';

            return $return;
        }
    }

    $return['error'] = 'request_unhandled';

    return $return;
}

function Coupons_validateCoupon($coupon)
{
    global $db, $user;
    $return = [];
    $return['state'] = false;

    if (!$user->isLoggedIn()) {
        $return['error'] = 'not_logged_in';

        return $return;
    }

    $db->query('SELECT c.*, GROUP_CONCAT(cp.fkPermissionID) PermissionIds, GROUP_CONCAT(crp.fkPermissionID) RequiredPermissionIds FROM coupons c LEFT JOIN coupons_permissions cp ON cp.fkCouponID = c.kCouponID LEFT JOIN coupons_required_permissions crp ON crp.fkCouponID = c.kCouponID LEFT JOIN(SELECT fkCouponID, COUNT(ch.kCouponHistoryID) CouponUseCount FROM coupons_history ch GROUP BY ch.fkCouponID) cu ON cu.fkCouponID = c.kCouponID WHERE c.Coupon = ? AND (c.CouponExpirationDate IS NULL OR c.CouponExpirationDate > NOW()) AND (c.CouponUseLimit IS NULL OR cu.CouponUseCount IS NULL OR c.CouponUseLimit > cu.CouponUseCount)', [$coupon]);
    if (!$db->error()) {
        $count = $db->count();
        if ($count == 1) {
            $couponData = $db->first();
            if ($couponData->kCouponID == null) {
                $return['error'] = 'db_no_results';

                return $return;
            }
            $couponRequiredPermissions = explode(',', $couponData->RequiredPermissionIds);
            if ($couponData->RequiredPermissionIds !== null && count($couponRequiredPermissions) > 0) {
                if (hasPerm($couponRequiredPermissions, null, false)) {
                    $return['state'] = true;
                    $return['data'] = $couponData;

                    return $return;
                } else {
                    $return['error'] = 'missing_permission';

                    return $return;
                }
            } else {
                $return['state'] = true;
                $return['data'] = $couponData;

                return $return;
            }
        } elseif ($count > 1) {
            $return['error'] = 'db_too_many_results';

            return $return;
        } else {
            $return['error'] = 'db_no_results';

            return $return;
        }
    } else {
        logger($user->data()->id, 'Coupons_validateCoupon', "Failed to validate coupon {$coupon}", json_encode(['ERROR' => $db->errorString()]));
        $return['error'] = 'db_error';

        return $return;
    }

    $return['error'] = 'request_unhandled';

    return $return;
}

function Coupons_trackReward($rewardType, $rewardId)
{
    global $db, $user;
    $return = [];
    $return['state'] = false;

    if (!$user->isLoggedIn()) {
        $return['error'] = 'not_logged_in';

        return $return;
    }

    $fields = [
    'RewardType' => $rewardType,
    'RewardId' => $rewardId,
  ];

    $db->insert('coupons_rewards', $fields);
    if (!$db->error()) {
        logger($user->data()->id, 'Coupons_trackReward', 'Tracked Reward', json_encode(['DATA' => $fields]));
        $return['state'] = true;
    } else {
        logger($user->data()->id, 'Coupons_trackReward', 'Failed to Track Reward', json_encode(['DATA' => $fields, 'ERROR' => $db->errorString()]));
        $return['state'] = false;
        $return['error'] = 'db_error';
    }

    return $return;
}
