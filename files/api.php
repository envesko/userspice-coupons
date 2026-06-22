<?php

require_once '../users/init.php';
$db = DB::getInstance();
header('Content-Type: application/json');

function handleError($errorCode, $errorMsg = null)
{
    http_response_code($errorCode);
    $data['state'] = 'error';
    $data['error'] = $errorMsg;
    exit(json_encode($data));
}

function handleSuccess($successCode = 200, $metadata = [])
{
    http_response_code($successCode);
    $data['state'] = 'success';
    $data['data'] = $metadata;
    exit(json_encode($data));
}

if (!$user->isLoggedIn()) {
    handleError(401, 'not_logged_in');
}

if (!hasPerm(2)) {
    handleError(401, 'permission_denied');
}

$csrf = Input::get('csrf');
if (!Token::check($csrf)) {
    handleError(401, 'no_csrf_token');
}

$coupon_id = Input::get('coupon_id');
$coupon_code = Input::get('coupon_code');

$request_type = Server::get('REQUEST_METHOD');

if ($request_type === 'GET') {
    if ($coupon_code != null) {
        $coupon = Coupons_getCoupons($coupon_code);
        if ($coupon['state']) {
            handleSuccess(200, $coupon['data']);
        } else {
            switch ($coupon['error']) {
                case 'db_no_results':
                    handleError(404, 'not_found');
                    break;
                case 'db_too_many_results':
                    handleError(500, 'too_many_results');
                    break;
                default:
                    handleError(500, 'internal_error');
                    break;
            }
        }
    }

    handleError(501, 'request_unhandled');
}

handleError(500, 'request_unhandled');
exit;
