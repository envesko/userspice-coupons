<?php
require_once '../users/init.php';

// Fail gracefully if the plugin is not active (its functions.php is not loaded). Checking the
// function directly is more reliable than pluginActive(), which can report active from the DB while
// plugins.ini still has the plugin disabled, leaving functions unloaded.
if (!function_exists('Coupons_getCoupons')) {
    if (function_exists('usError')) {
        usError('The Coupons plugin is not currently active.');
    }
    Redirect::to($us_url_root);
}

$args = parseUrl();
$page = $args[0] ?? null;

if ($page == 'api') {
    require_once 'api.php';
    exit;
}

if (!securePage(Server::get('PHP_SELF'))) {
    exit;
}

require_once $abs_us_root.$us_url_root.'users/includes/template/prep.php'; ?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/datatables/1.10.21/css/dataTables.bootstrap4.min.css" integrity="sha512-PT0RvABaDhDQugEbpNMwgYBCnGCiTZMh9yOzUsJHDgl/dMhD9yjHAwoumnUk3JydV3QTcIkNDuN40CJxik5+WQ==" crossorigin="anonymous" />
<style>
.wide {
  width:100%;
}

.permissions {
  padding-top: 1em;
}

.hidden {
  display: none;
}
</style>
<?php
$errors = $successes = [];

$form_valid = true;
$post_bypass = false;
$uri_coupon_code = null;

$coupon_code_submit = false;
if (Input::get('coupon_code_submit') == '1') {
    $coupon_code_submit = true;
}

removeIndexPhp();
$page = $args[0] ?? null;
$relRed = generateRelativeRedirect($args, true);

$is_admin = hasPerm(2);
if (!pluginActive('coupons', !$is_admin)) {
    $page = 'disabled';
}

if ($page == 'dashboard') {
    $action = $args[1] ?? null;
}

if ($page == 'task') {
    $action = $args[1] ?? null;

    if ($action == 'expire-all-coupons') {
        $expire = Coupons_expireCoupon(null, true);
        if (!$expire['state']) {
            $stored_errors = Session::get('errors');
            if ($stored_errors == null) {
                $stored_errors = [];
            } elseif (!is_object(json_decode($stored_errors))) {
                $stored_error = $stored_errors;
                $stored_errors = [];
                $stored_errors[] = $stored_error;
            }

            $stored_errors[] = $expire['error'] ?? 'generic_error';
            Session::put('errors', json_encode($stored_errors));
        }
        Redirect::to("{$relRed}dashboard/");
    } elseif ($action == 'expire') {
        $coupon_code = $args[2] ?? null;
        $expire = Coupons_expireCoupon($coupon_code);
        if (!$expire['state']) {
            $stored_errors = Session::get('errors');
            if ($stored_errors == null) {
                $stored_errors = [];
            } elseif (!is_object(json_decode($stored_errors))) {
                $stored_error = $stored_errors;
                $stored_errors = [];
                $stored_errors[] = $stored_error;
            }

            $stored_errors[] = $expire['error'] ?? 'generic_error';
            Session::put('errors', json_encode($stored_errors));
        }
        Redirect::to("{$relRed}dashboard/");
    }
}

if ($page == 'redeem') {
    $uri_coupon_code = $args[1] ?? null;
    if ($uri_coupon_code != '' && $uri_coupon_code != null) {
        $post_bypass = $coupon_code_submit = true;
    } else {
        $uri_coupon_code = null;
    }
    $page = null;
}

if (isset($_POST) || $post_bypass) {
    if (Input::get('coupon_submit') == '1') {
        $token = Input::get('csrf');
        $return_to_dashboard = Input::get('return_to_dashboard');
        if ($return_to_dashboard == '1') {
            $return_to_dashboard = true;
        } else {
            $return_to_dashboard = false;
        }
        $coupon_code = (Input::get('coupon_code') == '') ? null : Input::get('coupon_code');
        $coupon_type = (Input::get('coupon_type') == '') ? null : Input::get('coupon_type');
        $coupon_permissions = (Input::get('permissions') == '') ? null : Input::get('permissions');
        $coupon_required_permissions = (Input::get('required_permissions') == '') ? null : Input::get('required_permissions');
        $coupon_use_limit = (Input::get('coupon_use_limit') == '') ? null : Input::get('coupon_use_limit');
        if ($coupon_use_limit != null && $coupon_use_limit < 0) {
            $errors[] = 'Coupon Use Limmit may not be less than 1';
            $form_valid = false;
        }
        $coupon_create_amount = (int) Input::get('coupon_create_amount') ?? null;
        $coupon_expiry_date = (Input::get('coupon_expiry_date') == '') ? null : date('Y-m-d H:i:s', strtotime(Input::get('coupon_expiry_date')));
        if (date('Y-m-d', strtotime($coupon_expiry_date)) == '1969-12-31') {
            $coupon_expiry_date = null;
        }
        if ($coupon_expiry_date != null) {
            $_POST['coupon_expiry_date'] = $coupon_expiry_date;
        }

        if ($coupon_create_amount > 1 && $coupon_code != null) {
            $coupon_code = null;
        }

        if ($coupon_create_amount == '' || $coupon_create_amount == 0) {
            $coupon_create_amount = 1;
        }

        if (!Token::check($token)) {
            $errors[] = 'Security check failed, please try again';
        } else {
            $validation = new Validate();
            $validation->check($_POST, [
          'coupon_code' => [
            'display' => 'Coupon Code',
          ],
          'coupon_type' => [
            'display' => 'Coupon Type',
          ],
          'coupon_use_limit' => [
            'display' => 'Coupon Use Limit',
            'is_numeric' => true,
          ],
          'coupon_expiry_date' => [
            'display' => 'Coupon Expiry Date',
            'is_datetime' => true,
          ],
        ]);

            if ($validation->passed() && $form_valid) {
                for ($i = 0; $i < $coupon_create_amount; ++$i) {
                    if ($coupon_code == null) {
                        $coupon = Coupons_generateCouponCode();
                        if ($coupon['state']) {
                            $coupon_code = $coupon['data'];
                            $_POST['coupon_code'] = $coupon_code;
                        } else {
                            $form_valid = false;
                            $errors[] = 'There was an error generating a coupon code, please try again';
                        }
                    }

                    $fields = [
                'Coupon' => $coupon_code,
                'CouponType' => $coupon_type,
                'CouponGeneratedByUserId' => $user->data()->id,
                'CouponUseLimit' => $coupon_use_limit,
                'CouponExpirationDate' => $coupon_expiry_date,
              ];
                    $db->insert('coupons', $fields);
                    if (!$db->error()) {
                        $coupon_id = $db->lastId();
                        logger($user->data()->id, 'Coupons', "Created Coupon {$coupon_code}", json_encode(['DATA' => $fields]));

                        foreach ($coupon_permissions as $coupon_permission) {
                            $perm_fields = [
                              'fkCouponID' => $coupon_id,
                              'fkPermissionID' => $coupon_permission,
                            ];
                            $db->insert('coupons_permissions', $perm_fields);
                            if (!$db->error()) {
                                logger($user->data()->id, 'Coupons', "Added Permission to Coupon {$coupon_code}", json_encode(['DATA' => $perm_fields]));
                            } else {
                                logger($user->data()->id, 'Coupons', "Failed to add permission to Coupon {$coupon_code}", json_encode(['DATA' => $perm_fields]));
                            }
                        }

                        foreach ($coupon_required_permissions as $coupon_required_permission) {
                            $req_fields = [
                              'fkCouponID' => $coupon_id,
                              'fkPermissionID' => $coupon_required_permission,
                            ];
                            $db->insert('coupons_required_permissions', $req_fields);
                            if (!$db->error()) {
                                logger($user->data()->id, 'Coupons', "Added Required Permission to Coupon {$coupon_code}", json_encode(['DATA' => $req_fields]));
                            } else {
                                logger($user->data()->id, 'Coupons', "Failed to add required permission to Coupon {$coupon_code}", json_encode(['DATA' => $req_fields]));
                            }
                        }

                        if ($coupon_create_amount == 1) {
                            if ($return_to_dashboard) {
                                Redirect::to("{$relRed}dashboard/");
                            }
                        }
                    } else {
                        logger($user->data()->id, 'Coupons', 'Failed to Create Coupon', json_encode(['ERROR' => $db->errorString(), 'DATA' => $fields]));
                        if ($coupon_create_amount == 1) {
                            $form_valid = false;
                            $errors[] = 'There was an error processing your request, please try again later';
                        }
                    }
                    $coupon_code = null;
                }
                if ($form_valid && $coupon_create_amount > 1) {
                    if ($return_to_dashboard) {
                        Redirect::to("{$relRed}dashboard/");
                    }
                }
            } else {
                if ($coupon_create_amount == 1) {
                    $coupon_create_amount = null;
                }
                $form_valid = false;
                foreach ($validation->errors() as $error) {
                    $errors[] = $error[0];
                }
            }
        }
    } elseif ($coupon_code_submit) {
        $coupon_code = Input::get('coupon_code');
        if ($uri_coupon_code != null) {
            $coupon_code = $uri_coupon_code;
        }
        $coupon_code = strtoupper($coupon_code);
        $validate_coupon = Coupons_validateCoupon($coupon_code);
        if ($validate_coupon['state']) {
            $validate_coupon = $validate_coupon['data'];
            $permissions = explode(',', $validate_coupon->PermissionIds);
            $fields = [
            'fkCouponID' => $validate_coupon->kCouponID,
            'fkUserID' => $user->data()->id,
          ];
            $db->insert('coupons_history', $fields);
            if (!$db->error()) {
                logger($user->data()->id, 'Coupons', "Redeemed Coupon {$coupon_code}");
                foreach ($permissions as $perm) {
                    if ($perm == '' || $perm == null) {
                        continue;
                    }
                    if (!hasPerm($perm, null, false)) {
                        $fields = [
                        'user_id' => $user->data()->id,
                        'permission_id' => $perm,
                      ];
                        $db->insert('user_permission_matches', $fields);
                        if (!$db->error()) {
                            $track_permission = Coupons_trackReward('Permission_Add', $perm);
                            if ($track_permission['state']) {
                                logger($user->data()->id, 'Coupons', "Added Permission #{$perm} to User");
                            } else {
                                logger($user->data()->id, 'Coupons', "[UNTRACKED] Added Permission #{$perm} to User");
                            }
                        } else {
                            logger($user->data()->id, 'Coupons', "Failed to Add Permission #{$perm} to User", ['ERROR' => $db->errorString()]);
                        }
                    } else {
                        logger($user->data()->id, 'Coupons', "Skipping Addition of Permission #{$perm} to User", ['ERROR' => 'user_has_perm']);
                    }
                }
                $page = 'redeemed';
                Coupons_onCouponRedeem($validate_coupon);
            } else {
                logger($user->data()->id, 'Coupons', "Failed Redeeming Coupon {$coupon_code}", ['ERROR' => $db->errorString()]);
                $action = 'internal_error';
            }
        } else {
            logger($user->data()->id, 'Coupons', "Failed Redeeming Coupon {$coupon_code}", ['ERROR' => $validate_coupon['error']]);
            switch ($validate_coupon['error']) {
                case 'db_no_results':
                    $action = 'invalid_code';
                    break;
                default:
                    $action = 'internal_error';
                    break;
            }
        }
    }
}

$stored_errors = json_decode(Session::get('errors')) ?? [];
foreach ($stored_errors as $stored_error) {
    $errors[] = $stored_error;
}
Session::delete('errors');

if ($page == 'dashboard') {
    $coupons = Coupons_getCoupons();
}

if ($page == null && !isset($action)) {
    $action = null;
}

?>
<div class="container-fluid">
  <?php echo resultBlock($errors, $successes); ?>
    <?php if ($page == null) { ?>
      <?php if ($action == null) { ?>
        <h1>You've found the right place &#128526;</h1>
        <h5>Enter the coupon code you've been provided below.</h5>
      <?php } elseif ($action == 'invalid_code') { ?>
        <h1>Oh no... &#128533;</h1>
        <h5>The code you entered was invalid or expired</h5>
        <p>Please try again later or contact our team for support</p>
      <?php } elseif ($action == 'internal_error') { ?>
        <h1>Oh no... &#128533;</h1>
        <h5>We had trouble processing your request</h5>
        <p>Please try again later or contact our team for support</p>
      <?php } ?>
      <form class="form-inline" action="" method="POST">
        <div class="form-group mx-sm-3 mb-2" style="margin: 0 !important; margin-right: 1em !important;">
          <input class="form-control" style="height: 100%" type="text" name="coupon_code" input="coupon_code" placeholder="Coupon code" autofocus required />
        </div>
        <button type="submit" class="btn btn-secondary" name="coupon_code_submit" id="coupon_code_submit" value="1">Redeem</button>
      </form>
    <?php } elseif ($page == 'redeemed') { ?>
      <h1>Congrats! &#129395;</h1>
      <h5>You've redeemed the code!</h5>
      <p>The rewards for redeeming this code will be applied shortly.</p>
    <?php } elseif ($page == 'dashboard') { ?>
      <?php if ($action == null) { ?>
        <h1>Coupons Dashboard</h1>
        <div class="row">
          <div class="col-md-9 col-xs-12">
            <table id="coupons_table" class="table table-dark">
              <thead>
                <th>Coupon</th>
                <th>Type</th>
                <th>Uses & Expiration</th>
                <th>Actions</th>
              </thead>
              <tbody>
                <?php
                if ($coupons['state']) {
                    foreach ($coupons['data'] as $coupon) {?>
                    <tr>
                      <td><?php echo $coupon->Coupon; ?></td>
                      <td><?php echo $coupon->CouponType; ?></td>
                      <td>
                        <?php echo $coupon->CouponUseCount; ?> /
                        <?php
                          if ($coupon->CouponUseLimit == null) {
                              echo '&infin;';
                          } else {
                              echo $coupon->CouponUseLimit;
                          }
                        ?>
                        <?php if ($coupon->CouponExpirationDate != null) {?>
                          <span title="<?php echo $coupon->CouponExpirationDate; ?> (click to expire)" data-toggle="tooltip">
                            <?php echo time2str($coupon->CouponExpirationDate); ?>
                          </span>
                        <?php } ?>
                      </td>
                      <td>
                        <button class="btn btn-sm btn-secondary" data-clipboard-text="<?php echo str_replace('dashboard', 'redeem', explode('?', 'https://'.Server::get('HTTP_HOST').Server::get('REQUEST_URI'))[0]).$coupon->Coupon.'/'; ?>">Copy Redemption Link</button>
                        <button class="btn btn-sm btn-success" id="coupon_metadata" data-id="<?php echo $coupon->Coupon; ?>">Metadata</button>
                        <!-- <button class="btn btn-sm btn-success">Usage</button> -->
                        <a href='<?php echo "{$relRed}task/expire/{$coupon->Coupon}/"; ?>' class="btn btn-sm btn-danger">Expire</a>
                      </td>
                    </tr>
                  <?php }
                    } else { ?>
                  <tr>
                    <td colspan="4">
                      No coupons found
                    </td>
                  </tr>
                <?php } ?>
              </tbody>
            </table>
          </div>
          <div class="col-md-3 col-xs-12">
            <div style="padding-top:6px;">
              <a href='<?php echo "{$relRed}create-coupon/"; ?>' class="btn btn-md btn-secondary btn-block">Create Coupon</a>
              <a href='<?php echo "{$relRed}task/expire-all-coupons/"; ?>' class="btn btn-md btn-danger btn-block">Expire All Coupons</a>
            </div>
          </div>
        </div>

        <!-- Coupon Metadata -->
        <div class="modal" id="coupon_metadata" tabindex="-1" role="dialog">
          <div class="modal-dialog" role="document">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title"><span id="coupon_code"></span></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                  <span aria-hidden="true">&times;</span>
                </button>
              </div>
              <div class="modal-body" id="coupon_metadata_body">
                <pre id="coupon_metadata">

                </pre>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
              </div>
            </div>
          </div>
        </div>
      <?php } elseif ($action == 'settings') { ?>

      <?php } ?>
    <?php } elseif ($page == 'create-coupon') { ?>
      <div class="row">
        <div class="col col-md-6 offset-md-3 col-xs-12">
          <h1>Create Coupon</h1>
          <form id="coupon_form" action="" method="POST">
            <label for="coupon_code" class="form-label">| Coupon Code (optional)</label>
            <input type="text" class="form-control" name="coupon_code" id="coupon_code" placeholder="Leave blank to autogenerate" <?php if (!$form_valid) {?>value="<?php echo $coupon_code ?? null; ?>"<?php } ?> autofocus />

            <br>
            <label for="coupon_code" class="form-label">| Coupon Type (an identifier for you to track - optional)</label>
            <input type="text" class="form-control" name="coupon_type" id="coupon_type" <?php if (!$form_valid) {?>value="<?php echo $coupon_type ?? null; ?>"<?php } ?> autofocus />

            <br>
            <label for="coupon_use_limit" class="form-label">| Use Limit (#)</label>
            <input type="number" class="form-control" min="1" name="coupon_use_limit" id="coupon_use_limit" placeholder="Leave blank for unlimited" <?php if (!$form_valid) {?>value="<?php echo $coupon_use_limit ?? null; ?>"<?php } ?> />

            <br>
            <label for="coupon_expiry_date" class="form-label">| Expiration Date (YYYY-MM-DD)</label>
            <input type="datetime" class="form-control" name="coupon_expiry_date" id="coupon_expiry_date" placeholder="Leave blank for no expiry" <?php if (!$form_valid) {?> value="<?php echo $coupon_expiry_date ?? null; ?>"<?php } ?> />

            <br>
            <label for="coupon_use_limit" class="form-label">| Number of Coupons to Generate<br><sup>(you must not define a coupon code if you are generating multiple coupons)</sup></label>
            <input type="number" class="form-control" min="1" name="coupon_create_amount" id="coupon_create_amount" placeholder="Leave blank if you are not generating multiple" <?php if (!$form_valid) {?>value="<?php echo $coupon_create_amount ?? null; ?>"<?php } ?> />

            <br>
            <button type="button" class="btn btn-primary btn-md" data-state="off" id="toggle_required_permissions">Toggle Required Permissions: Show (Currently Hidden)</button>

            <br>
            <div id="required_permissions" class="required_permissions row hidden">
              <div class="col-xs-12">
                <br>
                <p>The Required Permissions feature allows you to restrict the use of a coupon to only users who have the required permissions (any). If you do not want to restrict the use of a coupon, leave this section blank.</p>
              </div>
              <?php $permissions = fetchAllPermissions();
        foreach ($permissions as $perm) { ?>
                <div class="col col-md-4 col-xs-12">
                  <label>
                    <input type="checkbox" class="required_permissions" name="required_permissions[]" id="required_permissions[]" value="<?php echo $perm->id; ?>" />
                    <?php echo $perm->name; ?>
                  </label>
                </div>
              <?php } ?>
            </div>

                        <br>
            <button type="button" class="btn btn-primary btn-md" data-state="off" id="toggle_permissions">Toggle Redemption Permissions: Show (Currently Hidden)</button>

            <br>
            <div id="permissions" class="permissions row hidden">
              <div class="col-xs-12">
                <br>
                <p>The Redemption Permissions feature allows you to select permissions that will be granted to a user when they redeem a coupon. If you do not want to grant any permissions, leave this section blank.</p>
              </div>
              <?php $permissions = fetchAllPermissions();
        foreach ($permissions as $perm) { ?>
                <div class="col col-md-4 col-xs-12">
                  <label>
                    <input type="checkbox" class="permissions" name="permissions[]" id="permissions[]" value="<?php echo $perm->id; ?>" />
                    <?php echo $perm->name; ?>
                  </label>
                </div>
              <?php } ?>
            </div>

            <br>
            <label>
              <input type="checkbox" name="return_to_dashboard" id="return_to_dashboard" value="1" <?php if ($return_to_dashboard ?? true) {?>checked<?php } ?> />
              Return to Dashboard
            </label>

            <br>
            <input type="hidden" name="csrf" id="csrf" value="<?php echo Token::generate(); ?>" />
            <button class="btn btn-md btn-success" type="submit" name="coupon_submit" id="coupon_submit" value="1">Create Coupon</button>
          </form>
        </div>
      </div>
    <?php } elseif ($page == 'disabled') { ?>
      <div class="alert alert-danger wide">
        We're sorry, but this <strong>system is unavailable.</strong> Please contact an administrator for further assistance.
      </div>
    <?php } ?>
</div>

<?php require_once $abs_us_root.$us_url_root.'users/includes/html_footer.php'; ?>
<?php if ($page == 'dashboard') { ?>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/clipboard.js/2.0.8/clipboard.min.js" integrity="sha512-sIqUEnRn31BgngPmHt2JenzleDDsXwYO+iyvQ46Mw6RL+udAUZj2n/u/PGY80NxRxynO7R9xIGx5LEzw4INWJQ==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/datatables/1.10.21/js/jquery.dataTables.min.js" integrity="sha512-BkpSL20WETFylMrcirBahHfSnY++H2O1W+UnEEO4yNIl+jI2+zowyoGJpbtk6bx97fBXf++WJHSSK2MV4ghPcg==" crossorigin="anonymous"></script>
  <script>
  $(function () {
    new ClipboardJS('.btn');

    main_div = $('main.container')
    main_div.removeClass('container');
    main_div.addClass('container-fluid');
    $('#coupons_table').DataTable(
      {
        "pageLength": 25,
        "stateSave": true,
        "aLengthMenu": [[25, 50, 100, -1], [25, 50, 100, "All"]],
        "aaSorting": [],
      }
    );
    $('button#coupon_metadata').click(function() {
      coupon_btn = $(this)
      coupon_code = coupon_btn.attr('data-id');

      $.get("<?php echo $us_url_root; ?>coupons/api/?csrf=<?php echo Token::generate(); ?>&coupon_code=" + coupon_code, function(data, status) {
        console.log(data);
        console.log(data.data);
        $("pre#coupon_metadata").html(JSON.stringify(data.data, null, 2));
        $("span#coupon_code").html(coupon_code)
        $("div.modal#coupon_metadata").modal();
      });
    })
  })
  </script>
<?php } elseif ($page == 'create-coupon') { ?>
  <script>
  $(function () {
    $('button#toggle_permissions').click(function() {
      btn = $(this)
      state = btn.attr('data-state');
      on_text = 'Toggle Permissions: Hide (Currently Shown)'
      off_text = 'Toggle Permissions: Show (Currently Hidden)'
      perm_div = $('div#permissions')
      perm_checkboxes = $('input:checkbox.permissions')

      if(state == 'off') {
        btn.text(on_text)
        btn.attr('data-state', 'on')
        perm_div.removeClass('hidden')
      } else if(state == 'on') {
        btn.text(off_text)
        btn.attr('data-state', 'off')
        perm_div.addClass('hidden')
        perm_checkboxes.prop('checked', false)
      }
    })
  })

  $(function () {
    $('button#toggle_required_permissions').click(function() {
      btn = $(this)
      state = btn.attr('data-state');
      on_text = 'Toggle Permissions: Hide (Currently Shown)'
      off_text = 'Toggle Permissions: Show (Currently Hidden)'
      perm_div = $('div#required_permissions')
      perm_checkboxes = $('input:checkbox.required_permissions')

      if(state == 'off') {
        btn.text(on_text)
        btn.attr('data-state', 'on')
        perm_div.removeClass('hidden')
      } else if(state == 'on') {
        btn.text(off_text)
        btn.attr('data-state', 'off')
        perm_div.addClass('hidden')
        perm_checkboxes.prop('checked', false)
      }
    })
  })
  </script>
<?php } ?>
