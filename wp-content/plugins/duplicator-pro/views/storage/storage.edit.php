<?php

/**
 *
 * @package Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 *
 */

defined('ABSPATH') || defined('DUPXABSPATH') || exit;

use Duplicator\Libs\Snap\SnapIO;
use Duplicator\Libs\Snap\SnapString;

DUP_PRO_U::hasCapability('export');

global $wp_version;
global $wpdb;

$global       = DUP_PRO_Global_Entity::get_instance();
$nonce_action = 'duppro-storage-edit';
$storage_id   = isset($_REQUEST['storage_id']) ? intval($_REQUEST['storage_id']) : -1;
$storage      = ($storage_id == -1) ? new DUP_PRO_Storage_Entity() : DUP_PRO_Storage_Entity::get_by_id($storage_id);
$needsReset   = ($storage_id != -1 && isset($_REQUEST['storage_type']) && $storage->storage_type != $_REQUEST['storage_type']) ? true : false;

$_REQUEST['_sftp_storage_folder'] = isset($_REQUEST['_sftp_storage_folder']) ? sanitize_text_field($_REQUEST['_sftp_storage_folder']) : '';
$_REQUEST['duppro-source-storage-id'] = isset($_REQUEST['duppro-source-storage-id']) ? $_REQUEST['duppro-source-storage-id'] : -1;


$error_message = null;
$success_message = null;

if (isset($_REQUEST['action'])) {
    $wpnonce_val = isset($_POST['_wpnonce']) ? $_POST['_wpnonce'] : $_GET['_wpnonce'];
    DUP_PRO_U::verifyNonce($wpnonce_val, $nonce_action);

    if ($_REQUEST['action'] == 'save') {
        if ($needsReset) {
            $storage     = new DUP_PRO_Storage_Entity();
            $storage->id = $storage_id;
        }
        if ($_REQUEST['storage_type'] == DUP_PRO_Storage_Types::GDrive) {
            if ($storage->gdrive_authorization_state == DUP_PRO_GDrive_Authorization_States::Unauthorized) {
                if (!empty($_REQUEST['gdrive-auth-code'])) {
                    try {
                        $google_client_auth_code = sanitize_text_field($_REQUEST['gdrive-auth-code']);
                        $google_client = DUP_PRO_GDrive_U::get_raw_google_client();
                        $gdrive_token_pair_string = $google_client->authenticate($google_client_auth_code);

                        $gdrive_token_pair = json_decode($gdrive_token_pair_string, true);

                        DUP_PRO_Log::traceObject('Token pair from authorization', $gdrive_token_pair);

                        if (isset($gdrive_token_pair['refresh_token'])) {
                            $storage->gdrive_refresh_token = $gdrive_token_pair['refresh_token'];
                            $storage->gdrive_access_token_set_json = $google_client->getAccessToken(); //$gdrive_token_pair['access_token'];

                            DUP_PRO_Log::trace("Set refresh token to {$storage->gdrive_refresh_token}");
                            DUP_PRO_Log::trace("Set access token to {$storage->gdrive_access_token_set_json}");

                            $storage->gdrive_authorization_state = DUP_PRO_GDrive_Authorization_States::Authorized;
                            $storage->save();

                            $success_message = DUP_PRO_U::__('Google Drive is connected successfully and Storage Provider Updated.');
                        } else {
                            $error_message = DUP_PRO_U::__("Couldn't connect. Google Drive refresh token not found.");
                        }
                    } catch (Exception $ex) {
                        $error_message = sprintf(DUP_PRO_U::__('Problem retrieving Google refresh and access tokens [%s] Please try again!'), $ex->getMessage());
                    }
                }
            }
        }

        if ($_REQUEST['storage_type'] == DUP_PRO_Storage_Types::Dropbox) {
            if ($storage->dropbox_authorization_state == DUP_PRO_Dropbox_Authorization_States::Unauthorized) {
                if (!empty($_REQUEST['dropbox-auth-code'])) {
                    try {
                        $dropbox_client_auth_code = sanitize_text_field($_REQUEST['dropbox-auth-code']);
                        $dropbox_client = DUP_PRO_Storage_Entity::get_raw_dropbox_client(false);
                        $v2_access_token = $dropbox_client->authenticate($dropbox_client_auth_code);

                        if ($v2_access_token !== false) {
                            $storage->dropbox_v2_access_token = $v2_access_token;

                            DUP_PRO_Log::trace("Set dorpbox access token to {$storage->dropbox_v2_access_token}");

                            $storage->dropbox_authorization_state = DUP_PRO_Dropbox_Authorization_States::Authorized;
                            $storage->save();
                        } else {
                            $error_message = DUP_PRO_U::__("Couldn't connect. Dropbox access token not found.");
                        }
                    } catch (Exception $ex) {
                        $error_message = sprintf(DUP_PRO_U::__('Problem retrieving Dropbox access token [%s] Please try again!'), $ex->getMessage());
                    }
                }
            }
        }

        if ($_REQUEST['storage_type'] == DUP_PRO_Storage_Types::OneDrive || $_REQUEST['storage_type'] == DUP_PRO_Storage_Types::OneDriveMSGraph) {
            if ($storage->onedrive_authorization_state == DUP_PRO_OneDrive_Authorization_States::Unauthorized) {
                if (!empty($_REQUEST['onedrive-auth-code']) || !empty($_REQUEST['onedrive-msgraph-auth-code'])) {
                    $use_msgraph_api = $_REQUEST['storage_type'] == DUP_PRO_Storage_Types::OneDriveMSGraph;
                    if ($_REQUEST['onedrive-is-business'] || $use_msgraph_api) {
                        $onedrive_auth_client = DUP_PRO_Onedrive_U::get_onedrive_client_from_state(
                            (object) array(
                                            'redirect_uri' => DUP_PRO_OneDrive_Config::ONEDRIVE_REDIRECT_URI,
                                            'token' => null
                                        ),
                            $use_msgraph_api
                        );

                        $access_token_args = array (
                            'code' => sanitize_text_field($use_msgraph_api ? $_REQUEST['onedrive-msgraph-auth-code'] : $_REQUEST['onedrive-auth-code']),
                            'grant_type' => 'authorization_code'
                        );
                        if ($_REQUEST['onedrive-is-business']) {
                            $onedrive_auth_client->setBusinessMode();
                            $access_token_args['resource'] = DUP_PRO_OneDrive_Config::MICROSOFT_GRAPH_ENDPOINT;
                        }
                        $onedrive_auth_client->obtainAccessToken(
                            DUP_PRO_OneDrive_Config::ONEDRIVE_CLIENT_SECRET,
                            $access_token_args
                        );
                        DUP_PRO_Log::traceObject("OneDrive Client State:", $onedrive_auth_client->getState());
                        $onedrive_info = $onedrive_auth_client->getServiceInfo();
                        if ($_REQUEST['onedrive-is-business']) {
                            $onedrive_auth_client->obtainAccessToken(DUP_PRO_OneDrive_Config::ONEDRIVE_CLIENT_SECRET, array(
                                'resource' => $onedrive_info['resource_id'],
                                'refresh_token' => $onedrive_auth_client->getState()->token->data->refresh_token,
                                'grant_type' => 'refresh_token'
                                    ));
                        }
                        $storage->onedrive_endpoint_url = $onedrive_info['endpoint_url'];
                        $storage->onedrive_resource_id = $onedrive_info['resource_id'];
                    } else {
                        $onedrive_auth_code = !empty($_REQUEST['onedrive-auth-code'])
                                                ? sanitize_text_field($_REQUEST['onedrive-auth-code'])
                                                : sanitize_text_field($_REQUEST['onedrive-msgraph-auth-code']);
                        $onedrive_auth_client = DUP_PRO_Onedrive_U::get_onedrive_client_from_state(
                            (object) array(
                                            'redirect_uri' => DUP_PRO_OneDrive_Config::ONEDRIVE_REDIRECT_URI,
                                            'token' => null
                                        )
                        );
                        $onedrive_auth_client->obtainAccessToken(DUP_PRO_OneDrive_Config::ONEDRIVE_CLIENT_SECRET, array(
                            'code' => $onedrive_auth_code,
                            'grant_type' => 'authorization_code'
                                ));
                    }
                    $onedrive_client_state = $onedrive_auth_client->getState();
                    $storage->storage_type = DUP_PRO_Storage_Types::OneDrive;
                    $storage->onedrive_access_token = $onedrive_client_state->token->data->access_token;
                    $storage->onedrive_refresh_token = $onedrive_client_state->token->data->refresh_token;
                    $storage->onedrive_user_id = property_exists($onedrive_client_state->token->data, "user_id")
                                                    ? $onedrive_client_state->token->data->user_id
                                                    : '';
                    $storage->onedrive_token_obtained = $onedrive_client_state->token->obtained;
                    $storage->onedrive_authorization_state = DUP_PRO_OneDrive_Authorization_States::Authorized;
                    $storage->save();

                    $success_message = DUP_PRO_U::__('OneDrive is connected successfully and Storage Provider Updated.');
                }
            }
        }

        // For hidden passwords/keys we should remember old values taken from the database,
        // to preserve them in case if new values are empty
        $s3_secret_key             = $storage->s3_secret_key;
        $ftp_password              = $storage->ftp_password;
        $sftp_password             = $storage->sftp_password;
        $sftp_private_key_password = $storage->sftp_private_key_password;

        $storage->set_post_variables($_REQUEST);

        // For hidden passwords/keys we should remember old values taken from the database,
        // to preserve them in case if new values are empty
        if (SnapString::stringLength($storage->s3_secret_key) == 0) {
            $storage->s3_secret_key = $s3_secret_key;
        }
        if (SnapString::stringLength($storage->ftp_password) == 0) {
            $storage->ftp_password = $ftp_password;
        }
        if (SnapString::stringLength($storage->sftp_password) == 0) {
            $storage->sftp_password = $sftp_password;
        }
        if (SnapString::stringLength($storage->sftp_private_key_password) == 0) {
            $storage->sftp_private_key_password = $sftp_private_key_password;
        }

        // Checkboxes don't set post values when off so have to manually set these
        switch ($_REQUEST['storage_type']) {
            case DUP_PRO_Storage_Types::Local:
                $storage->local_storage_folder = trim(SnapIO::safePath(sanitize_text_field($_REQUEST['_local_storage_folder'])));
                $storage->local_filter_protection = isset($_REQUEST['_local_filter_protection']);
                break;
            case DUP_PRO_Storage_Types::FTP:
                $storage->ftp_passive_mode = isset($_REQUEST['_ftp_passive_mode']);
                $storage->ftp_ssl = isset($_REQUEST['_ftp_ssl']);
                $storage->ftp_use_curl = isset($_POST['_ftp_use_curl']);
                $storage->ftp_storage_folder = SnapIO::safePath(sanitize_text_field($_REQUEST['_ftp_storage_folder']));
                break;
            case DUP_PRO_Storage_Types::SFTP:
                $storage->sftp_storage_folder = SnapIO::safePath(sanitize_text_field($_REQUEST['_sftp_storage_folder']));
                $storage->sftp_disable_chunking_mode = filter_input(INPUT_POST, 'sftp_disable_chunking_mode', FILTER_VALIDATE_BOOLEAN);
                break;
            case DUP_PRO_Storage_Types::Dropbox:
                $storage->dropbox_skip_archive_validation_hash = (
                    isset($_REQUEST['dropbox_skip_archive_validation_hash']) &&
                    $_REQUEST['dropbox_skip_archive_validation_hash']
                    ) ? 1 : 0;
                $storage->dropbox_storage_folder = SnapIO::safePath(sanitize_text_field($_REQUEST['_dropbox_storage_folder']));
                break;
            case DUP_PRO_Storage_Types::GDrive:
                $storage->gdrive_storage_folder = SnapIO::safePath(sanitize_text_field($_REQUEST['_gdrive_storage_folder']));
                break;
            case DUP_PRO_Storage_Types::S3:
                $storage->s3_storage_folder = SnapIO::safePath(sanitize_text_field($_REQUEST['_s3_storage_folder']));
                $storage->s3_ACL_full_control = filter_input(INPUT_POST, 's3_ACL_full_control', FILTER_VALIDATE_BOOLEAN);
                break;
            case DUP_PRO_Storage_Types::OneDrive:
                $onedrive_storage_folder = SnapIO::safePath(sanitize_text_field($_REQUEST['_onedrive_storage_folder']));
                if ($storage->onedrive_storage_folder != $onedrive_storage_folder) {
                    $storage->onedrive_storage_folder = $onedrive_storage_folder;
                    $storage->onedrive_storage_folder_id = '';
                }
                $storage->onedrive_max_files = intval($_REQUEST['onedrive_max_files']);
                break;
            case DUP_PRO_Storage_Types::OneDriveMSGraph:
                $onedrive_storage_folder = SnapIO::safePath(sanitize_text_field($_REQUEST['_onedrive_msgraph_storage_folder']));
                if ($storage->onedrive_storage_folder != $onedrive_storage_folder) {
                    $storage->onedrive_storage_folder = $onedrive_storage_folder;
                    $storage->onedrive_storage_folder_id = '';
                }
                $storage->onedrive_max_files = intval($_REQUEST['onedrive_msgraph_max_files']);
                break;
        }

        $storage->save();

        if (is_null($success_message) && is_null($error_message)) {
            $success_message = DUP_PRO_U::__('Storage Provider Updated.');
        }

        if ($storage->storage_type == DUP_PRO_Storage_Types::Local) {
            if ((trim($storage->local_storage_folder) != '') && (file_exists($storage->local_storage_folder) == false)) {
                if (@mkdir($storage->local_storage_folder, 0755, true)) {
                    $success_message = sprintf(DUP_PRO_U::__('Storage Provider Updated - Folder %1$s was created.'), $storage->local_storage_folder);
                } else {
                    $error_message = sprintf(DUP_PRO_U::__('Storage Provider Updated - Unable to create folder %1$s.'), $storage->local_storage_folder);
                }
            }
        }
    } elseif ($_REQUEST['action'] == 'copy-storage') {
        $source_id = $_REQUEST['duppro-source-storage-id'];
        if ($source_id != -1) {
            $storage->copy_from_source_id($source_id);
            $storage->save();

            $success_message = DUP_PRO_U::__('Storage Copied Successfully.');
        }
    } elseif ($_REQUEST['action'] == 'gdrive-revoke-access') {
        $google_client = DUP_PRO_GDrive_U::get_raw_google_client();

        if (!empty($storage->gdrive_refresh_token) && !($ret_gdrive_refresh_token_revoke = $google_client->revokeToken($storage->gdrive_refresh_token))) {
            DUP_PRO_Log::trace("Problem revoking Google Drive refresh token");
        }

        $gdrive_access_token = json_decode($storage->gdrive_access_token_set_json)->access_token;

        if (!empty($gdrive_access_token) && !($ret_gdrive_access_token_revoke = $google_client->revokeToken($gdrive_access_token))) {
            DUP_PRO_Log::trace("Problem revoking Google Drive access token");
        }

        if (!$ret_gdrive_refresh_token_revoke && !$ret_gdrive_access_token_revoke) {
            $error_message = DUP_PRO_U::__('Google Drive refresh token and access token can\'t be unauthorized.');
        } elseif (!$ret_gdrive_refresh_token_revoke) {
            $error_message = DUP_PRO_U::__('Google Drive refresh token can\'t be unauthorized.');
        } elseif (!$ret_gdrive_access_token_revoke) {
            $error_message = DUP_PRO_U::__('Google Drive access token can\'t be unauthorized.');
        } else {
            $success_message = DUP_PRO_U::__('Google Drive has unauthorized successfully.');
        }

        $storage->gdrive_access_token_set_json = '';
        $storage->gdrive_refresh_token = '';
        $storage->gdrive_authorization_state = DUP_PRO_GDrive_Authorization_States::Unauthorized;
        $storage->save();
    } elseif ($_REQUEST['action'] == 'dropbox-revoke-access') {
        $dropbox_client = $storage->get_dropbox_client();
        if ($dropbox_client->revokeToken() === false) {
            DUP_PRO_Log::trace("Problem revoking Dropbox access token");
            $error_message = DUP_PRO_U::__('DropBox can\'t be unauthorized.');
        } else {
            $success_message = DUP_PRO_U::__('DropBox has unauthorized successfully.');
        }

        $storage->dropbox_access_token = '';
        $storage->dropbox_access_token_secret = '';
        $storage->dropbox_v2_access_token = '';
        $storage->dropbox_authorization_state = DUP_PRO_Dropbox_Authorization_States::Unauthorized;
        $storage->save();
    } elseif ($_REQUEST['action'] == 'onedrive-revoke-access') {
        $storage->onedrive_endpoint_url = '';
        $storage->onedrive_resource_id = '';
        $storage->onedrive_access_token = '';
        $storage->onedrive_refresh_token = '';
        $storage->onedrive_token_obtained = '';
        $storage->onedrive_user_id = '';
        $storage->onedrive_storage_folder = '';
        $storage->onedrive_max_files = 10;
        $storage->onedrive_storage_folder_id = '';
        $storage->onedrive_authorization_state = DUP_PRO_OneDrive_Authorization_States::Unauthorized;
        $storage->onedrive_storage_folder_web_url = '';
        $storage->save();

        $success_message = DUP_PRO_U::__('OneDrive has unauthorized successfully.');
    }
}

if ($storage->dropbox_authorization_state == DUP_PRO_Dropbox_Authorization_States::Authorized) {
    $dropbox = $storage->get_dropbox_client();
    $account_info = $dropbox->GetAccountInfo();
}

if (DUP_PRO_StorageSupported::isOneDriveSupported() && $storage->onedrive_authorization_state == DUP_PRO_OneDrive_Authorization_States::Authorized) {
    $onedrive = $storage->get_onedrive_client();


    $onedrive_state = $onedrive->getState();
    $onedrive_state_token = $onedrive_state->token;
    if (!isset($onedrive_state_token->data->error)) {
        $storage->get_onedrive_storage_folder();
        $onedrive_account_info = $onedrive->fetchAccountInfo($storage->onedrive_storage_folder_id);
    }
}

if (DUP_PRO_StorageSupported::isGDriveSupported()) {
    if ($storage->gdrive_authorization_state == DUP_PRO_GDrive_Authorization_States::Authorized) {
        try {
            $google_client = $storage->get_full_google_client();
            $gdrive_user_info = DUP_PRO_GDrive_U::get_user_info($google_client);
        } catch (Exception $e) {
            // This is an oddball recommendation - don't queue it in system global entity
            $error_message = sprintf(DUP_PRO_U::__('Error retrieving Google Client. %s'), $e->getMessage()) .
                "<br>" .
                sprintf(
                    DUP_PRO_U::__('<strong>RECOMMENDATION:</strong> Cancel authorization and then connect and authorize Google Drive again. '
                    . 'If this repeatedly occurs, see FAQ: <a target="_blank" href="%s">%s</a>'),
                    'https://snapcreek.com/duplicator/docs/faqs-tech/#faq-trouble-store-130-q',
                    DUP_PRO_U::__('How do I fix issues with Google Drive storage types?')
                );
        }
    } else {
        $google_client = DUP_PRO_GDrive_U::get_raw_google_client();
    }
}

$storages = DUP_PRO_Storage_Entity::get_all();
$storage_count = count($storages);
$txt_auth_note = DUP_PRO_U::__('Note: Clicking the button below will open a new tab/window. Please be sure your browser does not block popups. If a new tab/window does not  '
                . 'open check your browsers address bar to allow popups from this URL.');
?>

<style>
    table.dpro-edit-toolbar select {float:left}
    #dup-storage-form input[type="text"], input[type="password"] { width: 250px;}
    #dup-storage-form input#name {width:100%; max-width: 500px}
    #dup-storage-form #ftp_timeout {width:100px !important} 
    #dup-storage-form input#_local_storage_folder, input#_ftp_storage_folder {width:100% !important; max-width: 500px}
    .provider { display:none; }
    .stage {display:none; }
    td.dpro-sub-title {padding:0; margin: 0}
    td.dpro-sub-title b{padding:20px 0; margin: 0; display:block; font-size:1.25em;}
    input.dpro-storeage-folder-path {width: 450px !important}
    small.dpro-store-type-notice {display:block; padding-left:15px; font-size:12px !important; line-height:18px; color: maroon}
    
    /* ---------------
    Common */
    table.dup-form-sub-area select, 
    table.dup-form-sub-area input[type="text"], input[type="password"] {min-width: 300px}
    .s3_max_files, #dropbox_max_files, #ftp_max_files, #local_max_files, #gdrive_max_files, #onedrive_msgraph_max_files {width:50px !important}
    table.dup-form-sub-area td {padding:2px}
    table.dup-form-sub-area th {width:150px; border:0 solid red; padding:10px}
    img.dup-store-auth-icon {vertical-align: bottom; margin:0 2px 0 0}
    .invisible_out_of_screen {visibility: hidden; position: absolute; left: -999em;}
    .account-heading-info {color: #777; font-weight: bold; font-size: 0.8em;}
    
    /* ---------------
    Amazon */
    td.dup-s3-auth-account {line-height:25px; padding-top:0px !important;}
    td.dup-s3-auth-account select {min-width: 350px}
    table.dup-s3-auth-provider select,
    table.dup-s3-auth-provider input[type="text"] {min-width: 400px}    

    /* ---------------
    DropBox*/
    td.dropbox-authorize {line-height:25px; padding-top:0px !important;}
    div#dropbox-account-info label {display: inline-block; width:100px; font-weight: bold} 
    button#dpro-dropbox-connect-btn {margin:10px 0}
    div.auth-code-popup-note {width:525px; font-size:11px; padding: 0; margin:-5px 0 10px 10px; line-height: 16px; font-style: italic}

    /* ---------------
    Google Drive */
    td.gdrive-authorize {line-height:25px;margin-top:-10px; padding-top:0;}
    div#dpro-gdrive-steps {display:none}
    div#dpro-gdrive-steps div {margin: 0 0 20px 0}
    div#dpro-gdrive-connect-progress {display:none}
    div#gdrive-state-authorized label {display: inline-block; width:100px; font-weight: bold}

    /* ---------------
    OneDrive */
    td.onedrive-authorize {line-height:25px;margin-top:-10px; padding-top:0;}
    div#onedrive-account-info label  {display: inline-block; width:75px; font-weight: bold}

    /* For switch */
    .switch {
        position: relative;
        display: inline-block;
        width: 44px;
        height: 20px;
    }
    .switch input { 
        opacity: 0;
        width: 0;
        height: 0;
    }
    .slider {
        position: absolute;
        cursor: pointer;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: #ccc;
        -webkit-transition: .4s;
        transition: .4s;
    }
    .slider:before {
        position: absolute;
        content: "";
        height: 16px;
        width: 16px;
        left: 4px;
        bottom: 2px;
        background-color: white;
        -webkit-transition: .4s;
        transition: .4s;
    }
    input:checked + .slider {
        background-color: #2196F3;
    }
    input:focus + .slider {
        box-shadow: 0 0 1px #2196F3;
    }
    input:checked + .slider:before {
        -webkit-transform: translateX(19px);
        -ms-transform: translateX(19px);
        transform: translateX(19px);
    }
    /* Rounded sliders */
    .slider.round {
        border-radius: 20px;
    }
    .slider.round:before {
        border-radius: 50%;
    }
</style>

<form id="dup-storage-form" action="<?php echo $edit_storage_url; ?>" method="post" data-parsley-ui-enabled="true" target="_self">
<?php wp_nonce_field($nonce_action); ?>
    <input type="hidden" id="dup-storage-form-action" name="action" value="save">
    <input type="hidden" name="storage_id" id="storage_id" value="<?php echo intval($storage->id); ?>">

<?php if (false) : ?>
        <input type="hidden" id="dropbox_access_token" name="dropbox_access_token" value="<?php echo esc_attr($storage->dropbox_access_token); ?>">
        <input type="hidden" id="dropbox_access_token_secret" name="dropbox_access_token_secret" value="<?php echo esc_attr($storage->dropbox_access_token_secret); ?>">
        <input type="hidden" id="dropbox_authorization_state" name="dropbox_authorization_state" value="<?php echo esc_attr($storage->dropbox_authorization_state); ?>">
<?php endif; //end false  ?>

<!-- ====================
TOOL-BAR -->
<table class="dpro-edit-toolbar">
    <tr>
        <td>
            <?php if ($storage_count > 0) : ?>
                <select name="duppro-source-storage-id">
                    <option value="-1" selected="selected" disabled="true"><?php _e("Copy From"); ?></option>
                <?php
                foreach ($storages as $copy_storage) {
                    echo ($copy_storage->id != $storage->id) ? "<option value='" . intval($copy_storage->id) . "'>" . esc_html($copy_storage->name) . "</option>" : '';
                }
                ?>
                </select>
                <input type="button" class="button action" value="<?php DUP_PRO_U::esc_attr_e("Apply") ?>" onclick="DupPro.Storage.Copy()">
            <?php else : ?>
                <select disabled="disabled"><option value="-1" selected="selected" disabled="true"><?php _e("Copy From"); ?></option></select>
                <input type="button" class="button action" value="<?php DUP_PRO_U::esc_attr_e("Apply") ?>" disabled="disabled">
            <?php endif; ?>
        </td>
        <td>
            <div class="btnnav">
                <a href="<?php echo $storage_tab_url; ?>" class="button"> <i class="fas fa-server fa-sm"></i> <?php DUP_PRO_U::esc_html_e('Providers'); ?></a>
                <?php if ($storage_id != -1) :
                    $add_storage_url = admin_url('admin.php?page=duplicator-pro-storage&tab=storage&inner_page=edit');
                    $add_storage_nonce_url = wp_nonce_url($add_storage_url, 'edit-storage');
                    ?>
                    <a href="<?php echo $add_storage_nonce_url;?>" class="button"><?php DUP_PRO_U::esc_html_e("Add New"); ?></a>
                <?php endif; ?>
            </div>
        </td>
    </tr>
</table>
<hr class="dpro-edit-toolbar-divider"/>


<!-- ====================
SUB-TABS -->
<?php
if (!is_null($error_message)) {
    DUP_PRO_UI_Notice::displayGeneralAdminNotice($error_message, DUP_PRO_UI_Notice::GEN_ERROR_NOTICE, true);
} elseif (!is_null($success_message)) {
    DUP_PRO_UI_Notice::displayGeneralAdminNotice($success_message, DUP_PRO_UI_Notice::GEN_SUCCESS_NOTICE, true);
}
?>

    <table class="form-table top-entry">
        <tr valign="top">
            <th scope="row"><label><?php DUP_PRO_U::esc_html_e("Name"); ?></label></th>
            <td>
                <input data-parsley-errors-container="#name_error_container" type="text" id="name" name="name" value="<?php echo esc_attr($storage->name); ?>" autocomplete="off" />
                <div id="name_error_container" class="duplicator-error-container"></div>
            </td>
        </tr>
        <tr valign="top">
            <th scope="row"><label><?php DUP_PRO_U::esc_html_e("Notes"); ?></label></th>
            <td><textarea id="notes" name="notes" style="width:100%; max-width: 500px"><?php echo esc_attr($storage->notes); ?></textarea></td>
        </tr>
        <tr valign="top">
            <th scope="row"><label><?php DUP_PRO_U::esc_html_e("Type"); ?></label></th>
            <td>
                <select id="change-mode" name="storage_type" onchange="DupPro.Storage.ChangeMode()">
                    <?php if (DUP_PRO_U::isCurlExists() && DUP_PRO_U::curlMultiEnabled()) : ?>
                        <?php
                            // Best guess for unrecognised future s3 providers is "other"
                            $best_guess_s3_provider = (
                                $storage->storage_type == DUP_PRO_Storage_Types::S3 &&
                                !in_array($storage->s3_provider, array("amazon", "backblaze"))
                            ) ? "other" : $storage->s3_provider;                            
                        ?>
                        <optgroup label="<?php DUP_PRO_U::esc_attr_e("Amazon S3 Compatible"); ?>">
                            <option <?php DUP_PRO_UI::echoSelected(
                                $storage->storage_type == DUP_PRO_Storage_Types::S3 &&
                                $best_guess_s3_provider == 'amazon');
                            ?> data-s3-provider='amazon' value="<?php echo esc_attr(DUP_PRO_Storage_Types::S3); ?>">
                                <?php DUP_PRO_U::esc_html_e("Amazon - S3 Direct"); ?>
                            </option>
                            <option <?php DUP_PRO_UI::echoSelected(
                                $storage->storage_type == DUP_PRO_Storage_Types::S3 &&
                                $best_guess_s3_provider == 'other');
                            ?> data-s3-provider='other' value="<?php echo esc_attr(DUP_PRO_Storage_Types::S3); ?>">
                                <?php DUP_PRO_U::esc_html_e("Amazon - 3rd Party Provider"); ?>
                            </option>
                            <option <?php DUP_PRO_UI::echoSelected(
                                $storage->storage_type == DUP_PRO_Storage_Types::S3 &&
                                $best_guess_s3_provider == 'backblaze');
                            ?> data-s3-provider='backblaze' value="<?php echo esc_attr(DUP_PRO_Storage_Types::S3); ?>">
                                <?php DUP_PRO_U::esc_html_e("Backblaze B2"); ?>
                            </option>
                        </optgroup>
                    <?php endif; ?>
                    <option <?php DUP_PRO_UI::echoSelected($storage->storage_type == DUP_PRO_Storage_Types::Dropbox); ?> value="<?php echo esc_attr(DUP_PRO_Storage_Types::Dropbox); ?>"><?php DUP_PRO_U::esc_html_e("Dropbox"); ?></option>
                    <?php
                    $ftp_connect_exists = function_exists('ftp_connect');
                    $ftp_connect_exists_filtered = apply_filters('duplicator_pro_ftp_connect_exists', $ftp_connect_exists);
                    if ($ftp_connect_exists_filtered) {
                        ?>
                        <option <?php DUP_PRO_UI::echoSelected($storage->storage_type == DUP_PRO_Storage_Types::FTP); ?> value="<?php echo esc_attr(DUP_PRO_Storage_Types::FTP); ?>"><?php DUP_PRO_U::esc_html_e("FTP"); ?></option>
                        <?php
                    }
                    if (extension_loaded('gmp')) : ?>
                        <option <?php DUP_PRO_UI::echoSelected($storage->storage_type == DUP_PRO_Storage_Types::SFTP); ?> value="<?php echo esc_attr(DUP_PRO_Storage_Types::SFTP); ?>"><?php DUP_PRO_U::esc_html_e("SFTP"); ?></option>
                    <?php endif; ?>
                    <?php if (DUP_PRO_StorageSupported::isGDriveSupported()) :?>
                        <option <?php DUP_PRO_UI::echoSelected($storage->storage_type == DUP_PRO_Storage_Types::GDrive); ?> value="<?php echo esc_attr(DUP_PRO_Storage_Types::GDrive); ?>"><?php DUP_PRO_U::esc_html_e("Google Drive"); ?></option>
                    <?php endif; ?>
                    <option <?php DUP_PRO_UI::echoSelected($storage->storage_type == DUP_PRO_Storage_Types::Local); ?> value="<?php echo esc_attr(DUP_PRO_Storage_Types::Local); ?>"><?php DUP_PRO_U::esc_html_e("Local Server"); ?></option>
                    <?php
                    if (DUP_PRO_StorageSupported::isOneDriveSupported()) {
                        $onedrive_deprecated_storage_option_disp =  (defined('DUPLICATOR_PRO_ONEDRIVE_DEPRECATED_STORAGE_OPTION_DISP')
                                        && DUPLICATOR_PRO_ONEDRIVE_DEPRECATED_STORAGE_OPTION_DISP)
                                        ? true
                                        : $storage->storage_type == DUP_PRO_Storage_Types::OneDrive;
                        if ($onedrive_deprecated_storage_option_disp) {
                            //Options is no longer used.
                            ?>
                            <!--option <?php DUP_PRO_UI::echoSelected($storage->storage_type == DUP_PRO_Storage_Types::OneDrive); ?> value="<?php echo DUP_PRO_Storage_Types::OneDrive; ?>"><?php DUP_PRO_U::esc_html_e("OneDrive v0.1"); ?></option-->
                            <?php
                        }
                        ?>
                        <option <?php DUP_PRO_UI::echoSelected($storage->storage_type == DUP_PRO_Storage_Types::OneDriveMSGraph); ?> value="<?php echo DUP_PRO_Storage_Types::OneDriveMSGraph; ?>"><?php DUP_PRO_U::esc_html_e("OneDrive"); ?></option>
                        <?php
                    }
                    ?>
                </select>
                <small class="dpro-store-type-notice">
                    <?php
                    $gDriveNotSupportedNotices = DUP_PRO_StorageSupported::getGDriveNotSupportedNotices();
                    if (!empty($gDriveNotSupportedNotices)) {
                        echo implode('<br/>', $gDriveNotSupportedNotices) . '<br/>';
                    }

                    if (!DUP_PRO_U::isCurlExists()) {
                        echo DUP_PRO_U::esc_html__("Amazon S3 (or Compatible) requires the PHP cURL extension and related functions to be enabled.") . '<br/>';
                    } elseif (!DUP_PRO_U::curlMultiEnabled()) {
                        echo DUP_PRO_U::esc_html__("Amazon S3 (or Compatible) requires 'curl_multi_' type functions to be enabled. One or more are disabled on your server.") . '<br/>';
                    }

                    if (!$ftp_connect_exists_filtered) {
                        printf(DUP_PRO_U::esc_html__('FTP requires FTP module enabled. Please install the FTP module as described in the %s.'), '<a href="https://secure.php.net/manual/en/ftp.installation.php" target="_blank">https://secure.php.net/manual/en/ftp.installation.php</a>');
                        echo '<br/>';
                    }

                    if (!extension_loaded('gmp')) {
                        echo wp_kses(
                            DUP_PRO_U::__('SFTP requires the <a href="http://php.net/manual/en/book.gmp.php" target="_blank">gmp extension</a>. Please contact your host to install.'),
                            array(
                            'a' => array(
                                'href' => array(),
                                'target' => array()),
                            )
                        ) . '<br/>';
                    }
                    $oneDriveNotSupportedNotices = DUP_PRO_StorageSupported::getOneDriveNotSupportedNotices();
                    if (!empty($oneDriveNotSupportedNotices)) {
                        echo implode('<br/>', $oneDriveNotSupportedNotices) . '<br/>';
                    }
                    ?>
                </small>
            </td>
        </tr>
    </table> <hr size="1" />


    <!-- ===============================
    AMAZON S3 PROVIDER -->
    <div id="provider-<?php echo esc_attr(DUP_PRO_Storage_Types::S3); ?>" class="provider dup-remove-on-submit-if-hidden">
        <!-- Input field #s3_provider will be filled by DupPro.Storage.ChangeMode, based on selected s3 provider -->
        <input type="hidden" id="s3_provider" class="invisible_out_of_screen"
               name="s3_provider" value="<?php echo esc_attr($storage->s3_provider); ?>">
        <!-- ===============================
        AMAZON S3 DIRECT -->
        <table id="s3-provider-amazon" class="form-table provider dup-remove-on-submit-if-hidden" >
            <tr>
                <td colspan="2" style="padding-left:0">
                    <i><?php echo wp_kses(
                        DUP_PRO_U::__("Amazon S3 Setup Guide: " .
                            "<a target='_blank' href='https://snapcreek.com/duplicator/docs/https://snapcreek.com/duplicator/docs/amazon-s3-step-by-step/'>Step-by-Step</a> " .
                            "and <a href='https://snapcreek.com/duplicator/docs/amazon-s3-policy-setup/' target='_blank'>User Bucket Policy</a>."),
                        array(
                        'a' => array(
                                'href' => array(),
                                'target' => array()
                            )
                        )
                    ); ?></i>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for=""><?php DUP_PRO_U::esc_html_e("Authorization"); ?></label></th>
                <td class="dup-s3-auth-account">
                    <h3>
                        <img src="<?php echo DUPLICATOR_PRO_IMG_URL ?>/aws-24.png" class="dup-store-auth-icon" alt="" />
                        <?php DUP_PRO_U::esc_html_e('Amazon Account'); ?><br/>
                    </h3>
                    <table class="dup-form-sub-area">
                      <tr>
                          <th scope="row"><label for="s3_access_key_amazon"><?php DUP_PRO_U::esc_html_e("Access Key"); ?>:</label></th>
                          <td>
                              <input id="s3_access_key_amazon" name="s3_access_key" data-parsley-errors-container="#s3_access_key_amazon_error_container" type="text" autocomplete="off" value="<?php echo esc_attr($storage->s3_access_key); ?>">
                              <div id="s3_access_key_amazon_error_container" class="duplicator-error-container"></div>
                          </td>
                      </tr>
                      <tr>
                          <th scope="row">
                              <label for="s3_secret_key_amazon"><?php DUP_PRO_U::esc_html_e("Secret Key"); ?>:</label>
                          </th>

                          <td>
                                <input
                                    id="s3_secret_key_amazon"
                                    name="s3_secret_key"
                                    type="password"
                                    placeholder="<?php echo str_repeat("*", SnapString::stringLength($storage->s3_secret_key)); ?>"
                                    data-parsley-errors-container="#s3_secret_key_amazon_error_container"
                                    autocomplete="off"
                                    value=""
                                >
                              <div id="s3_secret_key_amazon_error_container" class="duplicator-error-container"></div>
                          </td>
                      </tr>
                  </table>
                </td>
            </tr>            
            <tr>
                <th scope="row"></th>
                <td>
                    <table class="dup-form-sub-area dup-s3-auth-provider">
                        <tr class="invisible_out_of_screen">
                            <th><label for="s3_endpoint_amazon"><?php DUP_PRO_U::esc_html_e("Endpoint URL"); ?>:</label></th>
                            <td>
                                <input type="text" id="s3_endpoint_amazon" name="s3_endpoint" value="">
                            </td>
                        </tr>
                        <tr>
                            <th><label for="s3_region_amazon"><?php DUP_PRO_U::esc_html_e("Region"); ?>:</label></th>
                            <td>
                                <select id="s3_region_amazon" name="s3_region">
                                    <?php
                                    foreach (DUP_PRO_Storage_Entity::s3_amazon_direct_region_options() as $value => $label) {
                                        ?>
                                        <option
                                            <?php selected($storage->s3_region, $value); ?>
                                            value="<?php echo esc_attr($value); ?>">
                                            <?php echo esc_html($label . " - '" . $value . "'"); ?>
                                        </option>
                                        <?php
                                    }
                                    ?>                                    
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="s3_storage_class_amazon"><?php DUP_PRO_U::esc_html_e("Storage Class"); ?>:</label></th>
                            <td>
                                <select id="s3_storage_class_amazon" name="s3_storage_class">
                                    <option <?php DUP_PRO_UI::echoSelected($storage->s3_storage_class == 'REDUCED_REDUNDANCY'); ?> value="REDUCED_REDUNDANCY"><?php DUP_PRO_U::esc_html_e("Reduced Redundancy"); ?></option>
                                    <option <?php DUP_PRO_UI::echoSelected($storage->s3_storage_class == 'STANDARD'); ?> value="STANDARD"><?php DUP_PRO_U::esc_html_e("Standard"); ?></option>
                                    <option <?php DUP_PRO_UI::echoSelected($storage->s3_storage_class == 'STANDARD_IA'); ?> value="STANDARD_IA"><?php DUP_PRO_U::esc_html_e("Standard IA"); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="_s3_storage_folder_amazon"><?php DUP_PRO_U::esc_html_e("Storage Folder"); ?>:</label></th>
                            <td>
                                <input id="_s3_storage_folder_amazon" name="_s3_storage_folder" type="text" value="<?php echo esc_attr($storage->s3_storage_folder); ?>">
                                <p><i><?php DUP_PRO_U::esc_html_e("Folder where packages will be stored. This should be unique for each web-site using Duplicator."); ?></i></p>
                            </td>
                        </tr>
                    </table>

                </td>
            </tr>
            <tr>
                <th scope="row"><label for="s3_bucket_amazon"><?php DUP_PRO_U::esc_html_e("Bucket"); ?></label></th>
                <td>
                    <input id="s3_bucket_amazon" name="s3_bucket" type="text" value="<?php echo esc_attr($storage->s3_bucket); ?>">
                    <p><i><?php DUP_PRO_U::esc_html_e("S3 Bucket where you want to save the backups."); ?></i></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="s3_max_files_amazon"><?php DUP_PRO_U::esc_html_e("Max Packages"); ?></label></th>
                <td>
                    <label for="s3_max_files_amazon">
                        <input id="s3_max_files_amazon" class="s3_max_files" name="s3_max_files" data-parsley-errors-container="#s3_max_files_amazon_error_container" type="text" value="<?php echo absint($storage->s3_max_files); ?>">
                        <?php DUP_PRO_U::esc_html_e("Number of packages to keep in folder."); ?><br/>
                        <i><?php DUP_PRO_U::esc_html_e("When this limit is exceeded, the oldest package will be deleted. Set to 0 for no limit."); ?></i>
                    </label>
                    <div id="s3_max_files_amazon_error_container" class="duplicator-error-container"></div>
                </td>
            </tr>
            <tr valign="top" class="invisible_out_of_screen">
                <th scope="row"><label><?php DUP_PRO_U::esc_html_e("Additional Settings"); ?></label></th>
                <td>
                    <input type="checkbox" name="s3_ACL_full_control" id="s3_ACL_full_control_amazon" value="1" <?php checked(true, true); ?> />
                    <label for="s3_ACL_full_control_amazon"><?php DUP_PRO_U::esc_html_e("Enable full control ACL"); ?> </label><br />
                    <p class="description">
                        <?php DUP_PRO_U::esc_html_e("Only uncheck if object-level ACLs are not supported (for example for Backblaze provider)."); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for=""><?php DUP_PRO_U::esc_html_e("Connection"); ?></label></th>
                <td>
                    <button class="button button-large button_s3_test" id="button_s3_send_file_test_amazon" type="button" onclick="DupPro.Storage.S3.SendFileTest(); return false;">
                        <i class="fas fa-cloud-upload-alt fa-sm"></i> <?php DUP_PRO_U::esc_html_e('Test S3 Connection'); ?>
                    </button>
                    <p><i><?php DUP_PRO_U::esc_html_e("Test connection by sending and receiving a small file to/from the account."); ?></i></p>
                </td>
            </tr>
        </table>
        <!-- ===============================
        AMAZON S3 3rd PARTY PROVIDER -->
        <table id="s3-provider-other" class="form-table provider dup-remove-on-submit-if-hidden" >
            <tr>
                <td colspan="2" style="padding-left:0">
                    <i><?php echo wp_kses(
                        DUP_PRO_U::__("Amazon S3 Setup Guide: " .
                            "<a target='_blank' href='https://snapcreek.com/duplicator/docs/https://snapcreek.com/duplicator/docs/amazon-s3-step-by-step/'>Step-by-Step</a> " .
                            "and <a href='https://snapcreek.com/duplicator/docs/amazon-s3-policy-setup/' target='_blank'>User Bucket Policy</a>."),
                        array(
                        'a' => array(
                                'href' => array(),
                                'target' => array()
                            )
                        )
                    ); ?></i>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for=""><?php DUP_PRO_U::esc_html_e("Authorization"); ?></label></th>
                <td class="dup-s3-auth-account">
                    <h3>
                        <img src="<?php echo DUPLICATOR_PRO_IMG_URL ?>/aws-24.png" class="dup-store-auth-icon" alt="" />
                        <?php DUP_PRO_U::esc_html_e('Amazon Account:'); ?>
                        <span class="account-heading-info">3rd Party Provider</span>
                        <br/>
                    </h3>
                    <table class="dup-form-sub-area">
                      <tr>
                          <th scope="row"><label for="s3_access_key_other"><?php DUP_PRO_U::esc_html_e("Access Key"); ?>:</label></th>
                          <td>
                              <input id="s3_access_key_other" name="s3_access_key" data-parsley-errors-container="#s3_access_key_other_error_container" type="text" autocomplete="off" value="<?php echo esc_attr($storage->s3_access_key); ?>">
                              <div id="s3_access_key_other_error_container" class="duplicator-error-container"></div>
                          </td>
                      </tr>
                      <tr>
                          <th scope="row">
                              <label for="s3_secret_key_other"><?php DUP_PRO_U::esc_html_e("Secret Key"); ?>:</label>
                          </th>

                          <td>
                                <input
                                    id="s3_secret_key_other"                                    
                                    name="s3_secret_key"
                                    type="password"
                                    placeholder="<?php echo str_repeat("*", SnapString::stringLength($storage->s3_secret_key)); ?>"
                                    data-parsley-errors-container="#s3_secret_key_other_error_container"
                                    autocomplete="off"
                                    value=""
                                >
                              <div id="s3_secret_key_other_error_container" class="duplicator-error-container"></div>
                          </td>
                      </tr>
                  </table>
                </td>
            </tr>
            <tr>
                <th scope="row"></th>
                <td>
                    <table class="dup-form-sub-area dup-s3-auth-provider">
                        <tr>
                            <th><label for="s3_endpoint_other"><?php DUP_PRO_U::esc_html_e("Endpoint URL"); ?>:</label></th>
                            <td>
                                <input type="text" id="s3_endpoint_other" name="s3_endpoint" value="<?php echo esc_attr($storage->s3_endpoint); ?>">
                            </td>
                        </tr>
                        <tr>
                            <th><label for="s3_region_other"><?php DUP_PRO_U::esc_html_e("Region"); ?>:</label></th>
                            <td>
                                <input type="text" id="s3_region_other" name="s3_region" value="<?php echo esc_attr($storage->s3_region); ?>">
                                <p><i><?php DUP_PRO_U::esc_html_e("Please fill s3 bucket region slug. Space is not allowed."); ?></i></p>
                            </td>
                        </tr>
                        <tr class="invisible_out_of_screen">
                            <th><label for="s3_storage_class_other"><?php DUP_PRO_U::esc_html_e("Storage Class"); ?>:</label></th>
                            <td>
                                <select id="s3_storage_class_other" name="s3_storage_class">
                                    <option <?php DUP_PRO_UI::echoSelected(true); ?> value="STANDARD"><?php DUP_PRO_U::esc_html_e("Standard"); ?></option>
                                </select>
                            </td>
                        </tr>                        
                        <tr>
                            <th><label for="_s3_storage_folder_other"><?php DUP_PRO_U::esc_html_e("Storage Folder"); ?>:</label></th>
                            <td>
                                <input id="_s3_storage_folder_other" name="_s3_storage_folder" type="text" value="<?php echo esc_attr($storage->s3_storage_folder); ?>">
                                <p><i><?php DUP_PRO_U::esc_html_e("Folder where packages will be stored. This should be unique for each web-site using Duplicator."); ?></i></p>
                            </td>
                        </tr>
                    </table>

                </td>
            </tr>
            <tr>
                <th scope="row"><label for="s3_bucket_other"><?php DUP_PRO_U::esc_html_e("Bucket"); ?></label></th>
                <td>
                    <input id="s3_bucket_other" name="s3_bucket" type="text" value="<?php echo esc_attr($storage->s3_bucket); ?>">
                    <p><i><?php DUP_PRO_U::esc_html_e("S3 Bucket where you want to save the backups."); ?></i></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="s3_max_files_other"><?php DUP_PRO_U::esc_html_e("Max Packages"); ?></label></th>
                <td>
                    <label for="s3_max_files_other">
                        <input id="s3_max_files_other" class="s3_max_files" name="s3_max_files" data-parsley-errors-container="#s3_max_files_other_error_container" type="text" value="<?php echo absint($storage->s3_max_files); ?>">
                        <?php DUP_PRO_U::esc_html_e("Number of packages to keep in folder."); ?><br/>
                        <i><?php DUP_PRO_U::esc_html_e("When this limit is exceeded, the oldest package will be deleted. Set to 0 for no limit."); ?></i>
                    </label>
                    <div id="s3_max_files_other_error_container" class="duplicator-error-container"></div>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row"><label><?php DUP_PRO_U::esc_html_e("Additional Settings"); ?></label></th>
                <td>
                    <input type="checkbox" name="s3_ACL_full_control" id="s3_ACL_full_control_other" value="1" <?php checked($storage->s3_ACL_full_control, true); ?> />
                    <label for="s3_ACL_full_control_other"><?php DUP_PRO_U::esc_html_e("Enable full control ACL"); ?> </label><br />
                    <p class="description">
                        <?php DUP_PRO_U::esc_html_e("Only uncheck if object-level ACLs are not supported (for example for Backblaze provider)."); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for=""><?php DUP_PRO_U::esc_html_e("Connection"); ?></label></th>
                <td>
                    <button class="button button-large button_s3_test" id="button_s3_send_file_test_other" type="button" onclick="DupPro.Storage.S3.SendFileTest(); return false;">
                        <i class="fas fa-cloud-upload-alt fa-sm"></i> <?php DUP_PRO_U::esc_html_e('Test S3 Connection'); ?>
                    </button>
                    <p><i><?php DUP_PRO_U::esc_html_e("Test connection by sending and receiving a small file to/from the account."); ?></i></p>
                </td>
            </tr>
        </table>
        <!-- ===============================
        S3 BACKBLAZE -->
        <table id="s3-provider-backblaze" class="form-table provider dup-remove-on-submit-if-hidden" >
            <tr>
                <td colspan="2" style="padding-left:0">
                    <i><?php
                        echo wp_kses(
                            DUP_PRO_U::__("Amazon S3 Setup Guide: <a target='_blank' " .
                                "href='https://snapcreek.com/duplicator/docs/https://snapcreek.com/duplicator/docs/amazon-s3-step-by-step/'>Step-by-Step</a> " .
                                "and <a href='https://snapcreek.com/duplicator/docs/amazon-s3-policy-setup/' target='_blank'>User Bucket Policy</a>. " .
                                "Documentation for Backblaze B2: " .
                                "<a target='_blank' href='https://www.backblaze.com/b2/docs/'>Overview</a>, " .
                                "<a target='_blank' href='https://www.backblaze.com/b2/docs/s3_compatible_api.html'>S3 Compatible API</a>."),
                            array(
                                'a' => array(
                                    'href' => array(),
                                    'target' => array()
                                )
                            )
                        );
                    ?></i>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for=""><?php DUP_PRO_U::esc_html_e("Authorization"); ?></label></th>
                <td class="dup-s3-auth-account">
                    <h3>
                        <img src="<?php echo DUPLICATOR_PRO_IMG_URL ?>/backblaze-icon.png" class="dup-store-auth-icon" alt="" />
                        <?php DUP_PRO_U::esc_html_e('Backblaze Account'); ?><br/>
                    </h3>
                    <table class="dup-form-sub-area">
                      <tr>
                          <th scope="row"><label for="s3_access_key_backblaze"><?php DUP_PRO_U::esc_html_e("Key ID"); ?>:</label></th>
                          <td>
                              <input id="s3_access_key_backblaze" name="s3_access_key" data-parsley-errors-container="#s3_access_key_backblaze_error_container" type="text" autocomplete="off" value="<?php echo esc_attr($storage->s3_access_key); ?>">
                              <div id="s3_access_key_backblaze_error_container" class="duplicator-error-container"></div>
                          </td>
                      </tr>
                      <tr>
                          <th scope="row">
                              <label for="s3_secret_key_backblaze"><?php DUP_PRO_U::esc_html_e("Application Key"); ?>:</label>
                          </th>

                          <td>
                                <input
                                    id="s3_secret_key_backblaze"
                                    name="s3_secret_key"
                                    type="password"
                                    placeholder="<?php echo str_repeat("*", SnapString::stringLength($storage->s3_secret_key)); ?>"
                                    data-parsley-errors-container="#s3_secret_key_backblaze_error_container"
                                    autocomplete="off"
                                    value=""
                                >
                              <div id="s3_secret_key_backblaze_error_container" class="duplicator-error-container"></div>
                          </td>
                      </tr>
                  </table>
                </td>
            </tr>
            <tr>
                <th scope="row"></th>
                <td>
                    <table class="dup-form-sub-area dup-s3-auth-provider">
                        <tr>
                            <th><label for="s3_endpoint_backblaze"><?php DUP_PRO_U::esc_html_e("Endpoint URL"); ?>:</label></th>
                            <td>
                                <input type="text" id="s3_endpoint_backblaze" name="s3_endpoint" value="<?php echo esc_attr($storage->s3_endpoint); ?>">
                            </td>
                        </tr>
                        <tr>
                            <th><label for="s3_region_backblaze"><?php DUP_PRO_U::esc_html_e("Region"); ?>:</label></th>
                            <td>
                                <input type="text" readonly id="s3_region_backblaze" name="s3_region" value="<?php echo esc_attr($storage->s3_region); ?>">
                                <p><i><?php DUP_PRO_U::esc_html_e("Region is taken from between first 2 dots in Endpoint URL, for example: s3.<region>.backblazeb2.com"); ?></i></p>
                            </td>
                        </tr>
                        <tr class="invisible_out_of_screen">
                            <th><label for="s3_storage_class_backblaze"><?php DUP_PRO_U::esc_html_e("Storage Class"); ?>:</label></th>
                            <td>
                                <select id="s3_storage_class_backblaze" name="s3_storage_class">
                                    <option <?php DUP_PRO_UI::echoSelected(true); ?> value="STANDARD"><?php DUP_PRO_U::esc_html_e("Standard"); ?></option>
                                </select>
                            </td>
                        </tr>                        
                        <tr>
                            <th><label for="_s3_storage_folder_backblaze"><?php DUP_PRO_U::esc_html_e("Storage Folder"); ?>:</label></th>
                            <td>
                                <input id="_s3_storage_folder_backblaze" name="_s3_storage_folder" type="text" value="<?php echo esc_attr($storage->s3_storage_folder); ?>">
                                <p><i><?php DUP_PRO_U::esc_html_e("Folder where packages will be stored. This should be unique for each web-site using Duplicator."); ?></i></p>
                            </td>
                        </tr>
                    </table>

                </td>
            </tr>
            <tr>
                <th scope="row"><label for="s3_bucket_backblaze"><?php DUP_PRO_U::esc_html_e("Bucket"); ?></label></th>
                <td>
                    <input id="s3_bucket_backblaze" name="s3_bucket" type="text" value="<?php echo esc_attr($storage->s3_bucket); ?>">
                    <p><i><?php DUP_PRO_U::esc_html_e("S3 Bucket where you want to save the backups."); ?></i></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="s3_max_files_backblaze"><?php DUP_PRO_U::esc_html_e("Max Packages"); ?></label></th>
                <td>
                    <label for="s3_max_files_backblaze">
                        <input id="s3_max_files_backblaze" class="s3_max_files" name="s3_max_files" data-parsley-errors-container="#s3_max_files_backblaze_error_container" type="text" value="<?php echo absint($storage->s3_max_files); ?>">
                        <?php DUP_PRO_U::esc_html_e("Number of packages to keep in folder."); ?><br/>
                        <i><?php DUP_PRO_U::esc_html_e("When this limit is exceeded, the oldest package will be deleted. Set to 0 for no limit."); ?></i>
                    </label>
                    <div id="s3_max_files_backblaze_error_container" class="duplicator-error-container"></div>
                </td>
            </tr>
            <tr valign="top" class="invisible_out_of_screen">
                <th scope="row"><label><?php DUP_PRO_U::esc_html_e("Additional Settings"); ?></label></th>
                <td>
                    <input type="checkbox" name="s3_ACL_full_control" id="s3_ACL_full_control_backblaze" value="1" <?php checked(false, true); ?> />
                    <label for="s3_ACL_full_control_backblaze"><?php DUP_PRO_U::esc_html_e("Enable full control ACL"); ?> </label><br />
                    <p class="description">
                        <?php DUP_PRO_U::esc_html_e("Only uncheck if object-level ACLs are not supported (for example for Backblaze provider)."); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for=""><?php DUP_PRO_U::esc_html_e("Connection"); ?></label></th>
                <td>
                    <button class="button button-large button_s3_test" id="button_s3_send_file_test_backblaze" type="button" onclick="DupPro.Storage.S3.SendFileTest(); return false;">
                        <i class="fas fa-cloud-upload-alt fa-sm"></i> <?php DUP_PRO_U::esc_html_e('Test S3 Connection'); ?>
                    </button>
                    <p><i><?php DUP_PRO_U::esc_html_e("Test connection by sending and receiving a small file to/from the account."); ?></i></p>
                </td>
            </tr>
        </table>
    </div>

    <!-- ===============================
    DROP-BOX PROVIDER -->
    <table id="provider-<?php echo DUP_PRO_Storage_Types::Dropbox ?>" class="form-table provider dup-remove-on-submit-if-hidden" >
        <tr>
            <th scope="row"><label><?php DUP_PRO_U::esc_html_e("Authorization"); ?></label></th>
            <td class="dropbox-authorize">
                <div class="authorization-state" id="state-unauthorized">
                    <!-- CONNECT -->
                    <button id="dpro-dropbox-connect-btn" type="button" class="button button-large" onclick="DupPro.Storage.Dropbox.DropboxGetAuthUrl();">
                        <i class="fa fa-plug"></i> <?php DUP_PRO_U::esc_html_e('Connect to Dropbox'); ?>
                        <img src="<?php echo esc_url(DUPLICATOR_PRO_IMG_URL . '/dropbox-24.png'); ?>" style='vertical-align: middle; margin:-2px 0 0 3px; height:18px; width:18px' />
                    </button>
                </div>

                <div class="authorization-state" id="state-waiting-for-request-token">
                    <div style="padding:10px">
                        <i class="fas fa-circle-notch fa-spin"></i> <?php DUP_PRO_U::esc_html_e('Getting Dropbox request token...'); ?>
                    </div>
                </div>

                <div class="authorization-state" id="state-waiting-for-auth-button-click">
                    <!-- STEP 2 -->
                    <b><?php DUP_PRO_U::esc_html_e("Step 1:"); ?></b>&nbsp;
                    <?php DUP_PRO_U::esc_html_e(' Duplicator needs to authorize at the Dropbox.com website.'); ?>
                    <div class="auth-code-popup-note">
                    <?php echo $txt_auth_note ?>
                    </div>
                    <button id="auth-redirect" type="button" class="button button-large" onclick="DupPro.Storage.Dropbox.OpenAuthPage(); return false;">
                        <i class="fa fa-user"></i> <?php DUP_PRO_U::esc_html_e('Authorize Dropbox'); ?>
                    </button>
                    <br/><br/>

                    <div id="dropbox-auth-code-area">
                        <b><?php DUP_PRO_U::esc_html_e('Step 2:'); ?></b> <?php DUP_PRO_U::esc_html_e("Paste code from Dropbox authorization page."); ?> <br/>
                        <input style="width:400px" id="dropbox-auth-code" name="dropbox-auth-code" />
                    </div>

                    <!-- STEP 3 -->
                    <b><?php DUP_PRO_U::esc_html_e("Step 3:"); ?></b>&nbsp;
                    <?php DUP_PRO_U::esc_html_e('Finalize Dropbox validation by clicking the "Finalize Setup" button.'); ?>
                    <br/>
                    <button type="button" class="button" id="dropbox-finalize-setup" onclick="DupPro.Storage.Dropbox.FinalizeSetup(); return false;"><i class="fa fa-check-square"></i> <?php DUP_PRO_U::esc_html_e('Finalize Setup'); ?></button>
                </div>

                <div class="authorization-state" id="state-waiting-for-access-token">
                    <div><i class="fas fa-circle-notch fa-spin"></i> <?php DUP_PRO_U::esc_html_e('Performing final authorization...Please wait'); ?></div>
                </div>

                <div class="authorization-state" id="state-authorized" style="margin-top:-5px">
                <?php if ($storage->dropbox_authorization_state == DUP_PRO_Dropbox_Authorization_States::Authorized) : ?>
                    <h3>
                        <img src="<?php echo DUPLICATOR_PRO_IMG_URL ?>/dropbox-24.png" class="dup-store-auth-icon" alt="" />
                        <?php DUP_PRO_U::esc_html_e('Dropbox Account'); ?><br/>
                        <i class="dpro-edit-info"><?php DUP_PRO_U::esc_html_e('Duplicator has been authorized to access this user\'s Dropbox account'); ?></i>
                    </h3>
                    <div id="dropbox-account-info">
                        <label><?php DUP_PRO_U::esc_html_e('Name'); ?>:</label>
                        <?php echo esc_html($account_info->name->display_name); ?><br/>

                        <label><?php DUP_PRO_U::esc_html_e('Email'); ?>:</label>
                        <?php echo esc_html($account_info->email); ?>
                        <?php
                        if (is_a($dropbox, 'DUP_PRO_DropboxV2Client')) {
                            $quota = $dropbox->getQuota();
                            if (isset($quota->used) && isset($quota->allocation->allocated)) {
                                ?>
                                <br/>
                                <label><?php DUP_PRO_U::esc_html_e('Quota Usage'); ?>:</label>
                                <?php
                                $quota_used = $quota->used;
                                $quota_total = $quota->allocation->allocated;
                                $used_perc = round($quota_used * 100 / $quota_total, 1);
                                $available_quota = $quota_total - $quota_used;
                                printf(DUP_PRO_U::__('%s %% used, %s available'), $used_perc, round($available_quota / 1048576, 1) . ' MB');
                            }
                        }

                        checked($used_perc);
                        ?>
                    </div>
                <?php endif; ?>
                    <br/>

                    <button type="button" class="button" onclick='DupPro.Storage.Dropbox.CancelAuthorization();'>
                        <?php DUP_PRO_U::esc_html_e('Cancel Authorization'); ?>
                    </button><br/>
                    <i class="dpro-edit-info"><?php DUP_PRO_U::esc_html_e('Disassociates storage provider with the Dropbox account. Will require re-authorization.'); ?> </i>
                </div>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="_dropbox_storage_folder"><?php DUP_PRO_U::esc_html_e("Storage Folder"); ?></label></th>
            <td>
                <b>//Dropbox/Apps/Duplicator Pro/</b>
                <input id="_dropbox_storage_folder" name="_dropbox_storage_folder" type="text" value="<?php echo esc_attr($storage->dropbox_storage_folder); ?>" class="dpro-storeage-folder-path" />
                <p><i><?php DUP_PRO_U::esc_html_e("Folder where packages will be stored. This should be unique for each web-site using Duplicator."); ?></i></p>
            </td>
        </tr>
        <tr>
            <th scope="row"><label><?php DUP_PRO_U::esc_html_e("Hash Validation"); ?></label></th>
            <td>
                <input type="checkbox" id="dropbox_skip_archive_validation_hash" name="dropbox_skip_archive_validation_hash" value="1" <?php checked($storage->dropbox_skip_archive_validation_hash);?> >                    
                <label for="dropbox_skip_archive_validation_hash">Disable</label>
                 <br/>                                                    
                <p><?php DUP_PRO_U::esc_html_e("Turn off hash validation. This is less reliable, but may be required when transferring very large archives to avoid timeouts."); ?></p>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for=""><?php DUP_PRO_U::esc_html_e("Max Packages"); ?></label></th>
            <td>
                <label for="dropbox_max_files">
                    <input data-parsley-errors-container="#dropbox_max_files_error_container" id="dropbox_max_files" name="dropbox_max_files" type="text" value="<?php echo esc_attr($storage->dropbox_max_files); ?>" maxlength="4">
                    <?php DUP_PRO_U::esc_html_e("Number of packages to keep in folder."); ?> <br/>
                    <i><?php DUP_PRO_U::esc_html_e("When this limit is exceeded, the oldest package will be deleted. Set to 0 for no limit."); ?></i>
                </label>
                <div id="dropbox_max_files_error_container" class="duplicator-error-container"></div>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for=""><?php DUP_PRO_U::esc_html_e("Connection"); ?></label></th>
            <td>
                <button class="button button_dropbox_test" id="button_dropbox_send_file_test" type="button" onclick="DupPro.Storage.Dropbox.SendFileTest(); return false;">
                    <i class="fas fa-cloud-upload-alt fa-sm"></i>   <?php DUP_PRO_U::esc_html_e('Test Dropbox Connection'); ?>
                </button>
                <p><i><?php DUP_PRO_U::esc_html_e("Test connection by sending and receiving a small file to/from the account."); ?></i></p>
            </td>
        </tr>
    </table>    

    <!-- ===============================
    ONE-DRIVE PROVIDER -->
    <table id="provider-<?php echo DUP_PRO_Storage_Types::OneDrive ?>" class="form-table provider dup-remove-on-submit-if-hidden" >
        <tr>
            <th scope="row"><label><?php DUP_PRO_U::esc_html_e("Authorization"); ?></label></th>
            <td class="onedrive-authorize">
            <?php if ($storage->onedrive_authorization_state == DUP_PRO_OneDrive_Authorization_States::Unauthorized) : ?>
                    <div class='onedrive-authorization-state' id="onedrivestate-unauthorized">
                        <!-- CONNECT -->
                        <button id="dpro-onedrive-connect-btn" type="button" class="button button-large" onclick="DupPro.Storage.OneDrive.GetAuthUrl(); return false;">
                            <i class="fa fa-plug"></i> <?php DUP_PRO_U::esc_html_e('Connect to OneDrive Personal'); ?>
                        </button>
                        <button id="dpro-onedrive-business-connect-btn" type="button" class="button button-large" onclick="DupPro.Storage.OneDrive.GetAuthUrl(1); return false;">
                            <i class="fa fa-plug"></i> <?php DUP_PRO_U::esc_html_e('Connect to OneDrive Business'); ?>
                        </button>

                        <div class='onedrive-auth-container' style="display: none;">
                            <!-- STEP 2 -->
                            <b><?php DUP_PRO_U::esc_html_e("Step 1:"); ?></b>&nbsp;
                            <?php DUP_PRO_U::esc_html_e(' Duplicator needs to authorize at OneDrive.'); ?>
                            <div class="auth-code-popup-note" style="margin-top:1px">
                            <?php echo $txt_auth_note ?>
                            </div>
                            <button id="auth-redirect-od" type="button" class="button button-large" onclick="DupPro.Storage.OneDrive.OpenAuthPage(); return false;">
                                <i class="fa fa-user"></i> <?php DUP_PRO_U::esc_html_e('Authorize Onedrive'); ?>
                            </button>
                            <br/><br/>

                            <div id="onedrive-auth-container">
                                <b><?php DUP_PRO_U::esc_html_e('Step 2:'); ?></b> <?php DUP_PRO_U::esc_html_e("Paste code from OneDrive authorization page."); ?> <br/>
                                <input style="width:400px" id="onedrive-auth-code" name="onedrive-auth-code" />
                            </div>
                            <br><br>
                            <!-- STEP 3 -->
                            <b><?php DUP_PRO_U::esc_html_e("Step 3:"); ?></b>&nbsp;
                            <?php DUP_PRO_U::esc_html_e('Finalize OneDrive validation by clicking the "Finalize Setup" button.'); ?>
                            <br/>
                            <button type="button" class="button" onclick="DupPro.Storage.OneDrive.FinalizeSetup(); return false;"><i class="fa fa-check-square"></i> <?php DUP_PRO_U::esc_html_e('Finalize Setup'); ?></button>
                        </div>
                    </div>
                    <input type="hidden" id="onedrive-is-business" name="onedrive-is-business" value="0">
            <?php endif; ?>
                <div class='onedrive-authorization-state' id="onedrive-state-authorized" style="margin-top:-5px">
                <?php if ($storage->onedrive_authorization_state == DUP_PRO_OneDrive_Authorization_States::Authorized) : ?>
                        <h3>
                        <?php echo (!$storage->onedrive_is_business()) ? DUP_PRO_U::__('OneDrive Personal Account') : DUP_PRO_U::__('OneDrive Business Account'); ?><br/>
                            <i class="dpro-edit-info"><?php DUP_PRO_U::esc_html_e('Duplicator has been authorized to access this user\'s OneDrive account'); ?></i>
                        </h3>
                        
                        <?php
                        if (isset($onedrive_account_info)) {
                            ?>
                            <div id="onedrive-account-info">
                                <label><?php DUP_PRO_U::esc_html_e('Name'); ?>:</label>
                                <?php echo esc_html($onedrive_account_info->displayName); ?> <br/>
                            </div>
                        </div>
                            <?php
                        } elseif (isset($onedrive_state_token->data->error)) {
                            ?>
                            <div class="error-txt">
                                <?php
                                printf(DUP_PRO_U::esc_html__('Error: %s'), $onedrive_state_token->data->error_description);
                                // echo '<br/>';
                                // $obtained = $onedrive_state_token->obtained;
                                // printf(DUP_PRO_U::esc_html__('Last authorized date time : %s'), date('d-M-Y H:i:s a', $obtained));
                                echo '<br/><strong>';
                                DUP_PRO_U::esc_html_e('Please click on the "Cancel Authorization" button and reauthorize the OneDrive storage');
                                echo '</strong>';
                                ?>
                            </div>
                            <?php
                        }
                        ?>
                        <br/>
                        <button type="button" class="button" onclick='DupPro.Storage.OneDrive.CancelAuthorization();'>
                            <?php DUP_PRO_U::esc_html_e('Cancel Authorization'); ?>
                        </button><br/>
                        <i class="dpro-edit-info"><?php DUP_PRO_U::esc_html_e('Disassociates storage provider with the OneDrive account. Will require re-authorization.'); ?> </i>
                <?php endif; ?>
                </div>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="_onedrive_storage_folder"><?php DUP_PRO_U::esc_html_e("Storage Folder"); ?></label></th>
            <td>
                <b>//OneDrive/Apps/Duplicator Pro/</b>
                <input id="_onedrive_storage_folder" name="_onedrive_storage_folder" type="text" value="<?php echo esc_attr($storage->onedrive_storage_folder); ?>" 
                    class="dpro-storeage-folder-path" data-parsley-pattern="^((?!\:).)*[^\.\:]$"
                    data-parsley-errors-container="#onedrive_storage_folder_error_container"
                    data-parsley-pattern-message="<?php echo DUP_PRO_U::esc_attr__('The folder path shouldn\'t include the special character colon(":") or shouldn\'t end with a dot(".").'); ?>" />
                <p><i><?php DUP_PRO_U::esc_html_e("Folder where packages will be stored. This should be unique for each web-site using Duplicator."); ?></i></p>
                <div id="onedrive_storage_folder_error_container" class="duplicator-error-container"></div>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for=""><?php DUP_PRO_U::esc_html_e("Max Packages"); ?></label></th>
            <td>
                <label for="onedrive_max_files">
                    <input data-parsley-errors-container="#onedrive_max_files_error_container" id="onedrive_max_files" name="onedrive_max_files" type="text" value="<?php echo absint($storage->onedrive_max_files); ?>" maxlength="4">
                    <?php DUP_PRO_U::esc_html_e("Number of packages to keep in folder."); ?> <br/>
                    <i><?php DUP_PRO_U::esc_html_e("When this limit is exceeded, the oldest package will be deleted. Set to 0 for no limit."); ?></i>
                </label>
                <div id="onedrive_max_files_error_container" class="duplicator-error-container"></div>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for=""><?php DUP_PRO_U::esc_html_e("Connection"); ?></label></th>
            <td>
                <button class="button button-large button_onedrive_test" id="button_onedrive_send_file_test" type="button" onclick="DupPro.Storage.OneDrive.SendFileTest(); return false;">
                    <i class="fas fa-cloud-upload-alt fa-sm"></i>   <?php DUP_PRO_U::esc_html_e('Test OneDrive Connection'); ?>
                </button>
                <p><i><?php DUP_PRO_U::esc_html_e("Test connection by sending and receiving a small file to/from the account."); ?></i></p>
            </td>
        </tr>
    </table>

    <!-- ===============================
    ONE-DRIVE MSGraph PROVIDER -->
    <table id="provider-<?php echo DUP_PRO_Storage_Types::OneDriveMSGraph;?>" class="form-table provider dup-remove-on-submit-if-hidden" >
        <tr>
            <th scope="row"><label><?php DUP_PRO_U::esc_html_e("Authorization"); ?></label></th>
            <td class="onedrive-authorize">
                <?php if ($storage->onedrive_authorization_state == DUP_PRO_OneDrive_Authorization_States::Unauthorized) : ?>
                    <div class='onedrive-msgraph-authorization-state' id="onedrive-msgraph-state-unauthorized">
                        <?php DUP_PRO_U::esc_html_e('All folders read write permission:'); ?>
                        <label class="switch">
                        <input id="onedrive_msgraph_all_folders_read_write_perm" name="onedrive_msgraph_all_folders_read_write_perm" type="checkbox" value="1" />
                            <span class="slider round"></span>
                        </label>
                        <div class="auth-code-popup-note" style="margin-top:1px; margin-left: 0;">
                            <?php DUP_PRO_U::esc_html_e('There is only Apps folder permission scope by default. If your OneDrive Business is not working, Please switch on this option.'); ?>
                        </div>

                        <!-- CONNECT -->
                        <button id="dpro-onedrive-msgraph-connect-btn" type="button" class="button button-large" onclick="DupPro.Storage.OneDrive.GetAuthUrl(); return false;">
                            <i class="fa fa-plug"></i> <?php DUP_PRO_U::esc_html_e('Connect to OneDrive'); ?>
                        </button>

                        <div class='onedrive-msgraph-auth-container' style="display: none;">
                            <!-- STEP 2 -->
                            <b><?php DUP_PRO_U::esc_html_e("Step 1:"); ?></b>&nbsp;
                            <?php DUP_PRO_U::esc_html_e(' Duplicator needs to authorize at OneDrive.'); ?>
                            <div class="auth-code-popup-note" style="margin-top:1px">
                            <?php echo $txt_auth_note ?>
                            </div>
                            <button type="button" class="button button-large" onclick="DupPro.Storage.OneDrive.OpenAuthPage(); return false;">
                                <i class="fa fa-user"></i> <?php DUP_PRO_U::esc_html_e('Authorize Onedrive'); ?>
                            </button>
                            <br/><br/>

                            <div id="onedrive-msgraph-auth-container">
                                <b><?php DUP_PRO_U::esc_html_e('Step 2:'); ?></b> <?php DUP_PRO_U::esc_html_e("Paste code from OneDrive authorization page."); ?> <br/>
                                <input style="width:400px" id="onedrive-msgraph-auth-code" name="onedrive-msgraph-auth-code" />
                            </div>
                            <br><br>
                            <!-- STEP 3 -->
                            <b><?php DUP_PRO_U::esc_html_e("Step 3:"); ?></b>&nbsp;
                            <?php DUP_PRO_U::esc_html_e('Finalize OneDrive validation by clicking the "Finalize Setup" button.'); ?>
                            <br/>
                            <button type="button" 
                            id="onedrive-msgraph-finalize-setup"
                            class="button" onclick="DupPro.Storage.OneDrive.FinalizeSetup(); return false;"><i class="fa fa-check-square"></i> <?php DUP_PRO_U::esc_html_e('Finalize Setup'); ?></button>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="onedrive-msgraph-authorization-state" id="onedrive-msgraph-state-authorized">
                <?php if ($storage->onedrive_authorization_state == DUP_PRO_OneDrive_Authorization_States::Authorized) : ?>
                        <h3>
                            <img src="<?php echo DUPLICATOR_PRO_IMG_URL ?>/onedrive-24.png" style='vertical-align: bottom' />
                            <?php DUP_PRO_U::esc_html_e('OneDrive Account'); ?><br/>
                            <i class="dpro-edit-info"><?php DUP_PRO_U::esc_html_e('Duplicator has been authorized to access this user\'s OneDrive account'); ?></i>
                        </h3>
                        
                        <?php
                        if (isset($onedrive_account_info)) {
                            ?>
                            <div id="onedrive-account-info">
                                <label><?php DUP_PRO_U::esc_html_e('Name'); ?>:</label>
                                <?php echo esc_html($onedrive_account_info->displayName); ?> <br/>
                            </div>
                        </div>
                            <?php
                        } elseif (isset($onedrive_state_token->data->error)) {
                            ?>
                            <div class="error-txt">
                                <?php
                                printf(DUP_PRO_U::esc_html__('Error: %s'), $onedrive_msgraph_state_token->data->error_description);
                                echo '<br/><strong>';
                                DUP_PRO_U::esc_html_e('Please click on the "Cancel Authorization" button and reauthorize the OneDrive storage');
                                echo '</strong>';
                                ?>
                            </div>
                            <?php
                        }
                        ?>
                        <br/>
                        <button type="button" class="button" onclick='DupPro.Storage.OneDrive.CancelAuthorization();'>
                            <?php DUP_PRO_U::esc_html_e('Cancel Authorization'); ?>
                        </button><br/>
                        <i class="dpro-edit-info"><?php DUP_PRO_U::esc_html_e('Disassociates storage provider with the OneDrive account. Will require re-authorization.'); ?> </i>
                <?php endif; ?>
                </div>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="_onedrive_msgraph_storage_folder"><?php DUP_PRO_U::esc_html_e("Storage Folder"); ?></label></th>
            <td>
                <b>//OneDrive/Apps/Duplicator Pro/</b>
                <input id="_onedrive_msgraph_storage_folder" name="_onedrive_msgraph_storage_folder" type="text" value="<?php echo esc_attr($storage->onedrive_storage_folder); ?>"
                    class="dpro-storeage-folder-path" data-parsley-pattern="^((?!\:).)*[^\.\:]$"
                    data-parsley-errors-container="#onedrive_msgraph_storage_folder_error_container"
                    data-parsley-pattern-message="<?php echo DUP_PRO_U::esc_attr__('The folder path shouldn\'t include the special character colon(":") or shouldn\'t end with a dot(".").'); ?>" />
                <p><i><?php DUP_PRO_U::esc_html_e("Folder where packages will be stored. This should be unique for each web-site using Duplicator."); ?></i></p>
                <div id="onedrive_msgraph_storage_folder_error_container" class="duplicator-error-container"></div>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for=""><?php DUP_PRO_U::esc_html_e("Max Packages"); ?></label></th>
            <td>
                <label for="onedrive_msgraph_max_files">
                    <input data-parsley-errors-container="#onedrive_msgraph_max_files_error_container" id="onedrive_msgraph_max_files" name="onedrive_msgraph_max_files" type="text" value="<?php echo absint($storage->onedrive_max_files); ?>" maxlength="4">
                    <?php DUP_PRO_U::esc_html_e("Number of packages to keep in folder."); ?> <br/>
                    <i><?php DUP_PRO_U::esc_html_e("When this limit is exceeded, the oldest package will be deleted. Set to 0 for no limit."); ?></i>
                </label>
                <div id="onedrive_msgraph_max_files_error_container" class="duplicator-error-container"></div>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for=""><?php DUP_PRO_U::esc_html_e("Connection"); ?></label></th>
            <td>
                <button class="button button-large button_onedrive_test" id="button_onedrive_msgraph_send_file_test" type="button" onclick="DupPro.Storage.OneDrive.SendFileTest(); return false;">
                    <i class="fas fa-cloud-upload-alt fa-sm"></i>   <?php DUP_PRO_U::esc_html_e('Test OneDrive Connection'); ?>
                </button>
                <p><i><?php DUP_PRO_U::esc_html_e("Test connection by sending and receiving a small file to/from the account."); ?></i></p>
            </td>
        </tr>
    </table>

    <!-- ===============================
    FTP PROVIDER -->
    <table id="provider-<?php echo DUP_PRO_Storage_Types::FTP ?>" class="form-table provider dup-remove-on-submit-if-hidden" >
        <tr>
            <td class="dpro-sub-title" colspan="2"><b><?php DUP_PRO_U::esc_html_e("Credentials"); ?></b></td>
        </tr>
        <tr>
            <th scope="row"><label for="ftp_server"><?php DUP_PRO_U::esc_html_e("Server"); ?></label></th>
            <td>
                <input id="ftp_server" class="dup-empty-field-on-submit" name="ftp_server" data-parsley-errors-container="#ftp_server_error_container" type="text" autocomplete="off" value="<?php echo esc_attr($storage->ftp_server); ?>">
                <label for="ftp_server"><?php DUP_PRO_U::esc_html_e("Port"); ?></label> <input name="ftp_port" id="ftp_port" data-parsley-errors-container="#ftp_server_error_container" type="text" style="width:75px"  value="<?php echo esc_attr($storage->ftp_port); ?>">
                <div id="ftp_server_error_container" class="duplicator-error-container"></div>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="ftp_username"><?php DUP_PRO_U::esc_html_e("Username"); ?></label></th>
            <td><input id="ftp_username" class="dup-empty-field-on-submit" name="ftp_username" type="text" autocomplete="off" value="<?php echo esc_attr($storage->ftp_username); ?>" /></td>
        </tr>
        <tr>
            <th scope="row"><label for="ftp_password"><?php DUP_PRO_U::esc_html_e("Password"); ?></label></th>
            <td>
                <input 
                    id="ftp_password"
                    name="ftp_password" 
                    type="password" 
                    class="dup-empty-field-on-submit"
                    placeholder="<?php echo str_repeat("*", SnapString::stringLength($storage->ftp_password)); ?>"
                    autocomplete="off" 
                    value="" 
                >
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="ftp_password2"><?php DUP_PRO_U::esc_html_e("Retype Password"); ?></label></th>
            <td>
                <input 
                    id="ftp_password2" 
                    class="dup-empty-field-on-submit" 
                    name="ftp_password2" 
                    type="password" 
                    placeholder="<?php echo str_repeat("*", SnapString::stringLength($storage->ftp_password)); ?>"
                    autocomplete="off" 
                    value="" 
                    data-parsley-errors-container="#ftp_password2_error_container"  
                    data-parsley-trigger="change" data-parsley-equalto="#ftp_password" 
                    data-parsley-equalto-message="<?php DUP_PRO_U::esc_html_e("Passwords do not match"); ?>" 
                ><br/>
                <div id="ftp_password2_error_container" class="duplicator-error-container"></div>
            </td>
        </tr>
        <tr>
            <td class="dpro-sub-title" colspan="2"><b><?php DUP_PRO_U::esc_html_e("Settings"); ?></b></td>
        </tr>
        <tr>
            <th scope="row"><label for="_ftp_storage_folder"><?php DUP_PRO_U::esc_html_e("Storage Folder"); ?></label></th>
            <td>
                <input id="_ftp_storage_folder" name="_ftp_storage_folder" type="text" value="<?php echo esc_attr($storage->ftp_storage_folder); ?>">
                <p><i><?php DUP_PRO_U::esc_html_e("Folder where packages will be stored. This should be unique for each web-site using Duplicator."); ?></i></p>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="ftp_max_files"><?php DUP_PRO_U::esc_html_e("Max Packages"); ?></label></th>
            <td>
                <label for="ftp_max_files">
                    <input id="ftp_max_files" name="ftp_max_files" data-parsley-errors-container="#ftp_max_files_error_container" type="text" value="<?php echo absint($storage->ftp_max_files); ?>">
                    <?php DUP_PRO_U::esc_html_e("Number of packages to keep in folder."); ?> <br/>
                    <i><?php DUP_PRO_U::esc_html_e("When this limit is exceeded, the oldest package will be deleted. Set to 0 for no limit. "); ?></i>
                </label>
                <div id="ftp_max_files_error_container" class="duplicator-error-container"></div>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="ftp_timeout_in_secs"><?php DUP_PRO_U::esc_html_e("Timeout"); ?></label></th>
            <td>

                <label for="ftp_timeout_in_secs">
                        <input id="ftp_timeout" name="ftp_timeout_in_secs" data-parsley-errors-container="#ftp_timeout_error_container" type="text" value="<?php echo absint($storage->ftp_timeout_in_secs); ?>"> <label for="ftp_timeout_in_secs"><?php DUP_PRO_U::esc_html_e("seconds"); ?></label>
                        <br>
                        <i><?php DUP_PRO_U::esc_html_e("Do not modify this setting unless you know the expected result or have talked to support."); ?></i>
                </label>
                <div id="ftp_timeout_error_container" class="duplicator-error-container"></div>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="ftp_ssl"><?php DUP_PRO_U::esc_html_e("Explicit SSL"); ?></label></th>
            <td>
                <input name="_ftp_ssl" <?php DUP_PRO_UI::echoChecked($storage->ftp_ssl); ?> class="checkbox" value="1" type="checkbox" id="_ftp_ssl" >
                <label for="_ftp_ssl"><?php DUP_PRO_U::esc_html_e("Enable"); ?></label>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="_ftp_passive_mode"><?php DUP_PRO_U::esc_html_e("Passive Mode"); ?></label></th>
            <td>
                <input <?php DUP_PRO_UI::echoChecked($storage->ftp_passive_mode); ?> class="checkbox" value="1" type="checkbox" name="_ftp_passive_mode" id="_ftp_passive_mode">
                <label for="_ftp_passive_mode"><?php DUP_PRO_U::esc_html_e("Enable"); ?></label>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="_ftp_use_curl"><?php DUP_PRO_U::esc_html_e("cURL"); ?></label></th>
            <td>
                <input <?php DUP_PRO_UI::echoChecked($storage->ftp_use_curl); ?> class="checkbox" value="1" type="checkbox" name="_ftp_use_curl" id="_ftp_use_curl">
                <label for="_ftp_use_curl"><?php DUP_PRO_U::esc_html_e("Enable"); ?></label>
                <p><i><?php DUP_PRO_U::esc_html_e("PHP cURL. Only check if connection test recommends it."); ?></i></p>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for=""><?php DUP_PRO_U::esc_html_e("Connection"); ?></label></th>
            <td>
                <button class="button button-large button_ftp_test" id="button_ftp_send_file_test" type="button" onclick="DupPro.Storage.FTP.SendFileTest(); return false;">
                    <i class="fas fa-cloud-upload-alt fa-sm"></i> <?php DUP_PRO_U::esc_html_e('Test FTP Connection'); ?>
                </button>
                <p>
                    <i><?php DUP_PRO_U::esc_html_e("Test connection by sending and receiving a small file to/from the account."); ?>
                     <br/><br/><br/>
                    <?php echo wp_kses(DUP_PRO_U::__("<b>Note:</b> This setting is for FTP and FTPS (FTP/SSL) only. To use SFTP (SSH File Transfer Protocol) change the type dropdown above."), array(
                            'b' => array()
                        ));
?>
                    <br/> 
                </p>
            </td>
        </tr>
    </table>    

<?php if (extension_loaded('gmp')) : ?>
        <!-- ===============================
        SFTP PROVIDER -->
        <table id="provider-<?php echo DUP_PRO_Storage_Types::SFTP ?>" class="form-table provider dup-remove-on-submit-if-hidden" >
            <tr>
                <td class="dpro-sub-title" colspan="2"><b><?php DUP_PRO_U::esc_html_e("Credentials"); ?></b></td>
            </tr>
            <tr>
                <th scope="row"><label for="sftp_server"><?php DUP_PRO_U::esc_html_e("Server"); ?></label></th>
                <td>
                    <input id="sftp_server" class="dup-empty-field-on-submit" name="sftp_server" data-parsley-errors-container="#sftp_server_error_container" type="text" autocomplete="off" value="<?php echo esc_attr($storage->sftp_server); ?>">
                    <label for="sftp_server"><?php DUP_PRO_U::esc_html_e("Port"); ?></label> <input name="sftp_port" id="sftp_port" data-parsley-errors-container="#sftp_server_error_container" type="text" style="width:75px"  value="<?php echo esc_attr($storage->sftp_port); ?>">
                    <div id="sftp_server_error_container" class="duplicator-error-container"></div>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="sftp_username"><?php DUP_PRO_U::esc_html_e("Username"); ?></label></th>
                <td><input id="sftp_username" class="dup-empty-field-on-submit" name="sftp_username" type="text" autocomplete="off" value="<?php echo esc_attr($storage->sftp_username); ?>" /></td>
            </tr>
            <tr>
                <th scope="row"><label for="sftp_password"><?php DUP_PRO_U::esc_html_e("Password"); ?></label></th>
                <td>
                    <input 
                        id="sftp_password" 
                        class="dup-empty-field-on-submit"
                        name="sftp_password" 
                        type="password"
                        placeholder="<?php echo str_repeat("*", SnapString::stringLength($storage->sftp_password)); ?>"
                        autocomplete="off" 
                        value="" 
                    >
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="sftp_password2"><?php DUP_PRO_U::esc_html_e("Retype Password"); ?></label></th>
                <td>
                    <input 
                        id="sftp_password2" 
                        class="dup-empty-field-on-submit" 
                        name="sftp_password2" 
                        type="password"
                        placeholder="<?php echo str_repeat("*", SnapString::stringLength($storage->sftp_password)); ?>"
                        autocomplete="off" 
                        value="" 
                        data-parsley-errors-container="#sftp_password2_error_container"  
                        data-parsley-trigger="change" 
                        data-parsley-equalto="#sftp_password" 
                        data-parsley-equalto-message="<?php DUP_PRO_U::esc_attr_e("Passwords do not match"); ?>" 
                    ><br/>
                    <div id="sftp_password2_error_container" class="duplicator-error-container"></div>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="sftp_private_key"><?php DUP_PRO_U::esc_html_e("Private Key (PuTTY)"); ?></label></th>
                <td>
                    <input id="sftp_private_key_file" class="dup-empty-field-on-submit" name="sftp_private_key_file" onchange="DupPro.Storage.SFTP.ReadPrivateKey(this);" type="file"  accept="ppk" value="" data-parsley-errors-container="#sftp_private_key_error_container" /><br/>
                    <input type="hidden" name="sftp_private_key" id="sftp_private_key" value="<?php echo esc_attr($storage->sftp_private_key); ?>" />
                    <div id="sftp_private_key_error_container" class="duplicator-error-container"></div>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="sftp_private_key_password"><?php DUP_PRO_U::esc_html_e("Private Key Password"); ?></label></th>
                <td>
                    <input 
                        id="sftp_private_key_password" 
                        class="dup-empty-field-on-submit" 
                        name="sftp_private_key_password" 
                        type="password" 
                        placeholder="<?php echo str_repeat("*", SnapString::stringLength($storage->sftp_private_key_password)); ?>"
                        autocomplete="off" 
                        value="" 
                        data-parsley-errors-container="#sftp_private_key_password_error_container" 
                    >
                    <br/>
                    <div id="sftp_private_key_password_error_container" class="duplicator-error-container"></div>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="sftp_private_key_password2"><?php DUP_PRO_U::esc_html_e("Private Key Retype Password"); ?></label></th>
                <td>
                    <input 
                        id="sftp_private_key_password2" 
                        class="dup-empty-field-on-submit" 
                        name="sftp_private_key_password2" 
                        type="password"
                        placeholder="<?php echo str_repeat("*", SnapString::stringLength($storage->sftp_private_key_password)); ?>"
                        autocomplete="off" 
                        value="" 
                        data-parsley-errors-container="#sftp_private_key_password2_error_container" 
                        data-parsley-trigger="change" 
                        data-parsley-equalto="#sftp_private_key_password" 
                        data-parsley-equalto-message="<?php DUP_PRO_U::esc_html_e("Passwords do not match"); ?>" 
                    ><br/>
                    <div id="sftp_private_key_password2_error_container" class="duplicator-error-container"></div>
                </td>
            </tr>
            <tr>
                <td class="dpro-sub-title" colspan="2"><b><?php DUP_PRO_U::esc_html_e("Settings"); ?></b></td>
            </tr>
            <tr>
                <th scope="row"><label for="_sftp_storage_folder"><?php DUP_PRO_U::esc_html_e("Storage Folder"); ?></label></th>
                <td>
                    <input id="_sftp_storage_folder" name="_sftp_storage_folder" type="text" value="<?php echo esc_attr($storage->sftp_storage_folder); ?>">
                    <p><i><?php echo wp_kses(DUP_PRO_U::__("Folder where packages will be stored. This should be <strong>an absolute path, not a relative path</strong> and be unique for each web-site using Duplicator."), array(
                        'strong' => array()
                    )); ?></i></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="sftp_max_files"><?php DUP_PRO_U::esc_html_e("Max Packages"); ?></label></th>
                <td>
                    <label for="sftp_max_files">
                        <input id="sftp_max_files" name="sftp_max_files" data-parsley-errors-container="#sftp_max_files_error_container" type="text" value="<?php echo absint($storage->sftp_max_files); ?>">
                        <?php DUP_PRO_U::esc_html_e("Number of packages to keep in folder."); ?> <br/>
                        <i><?php DUP_PRO_U::esc_html_e("When this limit is exceeded, the oldest package will be deleted. Set to 0 for no limit."); ?></i>
                    </label>
                    <div id="sftp_max_files_error_container" class="duplicator-error-container"></div>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="sftp_timeout_in_secs"><?php DUP_PRO_U::esc_html_e("Timeout"); ?></label></th>
                <td>
                    <label for="sftp_timeout_in_secs">
                        <input id="sftp_timeout" name="sftp_timeout_in_secs" data-parsley-errors-container="#sftp_timeout_error_container" type="text" value="<?php echo absint($storage->sftp_timeout_in_secs); ?>"> <label for="sftp_timeout_in_secs"><?php DUP_PRO_U::esc_html_e("seconds"); ?></label>
                        <br>
                        <i><?php DUP_PRO_U::esc_html_e("Do not modify this setting unless you know the expected result or have talked to support."); ?></i>
                    </label>
                    <div id="sftp_timeout_error_container" class="duplicator-error-container"></div>
                </td>
            </tr>
            <tr>
            <th scope="row"><label for="sftp_disable_chunking_mode"><?php DUP_PRO_U::esc_html_e("Chunking"); ?></label></th>
                <td>
                    <input id="sftp_disable_chunking_mode" name="sftp_disable_chunking_mode" type="checkbox" class="checkbox" value="1" <?php checked($storage->sftp_disable_chunking_mode, true); ?>>
                    <label for="sftp_disable_chunking_mode"><?php DUP_PRO_U::esc_html_e("Disable"); ?></label>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for=""><?php DUP_PRO_U::esc_html_e("Connection"); ?></label></th>
                <td>
                    <button class="button button-large button_sftp_test" id="button_sftp_send_file_test" type="button" onclick="DupPro.Storage.SFTP.SendFileTest(); return false;">
                        <i class="fas fa-cloud-upload-alt fa-sm"></i> <?php DUP_PRO_U::esc_html_e('Test SFTP Connection'); ?>
                    </button>
                    <p>
                        <i><?php DUP_PRO_U::esc_html_e("Test connection by sending and receiving a small file to/from the account."); ?></i>
                    </p>
                </td>
            </tr>
        </table>
<?php endif; ?>

    <!-- ===============================
    GOOGLE DRIVE PROVIDER -->
    <table id="provider-<?php echo DUP_PRO_Storage_Types::GDrive ?>" class="form-table provider dup-remove-on-submit-if-hidden" >
        <tr>
            <th scope="row"><label for=""><?php DUP_PRO_U::esc_html_e("Authorization"); ?></label></th>
            <td class="gdrive-authorize">
            <?php if ($storage->gdrive_authorization_state == DUP_PRO_GDrive_Authorization_States::Unauthorized) : ?>
                    <div class='gdrive-authorization-state' id="gdrive-state-unauthorized">
                        <!-- CONNECT -->
                        <div id="dpro-gdrive-connect-btn-area">
                            <button id="dpro-gdrive-connect-btn" type="button" class="button button-large" onclick="DupPro.Storage.GDrive.GoogleGetAuthUrl();">
                                <i class="fa fa-plug"></i> <?php DUP_PRO_U::esc_html_e('Connect to Google Drive'); ?>
                                <img src="<?php echo esc_url(DUPLICATOR_PRO_IMG_URL . '/gdrive-24.png'); ?>" style='vertical-align: middle; margin:-2px 0 0 3px; height:18px; width:18px' />
                            </button>
                        </div>
                        <div class="authorization-state" id="dpro-gdrive-connect-progress">
                            <div style="padding:10px">
                                <i class="fas fa-circle-notch fa-spin"></i> <?php DUP_PRO_U::esc_html_e('Getting Google Drive Request Token...'); ?>
                            </div>
                        </div>

                        <!-- STEPS -->
                        <div id="dpro-gdrive-steps">
                            <div>
                                <b><?php DUP_PRO_U::esc_html_e('Step 1:'); ?></b>&nbsp;
                                <?php DUP_PRO_U::esc_html_e("Duplicator needs to authorize Google Drive."); ?>
                                <div class="auth-code-popup-note">
                                    <?php echo $txt_auth_note ?>
                                </div>
                                <button id="gdrive-auth-window-button" class="button" onclick="DupPro.Storage.GDrive.OpenAuthPage(); return false;">
                                    <i class="fa fa-user"></i> <?php DUP_PRO_U::esc_html_e("Authorize Google Drive"); ?>
                                </button>
                            </div>

                            <div id="gdrive-auth-code-area">
                                <b><?php DUP_PRO_U::esc_html_e('Step 2:'); ?></b> <?php DUP_PRO_U::esc_html_e("Paste code from Google authorization page."); ?> <br/>
                                <input style="width:400px" id="gdrive-auth-code" name="gdrive-auth-code" />
                            </div>

                            <b><?php DUP_PRO_U::esc_html_e('Step 3:'); ?></b> <?php DUP_PRO_U::esc_html_e('Finalize Google Drive setup by clicking the "Finalize Setup" button.') ?><br/>
                            <button id="gdrive-finalize-setup" type="button" class="button" onclick="DupPro.Storage.GDrive.FinalizeSetup(); return false;"><i class="fa fa-check-square"></i> <?php DUP_PRO_U::esc_html_e('Finalize Setup'); ?></button>
                        </div>
                    </div>
            <?php else : ?>
                    <div class='gdrive-authorization-state' id="gdrive-state-authorized" style="margin-top:-10px">

                    <?php if ($gdrive_user_info != null) : ?>
                            <h3>
                                <img src="<?php echo DUPLICATOR_PRO_IMG_URL ?>/gdrive-24.png" class="dup-store-auth-icon" alt=""  />
                                <?php DUP_PRO_U::esc_html_e('Google Drive Account'); ?><br/>
                                <i class="dpro-edit-info"><?php DUP_PRO_U::esc_html_e('Duplicator has been authorized to access this user\'s Google Drive account'); ?></i>
                            </h3>
                            <div id="gdrive-account-info">
                                <label><?php DUP_PRO_U::esc_html_e('Name'); ?>:</label>
                                <?php echo esc_html($gdrive_user_info->givenName . ' ' . $gdrive_user_info->familyName); ?><br/>

                                <label><?php DUP_PRO_U::esc_html_e('Email'); ?>:</label>
                                <?php
                                    echo esc_html($gdrive_user_info->email);
                                    $google_service_drive = new Duplicator_Pro_Google_Service_Drive($google_client);
                                    $optParams = array('fields' => '*');
                                    $about = $google_service_drive->about->get($optParams);
                                    $quota_total = max($about->storageQuota['limit'], 1);
                                    $quota_used = $about->storageQuota['usage'];

                                if (is_numeric($quota_total) && is_numeric($quota_used)) {
                                    $available_quota = $quota_total - $quota_used;
                                    $used_perc = round($quota_used * 100 / $quota_total, 1);
                                    echo '<br>';
                                    echo '<label>' . DUP_PRO_U::__('Quota Usage:') . '</label> ';
                                    printf(DUP_PRO_U::__('%s %% used, %s available'), $used_perc, round($available_quota / 1048576, 1) . ' MB');
                                }
                                ?>
                            </div><br/>
                    <?php else : ?>
                            <div><?php DUP_PRO_U::esc_html_e('Error retrieving user information.'); ?></div>
                    <?php endif ?>

                        <button type="button" class="button" onclick='DupPro.Storage.GDrive.CancelAuthorization();'>
                        <?php DUP_PRO_U::esc_html_e('Cancel Authorization'); ?>
                        </button><br/>
                        <i class="dpro-edit-info"><?php DUP_PRO_U::esc_html_e('Disassociates storage provider with the Google Drive account. Will require re-authorization.'); ?> </i>
                    </div>
            <?php endif ?>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="_gdrive_storage_folder"><?php DUP_PRO_U::esc_html_e("Storage Folder"); ?></label></th>
            <td>
                <b>//Google Drive/</b>
                <input id="_gdrive_storage_folder" name="_gdrive_storage_folder" type="text" value="<?php echo esc_attr($storage->gdrive_storage_folder); ?>"  class="dpro-storeage-folder-path"/>
                <p>
                    <i><?php DUP_PRO_U::esc_html_e("Folder where packages will be stored. This should be unique for each web-site using Duplicator."); ?></i>
                    <i class="fas fa-question-circle fa-sm" data-tooltip-title="<?php DUP_PRO_U::esc_attr_e("Storage Folder Notice"); ?>" data-tooltip="<?php DUP_PRO_U::esc_attr_e('If the directory path above is already in Google Drive before connecting then a duplicate folder name will be made in the same path. This is because the plugin only has rights to folders it creates.'); ?>"></i>

                </p>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for=""><?php DUP_PRO_U::esc_html_e("Max Packages"); ?></label></th>
            <td>
                <label for="gdrive_max_files">
                    <input data-parsley-errors-container="#gdrive_max_files_error_container" id="gdrive_max_files" name="gdrive_max_files" type="text" value="<?php echo absint($storage->gdrive_max_files); ?>" maxlength="4">&nbsp;
                    <?php DUP_PRO_U::esc_html_e("Number of packages to keep in folder."); ?> <br/>
                    <i><?php DUP_PRO_U::esc_html_e("When this limit is exceeded, the oldest package will be deleted. Set to 0 for no limit."); ?></i>
                </label>
                <div id="gdrive_max_files_error_container" class="duplicator-error-container"></div>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for=""><?php DUP_PRO_U::esc_html_e("Connection"); ?></label></th>
            <td>
            <?php
            $gdrive_test_button_disabled = '';
            if ($storage->id == -1 || (($storage->storage_type == DUP_PRO_Storage_Types::GDrive) && ($storage->gdrive_access_token_set_json == ''))) {
                $gdrive_test_button_disabled = 'disabled';
            }
            ?>
                <button class="button button-large button_gdrive_test" id="button_gdrive_send_file_test" type="button" onclick="DupPro.Storage.GDrive.SendFileTest(); return false;" <?php echo $gdrive_test_button_disabled; ?>>
                    <i class="fas fa-cloud-upload-alt fa-sm"></i>   <?php DUP_PRO_U::esc_html_e('Test Google Drive Connection'); ?>
                </button>
                <p><i><?php DUP_PRO_U::esc_html_e("Test connection by sending and receiving a small file to/from the account."); ?></i></p>
            </td>
        </tr>
    </table>

    <!-- ===============================
    LOCAL PROVIDER -->
    <table id="provider-<?php echo DUP_PRO_Storage_Types::Local ?>" class="provider form-table dup-remove-on-submit-if-hidden">
        <tr valign="top">
            <th scope="row">
                <?php
                $home_path = duplicator_pro_get_home_path();
                ?>
                <label onclick="jQuery('#_local_storage_folder').val('<?php echo esc_js($home_path); ?>')">
                <?php DUP_PRO_U::esc_html_e("Storage Folder"); ?>
                </label>
            </th>
            <td>
                <input data-parsley-errors-container="#_local_storage_folder_error_container" data-parsley-required="true"  type="text" id="_local_storage_folder" class="dup-empty-field-on-submit" name="_local_storage_folder" data-parsley-pattern=".*" data-parsley-not-core-paths="true" value="<?php echo esc_attr($storage->local_storage_folder); ?>" />
                <script>
                    window.Parsley
                    .addValidator('notCorePaths', {
                        requirementType: 'string',
                        validateString: function(value) {
                            <?php
                            $home_path = duplicator_pro_get_home_path();
                            $wp_upload_dir = wp_upload_dir();
                            $wp_upload_dir_basedir = str_replace('\\', '/', $wp_upload_dir['basedir']);
                            ?>
                            var corePaths = [
                                        "<?php echo $home_path;?>",
                                        "<?php echo untrailingslashit($home_path);?>",

                                        "<?php echo $home_path . 'wp-content';?>",
                                        "<?php echo $home_path . 'wp-content/';?>",

                                        "<?php echo $home_path . 'wp-admin';?>",
                                        "<?php echo $home_path . 'wp-admin/';?>",

                                        "<?php echo $home_path . 'wp-includes';?>",
                                        "<?php echo $home_path . 'wp-includes/';?>",

                                        "<?php echo $wp_upload_dir_basedir;?>",
                                        "<?php echo trailingslashit($wp_upload_dir_basedir);?>"
                                    ];
                            // console.log(value);

                            for (var i = 0; i < corePaths.length; i++) {
                                if (value === corePaths[i]) {
                                    return false;
                                }
                            }                            
                            return true;                            
                        },
                        messages: {
                            en: "<?php echo DUP_PRO_U::__('Storage Folder should not be root directory path, content directory path and upload directory path'); ?>"
                        }
                    });
                </script>
                <p>
                    <i>
                    <?php
                    DUP_PRO_U::esc_html_e("Where to store on the server hosting this site.");
                    echo ' <b>' . DUP_PRO_U::esc_html__('This will not store to your local computer unless that is where this web-site is hosted.') . '</b>';
                    echo '<br/> ';
                    DUP_PRO_U::esc_html_e("On Linux servers start with '/' (e.g. /mypath). On Windows use drive letters (e.g. E:/mypath).");
                    ?>
                    </i>
                </p>
                <div id="_local_storage_folder_error_container" class="duplicator-error-container"></div>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="local_filter_protection"><?php DUP_PRO_U::esc_html_e("Filter Protection"); ?></label></th>
            <td>
                <input id="_local_filter_protection" name="_local_filter_protection" type="checkbox" <?php DUP_PRO_UI::echoChecked($storage->local_filter_protection); ?> onchange="DupPro.Storage.LocalFilterToggle()">&nbsp;
                <label for="_local_filter_protection">
                <?php DUP_PRO_U::esc_html_e("Filter the Storage Folder (recommended)"); ?>
                </label>
                <div style="padding-top:6px">
                    <i><?php DUP_PRO_U::esc_html_e("When checked this will exclude the 'Storage Folder' and all of its content and sub-folders from package builds."); ?></i>
                    <div id="_local_filter_protection_message" style="display:none; color:maroon">
                        <i><?php DUP_PRO_U::esc_html_e("Unchecking filter protection is not recommended.  This setting helps to prevents packages from getting bundled in other packages."); ?></i>
                    </div>
                </div>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for=""><?php DUP_PRO_U::esc_html_e("Max Packages"); ?></label></th>
            <td>
                <label for="local_max_files">
                    <input data-parsley-errors-container="#local_max_files_error_container" id="local_max_files" name="local_max_files" type="text" value="<?php echo absint($storage->local_max_files); ?>" maxlength="4">&nbsp;
                    <?php DUP_PRO_U::esc_html_e("Number of packages to keep in folder."); ?><br/>
                    <i><?php DUP_PRO_U::esc_html_e("When this limit is exceeded, the oldest package will be deleted. Set to 0 for no limit."); ?></i>
                </label>
                <div id="local_max_files_error_container" class="duplicator-error-container"></div>
            </td>
        </tr>
    </table>    


    <br style="clear:both" />
    <button 
        id="button_save_provider" 
        class="button button-primary" 
        type="submit"
        <?php disabled(($storage->id > 0)); ?>
    >
        <?php DUP_PRO_U::esc_html_e('Save Provider'); ?>
    </button>
</form>
<?php
// Alerts for Dropbox
$alertDropboxConnStatus = new DUP_PRO_UI_Dialog();
$alertDropboxConnStatus->title = __('Dropbox Connection Status', 'duplicator-pro');
$alertDropboxConnStatus->message = ''; // javascript inserted message
$alertDropboxConnStatus->initAlert();

$alertDropboxConnStatusSuccess = new DUP_PRO_UI_Dialog();
$alertDropboxConnStatusSuccess->title = __('SUCCESS!', 'duplicator-pro');
$alertDropboxConnStatusSuccess->message = ''; // javascript inserted message
$alertDropboxConnStatusSuccess->wrapperClassButtons = 'dpro-dlg-dropbox-test-success';
$alertDropboxConnStatusSuccess->initAlert();

// Alerts for OneDrive
$alertOneDriveConnStatus = new DUP_PRO_UI_Dialog();
$alertOneDriveConnStatus->title = __('OneDrive Connection Status', 'duplicator-pro');
$alertOneDriveConnStatus->message = ''; // javascript inserted message
$alertOneDriveConnStatus->initAlert();

$alertOneDriveConnStatusSuccess = new DUP_PRO_UI_Dialog();
$alertOneDriveConnStatusSuccess->title = __('SUCCESS!', 'duplicator-pro');
$alertOneDriveConnStatusSuccess->message = ''; // javascript inserted message
$alertOneDriveConnStatusSuccess->wrapperClassButtons = 'dpro-dlg-onedrive-test-success';
$alertOneDriveConnStatusSuccess->initAlert();

// Alerts for Google Drive
$alertGoogleDriveConnStatus = new DUP_PRO_UI_Dialog();
$alertGoogleDriveConnStatus->title = __('Google Drive Authorization Error', 'duplicator-pro');
$alertGoogleDriveConnStatus->message = ''; // javascript inserted message
$alertGoogleDriveConnStatus->initAlert();

$alertGoogleDriveConnStatusSuccess = new DUP_PRO_UI_Dialog();
$alertGoogleDriveConnStatusSuccess->title = __('SUCCESS!', 'duplicator-pro');
$alertGoogleDriveConnStatusSuccess->message = ''; // javascript inserted message
$alertGoogleDriveConnStatusSuccess->wrapperClassButtons = 'dpro-dlg-gdrive-test-success';
$alertGoogleDriveConnStatusSuccess->initAlert();

// Alerts for S3
$alertS3ConnStatus = new DUP_PRO_UI_Dialog();
$alertS3ConnStatus->title = __('S3 Connection Status', 'duplicator-pro');
$alertS3ConnStatus->height = 185;
$alertS3ConnStatus->message = ''; // javascript inserted message
$alertS3ConnStatus->initAlert();

$alertS3ConnStatusLong = new DUP_PRO_UI_Dialog();
$alertS3ConnStatusLong->title = __('S3 Connection Status', 'duplicator-pro');
$alertS3ConnStatusLong->width = 600;
$alertS3ConnStatusLong->height = 450;
$alertS3ConnStatusLong->showTextArea = true;
$alertS3ConnStatusLong->textAreaRows = 13;
$alertS3ConnStatusLong->textAreaCols = 100;
$alertS3ConnStatusLong->message = ''; // javascript inserted message
$alertS3ConnStatusLong->initAlert();

// Alerts for FTP
$alertFTPConnStatus = new DUP_PRO_UI_Dialog();
$alertFTPConnStatus->title = __('FTP Connection Status', 'duplicator-pro');
$alertFTPConnStatus->height = 185;
$alertFTPConnStatus->message = ''; // javascript inserted message
$alertFTPConnStatus->initAlert();

$alertFTPConnStatusLong = new DUP_PRO_UI_Dialog();
$alertFTPConnStatusLong->title = __('FTP Connection Status', 'duplicator-pro');
$alertFTPConnStatusLong->width = 600;
$alertFTPConnStatusLong->height = 520;
$alertFTPConnStatusLong->showTextArea = true;
$alertFTPConnStatusLong->textAreaRows = 15;
$alertFTPConnStatusLong->textAreaCols = 100;
$alertFTPConnStatusLong->message = ''; // javascript inserted message
$alertFTPConnStatusLong->initAlert();

// Alerts for SFTP
$alertSFTPConnStatus = new DUP_PRO_UI_Dialog();
$alertSFTPConnStatus->title = __('SFTP Connection Status', 'duplicator-pro');
$alertSFTPConnStatus->height = 185;
$alertSFTPConnStatus->message = ''; // javascript inserted message
$alertSFTPConnStatus->initAlert();

$alertSFTPConnStatusLong = new DUP_PRO_UI_Dialog();
$alertSFTPConnStatusLong->title = __('SFTP Connection Status', 'duplicator-pro');
$alertSFTPConnStatusLong->width = 600;
$alertSFTPConnStatusLong->height = 450;
$alertSFTPConnStatusLong->showTextArea = true;
$alertSFTPConnStatusLong->textAreaRows = 13;
$alertSFTPConnStatusLong->textAreaCols = 100;
$alertSFTPConnStatusLong->message = ''; // javascript inserted message
$alertSFTPConnStatusLong->initAlert();
?>
<script>
    jQuery(document).ready(function ($) {

        // Quick fix for submint/enter error
        $(window).on('keyup keydown', function (e) {
            if (!$(e.target).is('textarea'))
            {
                var keycode = (typeof e.keyCode != 'undefined' && e.keyCode > -1 ? e.keyCode : e.which);
                if ((keycode === 13)) {
                    e.preventDefault();
                    return false;
                }
            }
        });

        var counter = 0;

        DupPro.Storage.Modes = {
            LOCAL: 0,
            DROPBOX: 1,
            FTP: 2,
            GDRIVE: 3,
            S3: 4
        };

        DupPro.Storage.BindParsley = function (mode, s3_provider)
        {
            if (counter++ > 0) {
                $('#dup-storage-form').parsley().destroy();
            }

            $('#dup-storage-form input').removeAttr('data-parsley-required');
            $('#dup-storage-form input').removeAttr('data-parsley-type');
            $('#dup-storage-form input').removeAttr('data-parsley-range');
            $('#dup-storage-form input').removeAttr('data-parsley-min');
            $('#name').attr('data-parsley-required', 'true');

            switch (parseInt(mode)) {

                case DupPro.Storage.Modes.LOCAL:
                    $('#_local_storage_folder').attr('data-parsley-required', 'true');

                    $('#local_max_files').attr('data-parsley-required', 'true');
                    $('#local_max_files').attr('data-parsley-type', 'number');
                    $('#local_max_files').attr('data-parsley-min', '0');
                    break;

                case DupPro.Storage.Modes.DROPBOX:
                    $('#dropbox_max_files').attr('data-parsley-required', 'true');
                    $('#dropbox_max_files').attr('data-parsley-type', 'number');
                    $('#dropbox_max_files').attr('data-parsley-min', '0');
                    break;

                case DupPro.Storage.Modes.FTP:
                    $('#ftp_server').attr('data-parsley-required', 'true');
                    $('#ftp_port').attr('data-parsley-required', 'true');
                    // $('#ftp_password, #ftp_password2').attr('data-parsley-required', 'true');
                    $('#ftp_max_files').attr('data-parsley-required', 'true');
                    $('#ftp_timeout').attr('data-parsley-required', 'true');
                    $('#ftp_port').attr('data-parsley-type', 'number');
                    $('#ftp_max_files').attr('data-parsley-type', 'number');
                    $('#ftp_timeout').attr('data-parsley-type', 'number');
                    $('#ftp_port').attr('data-parsley-range', '[1,65535]');
                    $('#ftp_max_files').attr('data-parsley-min', '0');
                    $('#ftp_timeout').attr('data-parsley-min', '10');
                    break;

                case DupPro.Storage.Modes.GDRIVE:
                    $('#gdrive_max_files').attr('data-parsley-required', 'true');
                    $('#gdrive_max_files').attr('data-parsley-type', 'number');
                    $('#gdrive_max_files').attr('data-parsley-min', '0');
                    break;

                case DupPro.Storage.Modes.S3:
                    // Common for all s3 providers:
                    $('#s3_access_key_'+s3_provider).attr('data-parsley-required', 'true');
                    // $('#s3_secret_key_'+s3_provider).attr('data-parsley-required', 'true');
                    $('#s3_max_files_'+s3_provider).attr('data-parsley-required', 'true');
                    $('#s3_bucket_'+s3_provider).attr('data-parsley-required', 'true');
                    // Specifically address each s3 provider here:
                    switch(s3_provider) {                        
                        case "amazon":
                            break;
                        case "backblaze":
                            $('#s3_region_backblaze').attr('data-parsley-required', 'true');
                            $('#s3_region_backblaze').attr('data-parsley-pattern', '\[0-9-a-z-_]+');
                            $('#s3_endpoint_backblaze').attr('data-parsley-required', 'true');
                            break;
                        default:
                            $('#s3_region_other').attr('data-parsley-required', 'true');
                            $('#s3_region_other').attr('data-parsley-pattern', '\[0-9-a-z-_]+');
                            $('#s3_endpoint_other').attr('data-parsley-required', 'true');                            
                    }
            }
            $('#dup-storage-form').parsley();
        };

        // Removes the values of hidden input fields marked with class dup-empty-field-on-submit
        DupPro.Storage.EmptyValues = function () {
            $(':hidden .dup-empty-field-on-submit').val('');
        }

        // Removes tags marked with class dup-remove-on-submit-if-hidden, if they are hidden
        DupPro.Storage.RemoveMarkedHiddenTags = function () {
            $('.dup-remove-on-submit-if-hidden:hidden').each(function() {
                $(this).remove();
            });
        }

        DupPro.Storage.PrepareForSubmit = function () {
            DupPro.Storage.EmptyValues();
            if ($('#dup-storage-form').parsley().isValid()) {
                // The form is about to be submitted.                
                DupPro.Storage.RemoveMarkedHiddenTags();
            }
        }

        $('#dup-storage-form').submit(DupPro.Storage.PrepareForSubmit);

        // GENERAL STORAGE LOGIC
        DupPro.Storage.ChangeMode = function (animateOverride) {
            var optionSelected = $("#change-mode option:selected");
            var mode = optionSelected.val();
            var s3_provider = optionSelected.data("s3-provider");            

            var animate = 400;
            if (arguments.length == 1) {
                animate = animateOverride;
            }

            $('.provider').hide();
            $('#provider-' + mode).show(animate);
            if (s3_provider != null) {
                $('#s3_provider').val(s3_provider);
                $('#s3-provider-' + s3_provider).show(animate);
            }
            DupPro.Storage.BindParsley(mode, s3_provider);
        }

        DupPro.Storage.ChangeMode(0);

        // DROPBOX RELATED METHODS
        DupPro.Storage.Dropbox.AuthorizationStates = {
            UNAUTHORIZED: 0,
            WAITING_FOR_REQUEST_TOKEN: 1,
            WAITING_FOR_AUTH_BUTTON_CLICK: 2,
            WAITING_FOR_ACCESS_TOKEN: 3,
            AUTHORIZED: 4
        }

        //=========================================================================
        //ONEDRIVE SPECIFIC
        //=========================================================================
        DupPro.Storage.OneDrive.GetAuthUrl = function (isBusiness = 0)
        {
            jQuery('.button_onedrive_test').prop('disabled', true);
            var msgraph_all_folders_read_write_perm = $("#onedrive_msgraph_all_folders_read_write_perm").is(':checked')
                                                ? 1 : 0;
            var data = {
                    action: 'duplicator_pro_onedrive_get_auth_url', 
                    business: isBusiness,
                    storage_type: $("#change-mode").val(),
                    msgraph_all_perms: msgraph_all_folders_read_write_perm,
                    nonce: '<?php echo wp_create_nonce('duplicator_pro_onedrive_get_auth_url'); ?>'
                };

            $.ajax({
                type: "POST",
                url: ajaxurl,
                data: data,
                success: function (respData) {
                    try {
                        var parsedData = DupPro.parseJSON(respData);
                    } catch(err) {
                        console.error(err);
                        console.error('JSON parse failed for response data: ' + respData);
                        alert("<?php DUP_PRO_U::esc_html_e('Unable to get OneDrive authentication URL.'); ?>");    
                        return false;
                    }

                    if (parsedData.success) {
                        var msgraph_str = ($("#change-mode").val() == <?php echo DUP_PRO_Storage_Types::OneDriveMSGraph;?>)
                            ? 'msgraph-'
                            : '';
                        $(".onedrive-" + msgraph_str + "auth-container").show();
                        $("#onedrive-" + msgraph_str + "is-business").val(isBusiness);
                        $("#dpro-onedrive-" + msgraph_str + "connect-btn").hide();
                        if ($("#change-mode").val() != <?php echo DUP_PRO_Storage_Types::OneDriveMSGraph;?>) {
                            $("#dpro-onedrive-business-connect-btn").hide();
                        }                        
                        DupPro.Storage.OneDrive.AuthUrl = parsedData.onedrive_auth_url;
                    } else {
                        alert("<?php DUP_PRO_U::esc_html_e('Error getting OneDrive authentication URL. Please try again later.') ?>");
                        console.log(parsedData);
                    }
                },
                error: function (respData) {
                    alert("<?php DUP_PRO_U::esc_html_e('Unable to get OneDrive authentication URL.') ?>");
                    console.log(respData);
                }
            });
        };

        DupPro.Storage.OneDrive.OpenAuthPage = function () {
            // console.log(DupPro.Storage.OneDrive.AuthUrl);
            window.open(DupPro.Storage.OneDrive.AuthUrl, '_blank');
        }

        DupPro.Storage.OneDrive.CancelAuthorization = function ()
        {
<?php if (DUP_PRO_StorageSupported::isOneDriveSupported()) : ?>
                window.open(
                    ($("#change-mode").val() == <?php echo DUP_PRO_Storage_Types::OneDriveMSGraph;?>)
                        ? '<?php echo DUP_PRO_Onedrive_U::get_onedrive_logout_url(true); ?>'
                        : '<?php echo DUP_PRO_Onedrive_U::get_onedrive_logout_url(); ?>', '_blank');
                $("#dup-storage-form-action").val('onedrive-revoke-access');
                $("#dup-storage-form").submit();
<?php endif; ?>
        }

        DupPro.Storage.OneDrive.FinalizeSetup = function () {
            var msgraph_str = ($("#change-mode").val() == <?php echo DUP_PRO_Storage_Types::OneDriveMSGraph;?>)
                            ? 'msgraph-'
                            : '';
            if ($('#onedrive-' + msgraph_str + 'auth-code').val().length > 5) {
                $("#dup-storage-form").submit();
            } else {
                <?php $alertOneDriveConnStatus->showAlert(); ?>
                let alertMsg = "<i class='fas fa-exclamation-triangle'></i> <?php DUP_PRO_U::esc_html_e('Please enter your OneDrive authorization code!'); ?>";
                <?php $alertOneDriveConnStatus->updateMessage("alertMsg"); ?>
            }
        }

        DupPro.Storage.OneDrive.SendFileTest = function ()
        {
            var msgraph_str = ($("#change-mode").val() == <?php echo DUP_PRO_Storage_Types::OneDriveMSGraph;?>)
                                ? 'msgraph_'
                                : '';
            var current_storage_folder = $('#_onedrive_' + msgraph_str + 'storage_folder').val();
            var use_msgraph_api = ($("#change-mode").val() == <?php echo DUP_PRO_Storage_Types::OneDriveMSGraph;?>) ? 1 : 0;
            var data = {
                action: 'duplicator_pro_onedrive_send_file_test',
                storage_id: <?php echo absint($storage->id); ?>,
                storage_folder: current_storage_folder,
                nonce: '<?php echo wp_create_nonce('duplicator_pro_onedrive_send_file_test'); ?>'
            };
            
            var $test_button = $('#button_onedrive_' + msgraph_str + 'send_file_test');
            $test_button.html('<i class="fas fa-circle-notch fa-spin"></i> <?php DUP_PRO_U::esc_html_e("Attempting Connection Please Wait..."); ?>');

            $.ajax({
                type: "POST",
                url: ajaxurl,
                data: data,
                success: function (respData) {
                    try {
                        var parsedData = DupPro.parseJSON(respData);
                    } catch(err) {
                        console.error(err);
                        console.error('JSON parse failed for response data: ' + respData);
                        $test_button.html('<i class="fas fa-cloud-upload-alt fa-sm"></i> <?php DUP_PRO_U::esc_html_e("Test Onedrive Connection"); ?>');
                        <?php $alertOneDriveConnStatus->showAlert(); ?>
                        let alertMsg = "<i class='fas fa-exclamation-triangle'></i> <?php DUP_PRO_U::esc_html_e('Send OneDrive file test failed. JSON parse failed for response data.'); ?>";
                        <?php $alertOneDriveConnStatus->updateMessage("alertMsg"); ?>
                        console.log(respData);
                        return false;
                    }
                    $test_button.html('<i class="fas fa-cloud-upload-alt fa-sm"></i> <?php DUP_PRO_U::esc_html_e("Test Onedrive Connection"); ?>');
                    if (parsedData.success === true) {
                        <?php $alertOneDriveConnStatusSuccess->showAlert(); ?>
                        <?php $alertOneDriveConnStatusSuccess->updateMessage("parsedData.message"); ?>
                    } else {                        
                        <?php $alertOneDriveConnStatus->showAlert(); ?>
                        <?php $alertOneDriveConnStatus->updateMessage("parsedData.message"); ?>
                        console.log(parsedData);
                    }
                },
                error: function (respData) {
                    $test_button.html('<i class="fas fa-cloud-upload-alt fa-sm"></i> <?php DUP_PRO_U::esc_html_e("Test Onedrive Connection"); ?>');
                    <?php $alertOneDriveConnStatus->showAlert(); ?>
                    let alertMsg = "<i class='fas fa-exclamation-triangle'></i> <?php DUP_PRO_U::esc_html_e('Send OneDrive file test failed. AJAX error!'); ?>";
                    <?php $alertOneDriveConnStatus->updateMessage("alertMsg"); ?>
                    console.log(respData);
                }
            });
        };

        //=========================================================================
        //DROPBOX SPECIFIC
        //=========================================================================
        DupPro.Storage.Dropbox.authorizationState = <?php echo $storage->dropbox_authorization_state; ?>;

        DupPro.Storage.Dropbox.CancelAuthorization = function ()
        {
            $("#dup-storage-form-action").val('dropbox-revoke-access');
            $("#dup-storage-form").submit();
        }

        DupPro.Storage.Dropbox.DropboxGetAuthUrl = function ()
        {
            jQuery('.authorization-state').hide();
            jQuery('#state-waiting-for-request-token').show();
            jQuery('.button_dropbox_test').prop('disabled', true);
            var data = {
                action: 'duplicator_pro_dropbox_get_auth_url',
                nonce: '<?php echo wp_create_nonce('duplicator_pro_dropbox_get_auth_url'); ?>'
            };

            $.ajax({
                type: "POST",
                url: ajaxurl,
                data: data,
                success: function (respData) {
                    try {
                        var parsedData = DupPro.parseJSON(respData);
                    } catch(err) {
                        console.error(err);
                        console.error('JSON parse failed for response data: ' + respData);
                        <?php $alertDropboxConnStatus->showAlert(); ?>
                        let alertMsg = "<i class='fas fa-exclamation-triangle'></i> <?php DUP_PRO_U::esc_html_e('Unable to get Dropbox authentication URL. JSON parse failed for response data.'); ?>";
                        <?php $alertDropboxConnStatus->updateMessage("alertMsg"); ?>
                        return false;
                    }

                    // Success
                    if (parsedData.success) {
                        DupPro.Storage.Dropbox.AuthUrl = parsedData.dropbox_auth_url;
                        jQuery("#state-waiting-for-auth-button-click").show();
                    } else {
                        <?php $alertDropboxConnStatus->showAlert(); ?>
                        let alertMsg = "<i class='fas fa-exclamation-triangle'></i> <?php DUP_PRO_U::esc_html_e('Error getting Dropbox authentication URL. Please try again later.'); ?>";
                        <?php $alertDropboxConnStatus->updateMessage("alertMsg"); ?>
                        console.log(parsedData);
                        jQuery('.authorization-state').show();
                    }
                },
                error: function (data) {
                    <?php $alertDropboxConnStatus->showAlert(); ?>
                    let alertMsg = "<i class='fas fa-exclamation-triangle'></i> <?php DUP_PRO_U::esc_html_e('Unable to get Dropbox authentication URL. AJAX error!'); ?>";
                    <?php $alertDropboxConnStatus->updateMessage("alertMsg"); ?>
                },
                complete: function (data) {
                    jQuery('#state-waiting-for-request-token').hide();
                }
            });
        };

        DupPro.Storage.Dropbox.TransitionAuthorizationState = function (newState)
        {
            jQuery('.authorization-state').hide();
            jQuery('.dropbox_access_type').prop('disabled', true);
            jQuery('.button_dropbox_test').prop('disabled', true);

            switch (newState) {
                case DupPro.Storage.Dropbox.AuthorizationStates.UNAUTHORIZED:
                    jQuery('.dropbox_access_type').prop('disabled', false);
                    $("#dropbox_authorization_state").val(DupPro.Storage.Dropbox.AuthorizationStates.UNAUTHORIZED);
                    DupPro.Storage.Dropbox.requestToken = null;
                    jQuery("#state-unauthorized").show();
                    break;

                case DupPro.Storage.Dropbox.AuthorizationStates.WAITING_FOR_REQUEST_TOKEN:
                    DupPro.Storage.Dropbox.GetRequestToken();
                    jQuery("#state-waiting-for-request-token").show();
                    break;

                case DupPro.Storage.Dropbox.AuthorizationStates.WAITING_FOR_AUTH_BUTTON_CLICK:
                    // Nothing to do here other than show the button and wait
                    jQuery("#state-waiting-for-auth-button-click").show();
                    break;

                case DupPro.Storage.Dropbox.AuthorizationStates.WAITING_FOR_ACCESS_TOKEN:
                    jQuery("#state-waiting-for-access-token").show();
                    if (DupPro.Storage.Dropbox.requestToken != null) {
                        DupPro.Storage.Dropbox.GetAccessToken();
                    } else {
                        <?php $alertDropboxConnStatus->showAlert(); ?>
                        let alertMsg = "<i class='fas fa-exclamation-triangle'></i> <?php DUP_PRO_U::esc_html_e('Tried transitioning to auth button click but don\'t have the request token!'); ?>";
                        <?php $alertDropboxConnStatus->updateMessage("alertMsg"); ?>
                        DupPro.Storage.Dropbox.TransitionAuthorizationState(DupPro.Storage.Dropbox.AuthorizationStates.UNAUTHORIZED);
                    }
                    break;

                case DupPro.Storage.Dropbox.AuthorizationStates.AUTHORIZED:
                    var token = $("#dropbox_access_token").val();
                    var token_secret = $("#dropbox_access_token_secret").val();
                    DupPro.Storage.Dropbox.accessToken = {};
                    DupPro.Storage.Dropbox.accessToken.t = token;
                    DupPro.Storage.Dropbox.accessToken.s = token_secret;
                    jQuery("#state-authorized").show();
                    jQuery('.button_dropbox_test').prop('disabled', false);
                    break;
            }

            DupPro.Storage.Dropbox.authorizationState = newState;
        }

        DupPro.Storage.Dropbox.SendFileTest = function ()
        {
            var fullAccess = $('#dropbox_accesstype_full').is(":checked");
            var current_storage_folder = $('#_dropbox_storage_folder').val();
            var data = {
                action: 'duplicator_pro_dropbox_send_file_test',
                storage_id: <?php echo absint($storage->id); ?>,
                storage_folder: current_storage_folder,
                full_access: fullAccess,
                nonce: '<?php echo wp_create_nonce('duplicator_pro_dropbox_send_file_test'); ?>'
            };
            var $test_button = $('#button_dropbox_send_file_test');
            $test_button.html('<i class="fas fa-circle-notch fa-spin"></i> <?php DUP_PRO_U::esc_html_e("Attempting Connection Please Wait..."); ?>');

            $.ajax({
                type: "POST",
                url: ajaxurl,
                data: data,
                success: function (respData) {
                    try {
                        var parsedData = DupPro.parseJSON(respData);
                    } catch(err) {
                        console.error(err);
                        console.error('JSON parse failed for response data: ' + respData);
                        $test_button.html('<i class="fas fa-cloud-upload-alt fa-sm"></i> <?php DUP_PRO_U::esc_html_e("Test Dropbox Connection"); ?>');
                        <?php $alertDropboxConnStatus->showAlert(); ?>
                        let alertMsg = "<i class='fas fa-exclamation-triangle'></i> <?php DUP_PRO_U::esc_html_e('Send Dropbox file test failed. JSON parse failed for response data.'); ?>";
                        <?php $alertDropboxConnStatus->updateMessage("alertMsg"); ?>
                        console.log(respData);
                        return false;
                    }

                    $test_button.html('<i class="fas fa-cloud-upload-alt fa-sm"></i> <?php DUP_PRO_U::esc_html_e("Test Dropbox Connection"); ?>');
                    if (parsedData.success) {
                        <?php $alertDropboxConnStatusSuccess->showAlert(); ?>
                        <?php $alertDropboxConnStatusSuccess->updateMessage("parsedData.message"); ?>
                    } else {
                        <?php $alertDropboxConnStatus->showAlert(); ?>
                        let alertMsg = "<i class='fas fa-exclamation-triangle'></i> <?php DUP_PRO_U::esc_html_e('Send Dropbox file test failed.'); ?>";
                        <?php $alertDropboxConnStatus->updateMessage("alertMsg"); ?>
                        console.log(parsedData);
                    }
                },
                error: function (data) {
                    $test_button.html('<i class="fas fa-cloud-upload-alt fa-sm"></i> <?php DUP_PRO_U::esc_html_e("Test Dropbox Connection"); ?>');
                    <?php $alertDropboxConnStatus->showAlert(); ?>
                    let alertMsg = "<i class='fas fa-exclamation-triangle'></i> <?php DUP_PRO_U::esc_html_e('Send Dropbox file test failed. AJAX error!'); ?>";
                    <?php $alertDropboxConnStatus->updateMessage("alertMsg"); ?>
                    console.log(data);
                }
            });
        }

        DupPro.Storage.Dropbox.OpenAuthPage = function ()
        {
            window.open(DupPro.Storage.Dropbox.AuthUrl, '_blank');
        }

        DupPro.Storage.Dropbox.Authorize = function ()
        {
            window.open(DupPro.Storage.Dropbox.AuthUrl, '_blank');
            $('button#auth-validate').prop('disabled', false);
        }

        DupPro.Storage.Dropbox.FinalizeSetup = function ()
        {
            if ($('#dropbox-auth-code').val().length > 5) {
                $("#dup-storage-form").submit();
            } else {
                <?php $alertDropboxConnStatus->showAlert(); ?>
                let alertMsg = "<i class='fas fa-exclamation-triangle'></i> <?php DUP_PRO_U::esc_html_e('Please enter your Dropbox authorization code!'); ?>";
                <?php $alertDropboxConnStatus->updateMessage("alertMsg"); ?>
            }
        }

        DupPro.Storage.Dropbox.TransitionAuthorizationState(DupPro.Storage.Dropbox.authorizationState);
        $('button#auth-validate').prop('disabled', true);

        // GOOGLE DRIVE RELATED METHODS
        DupPro.Storage.GDrive.OpenAuthPage = function ()
        {
            window.open(DupPro.Storage.GDrive.AuthUrl, '_blank');
        }


        //=========================================================================
        //GOOGLE-DRIVE SPECIFIC
        //=========================================================================
        DupPro.Storage.GDrive.FinalizeSetup = function ()
        {
            if ($('#gdrive-auth-code').val().length > 5) {
                $("#dup-storage-form").submit();
            } else {
                <?php $alertGoogleDriveConnStatus->showAlert(); ?>
                let alertMsg = "<i class='fas fa-exclamation-triangle'></i> <?php DUP_PRO_U::esc_html_e('Please enter your Google Drive authorization code!'); ?>";
                <?php $alertGoogleDriveConnStatus->updateMessage("alertMsg"); ?>
            }
        }

        DupPro.Storage.GDrive.GoogleGetAuthUrl = function ()
        {
            $('#dpro-gdrive-connect-btn-area').hide();
            $('#dpro-gdrive-connect-progress').show();

            var data = {
                action: 'duplicator_pro_gdrive_get_auth_url',
                nonce: '<?php echo wp_create_nonce('duplicator_pro_gdrive_get_auth_url'); ?>'
            };

            $.ajax({
                type: "POST",
                url: ajaxurl,
                data: data,
                success: function (respData) {
                    try {
                        var parsedData = DupPro.parseJSON(respData);
                    } catch(err) {
                        console.error(err);
                        console.error('JSON parse failed for response data: ' + respData);
                        <?php $alertGoogleDriveConnStatus->showAlert(); ?>
                        let alertMsg = "<i class='fas fa-exclamation-triangle'></i> <?php DUP_PRO_U::esc_html_e('Unable to get Google Drive authentication URL. JSON parse failed for response data.'); ?>";
                        <?php $alertGoogleDriveConnStatus->updateMessage("alertMsg"); ?>
                        return false;
                    }

                    if (parsedData.status === 0) {
                        DupPro.Storage.GDrive.AuthUrl = parsedData['gdrive_auth_url'];
                        $('#dpro-gdrive-connect-btn-area').hide();
                        $('#dpro-gdrive-steps').show();
                    } else if (parsedData.status === -2) {
                        <?php $alertGoogleDriveConnStatus->showAlert(); ?>
                        let alertMsg = "<i class='fas fa-exclamation-triangle'></i> <?php DUP_PRO_U::esc_html_e('Google Drive not supported on systems running PHP version < 5.3.2.'); ?>";
                        <?php $alertGoogleDriveConnStatus->updateMessage("alertMsg"); ?>
                        console.log(parsedData);
                        $('#dpro-gdrive-connect-btn-area').show();
                    } else {
                        <?php $alertGoogleDriveConnStatus->showAlert(); ?>
                        let alertMsg = "<i class='fas fa-exclamation-triangle'></i> <?php DUP_PRO_U::esc_html_e('Error getting Google Drive authentication URL. Please try again later. '); ?>";
                        alertMsg += "Error Message: "+parsedData.message;
                        <?php $alertGoogleDriveConnStatus->updateMessage("alertMsg"); ?>
                        $('#dpro-gdrive-connect-btn-area').show();
                    }
                },
                error: function (data) {
                    <?php $alertGoogleDriveConnStatus->showAlert(); ?>
                    let alertMsg = "<i class='fas fa-exclamation-triangle'></i> <?php DUP_PRO_U::esc_html_e('Unable to get Google Drive authentication URL. AJAX error!'); ?>";
                    <?php $alertGoogleDriveConnStatus->updateMessage("alertMsg"); ?>
                },
                complete: function (data) {
                    $('#dpro-gdrive-connect-progress').hide();
                }
            });
        }

        DupPro.Storage.GDrive.CancelAuthorization = function ()
        {
            $("#dup-storage-form-action").val('gdrive-revoke-access');
            $("#dup-storage-form").submit();
        }

        DupPro.Storage.GDrive.SendFileTest = function () {
            var current_storage_folder = $('#_gdrive_storage_folder').val();
            var data = {
                action: 'duplicator_pro_gdrive_send_file_test',
                storage_folder: current_storage_folder,
                storage_id: <?php echo absint($storage->id); ?>,
                nonce: '<?php echo wp_create_nonce('duplicator_pro_gdrive_send_file_test'); ?>'
            };
            var $test_button = $('#button_gdrive_send_file_test');
            $test_button.html('<i class="fas fa-circle-notch fa-spin"></i> <?php DUP_PRO_U::esc_html_e("Attempting Connection Please Wait..."); ?>');

            $.ajax({
                type: "POST",
                url: ajaxurl,
                data: data,
                success: function (respData) {
                    try {
                        var parsedData = DupPro.parseJSON(respData);
                    } catch(err) {
                        console.error(err);
                        console.error('JSON parse failed for response data: ' + respData);
                        $test_button.html('<i class="fas fa-cloud-upload-alt fa-sm"></i> <?php DUP_PRO_U::esc_html_e("Test Google Drive Connection"); ?>');
                        <?php $alertGoogleDriveConnStatus->showAlert(); ?>
                        let alertMsg = "<i class='fas fa-exclamation-triangle'></i> <?php DUP_PRO_U::esc_html_e('Send Google Drive file test failed. JSON parse failed for response data.'); ?>";
                        <?php $alertGoogleDriveConnStatus->updateMessage("alertMsg"); ?>
                        console.log(respData);
                        return false;
                    }

                    $test_button.html('<i class="fas fa-cloud-upload-alt fa-sm"></i> <?php DUP_PRO_U::esc_html_e("Test Google Drive Connection"); ?>');
                    if (parsedData.success) {
                        <?php $alertGoogleDriveConnStatusSuccess->showAlert(); ?>
                        <?php $alertGoogleDriveConnStatusSuccess->updateMessage("parsedData.message"); ?>
                    } else {
                        <?php $alertGoogleDriveConnStatus->showAlert(); ?>
                        let alertMsg = "<i class='fas fa-exclamation-triangle'></i> <?php DUP_PRO_U::esc_html_e('Send Google Drive file test failed.'); ?>";
                        <?php $alertGoogleDriveConnStatus->updateMessage("alertMsg"); ?>
                        console.log(parsedData);
                    }
                },
                error: function (data) {
                    $test_button.html('<i class="fas fa-cloud-upload-alt fa-sm"></i> <?php DUP_PRO_U::esc_html_e("Test Google Drive Connection"); ?>');
                    <?php $alertGoogleDriveConnStatus->showAlert(); ?>
                    let alertMsg = "<i class='fas fa-exclamation-triangle'></i> <?php DUP_PRO_U::esc_html_e('Send Google Drive file test failed. AJAX error!'); ?>";
                    <?php $alertGoogleDriveConnStatus->updateMessage("alertMsg"); ?>
                    console.log(data);
                }
            });
        }

        //=========================================================================
        //FTP SPECIFIC
        //=========================================================================
        DupPro.Storage.FTP.SendFileTest = function ()
        {
            var current_storage_folder = $('#_ftp_storage_folder').val();
            var server = $('#ftp_server').val();
            var port = $('#ftp_port').val();
            var username = $('#ftp_username').val();
            var password = $('#ftp_password').val();
            var ssl = $('#_ftp_ssl').prop('checked') ? 1 : 0;
            var passive_mode = $('#_ftp_passive_mode').prop('checked') ? 1 : 0;
            var use_curl  = $('#_ftp_use_curl').prop('checked') ? 1 : 0;
            var $test_button = $('#button_ftp_send_file_test');

            var data = {
                action: 'duplicator_pro_ftp_send_file_test',
                storage_id: <?php echo $storage->id; ?>,
                storage_folder: current_storage_folder,
                server: server,
                port: port,
                username: username,
                password: password,
                ssl: ssl,
                passive_mode: passive_mode,
                use_curl: use_curl,
                nonce: '<?php echo wp_create_nonce('duplicator_pro_ftp_send_file_test'); ?>'
            };

            $test_button.html('<i class="fas fa-circle-notch fa-spin"></i> <?php DUP_PRO_U::esc_html_e('Attempting Connection Please Wait...'); ?>');
            $.ajax({
                type: "POST",
                url: ajaxurl,
                data: data,
                success: function (respData) {
                    try {
                        var parsedData = DupPro.parseJSON(respData);
                    } catch(err) {
                        console.error(err);
                        console.error('JSON parse failed for response data: ' + respData);
                        $test_button.html('<i class="fas fa-cloud-upload-alt fa-sm"></i> <?php DUP_PRO_U::esc_html_e('Test FTP Connection'); ?>');
                        <?php $alertFTPConnStatus->showAlert(); ?>
                        let alertMsg = "<i class='fas fa-exclamation-triangle'></i> <?php DUP_PRO_U::esc_html_e('JSON parse failed for response data.'); ?>";
                        <?php $alertFTPConnStatus->updateMessage("alertMsg"); ?>
                        console.log(respData);
                        return false;
                    }

                    if (parsedData.success) {
                        if (parsedData.status_msgs.length==0) {
                            <?php $alertFTPConnStatus->showAlert(); ?>
                            let alertMsg = "<span style='color:green'><b><input type='checkbox' class='checkbox' checked disabled='disabled'>"+parsedData.message+"</b></span>";
                            <?php $alertFTPConnStatus->updateMessage("alertMsg"); ?>
                        } else {
                            <?php $alertFTPConnStatusLong->showAlert(); ?>
                            <?php $alertFTPConnStatusLong->updateTextareaMessage("parsedData.status_msgs"); ?>
                            let alertMsg = "<span style='color:green'><b><input type='checkbox' class='checkbox' checked disabled='disabled'>"+parsedData.message+"</b></span>";
                            <?php $alertFTPConnStatusLong->updateMessage("alertMsg"); ?>
                        }
                    } else {
                        if (parsedData.status_msgs.length==0) {
                            <?php $alertFTPConnStatus->showAlert(); ?>
                            let alertMsg = "<i class='fas fa-exclamation-triangle'></i> "+parsedData.message;
                            <?php $alertFTPConnStatus->updateMessage("alertMsg"); ?>
                        } else {
                            <?php $alertFTPConnStatusLong->showAlert(); ?>
                            <?php $alertFTPConnStatusLong->updateTextareaMessage("parsedData.status_msgs"); ?>
                            let alertMsg = "<i class='fas fa-exclamation-triangle'></i> "+parsedData.message;
                            <?php $alertFTPConnStatusLong->updateMessage("alertMsg"); ?>
                        }
                        console.log(parsedData);
                    }
                    $test_button.html('<i class="fas fa-cloud-upload-alt fa-sm"></i> <?php DUP_PRO_U::esc_html_e('Test FTP Connection'); ?>');
                },
                error: function (parsedData) {
                    $test_button.html('<i class="fas fa-cloud-upload-alt fa-sm"></i> <?php DUP_PRO_U::esc_html_e('Test FTP Connection'); ?>');
                    <?php $alertFTPConnStatus->showAlert(); ?>
                    let alertMsg = "<i class='fas fa-exclamation-triangle'></i> <?php DUP_PRO_U::esc_html_e('AJAX error while testing FTP connection'); ?>";
                    <?php $alertFTPConnStatus->updateMessage("alertMsg"); ?>
                    console.log(parsedData);
                }
            });
        }

        //=========================================================================
        //SFTP SPECIFIC
        //=========================================================================
        DupPro.Storage.SFTP.SendFileTest = function ()
        {
            var current_storage_folder = $('#_sftp_storage_folder').val();
            var server = $('#sftp_server').val();
            var port = $('#sftp_port').val();
            var username = $('#sftp_username').val();
            var password = $('#sftp_password').val();
            var private_key_password = $('#sftp_private_key_password').val();
            var $test_button = $('#button_sftp_send_file_test');
            var sftp_private_key = $('#sftp_private_key').val();
            var data = {
                action: 'duplicator_pro_sftp_send_file_test',
                storage_id: <?php echo $storage->id; ?>,
                storage_folder: current_storage_folder,
                server: server,
                port: port,
                username: username,
                password: password,
                private_key: sftp_private_key,
                private_key_password: private_key_password,
                nonce: '<?php echo wp_create_nonce('duplicator_pro_sftp_send_file_test'); ?>'
            };

            $test_button.html('<i class="fas fa-circle-notch fa-spin"></i> <?php DUP_PRO_U::esc_html_e('Attempting Connection Please Wait...'); ?>');

            $.ajax({
                type: "POST",
                url: ajaxurl,
                data: data,
                success: function (respData) {
                    try {
                        var parsedData = DupPro.parseJSON(respData);
                    } catch(err) {
                        console.error(err);
                        console.error('JSON parse failed for response data: ' + respData);
                        $test_button.html('<i class="fas fa-cloud-upload-alt fa-sm"></i> <?php DUP_PRO_U::esc_html_e('Test SFTP Connection'); ?>');
                        <?php $alertSFTPConnStatus->showAlert(); ?>
                        let alertMsg = "<i class='fas fa-exclamation-triangle'></i> <?php DUP_PRO_U::esc_html_e('JSON parse failed for response data.'); ?>";
                        <?php $alertSFTPConnStatus->updateMessage("alertMsg"); ?>
                        console.log(respData);
                        return false;
                    }

                    if (parsedData.success) {
                        if (parsedData.status_msgs.length==0) {
                            <?php $alertSFTPConnStatus->showAlert(); ?>
                            let alertMsg = "<span style='color:green'><b><input type='checkbox' class='checkbox' checked disabled='disabled'>"+parsedData.message+"</b></span>";
                            <?php $alertSFTPConnStatus->updateMessage("alertMsg"); ?>
                        } else {
                            <?php $alertSFTPConnStatusLong->showAlert(); ?>
                            <?php $alertSFTPConnStatusLong->updateTextareaMessage("parsedData.status_msgs"); ?>
                            let alertMsg = "<span style='color:green'><b><input type='checkbox' class='checkbox' checked disabled='disabled'>"+parsedData.message+"</b></span>";
                            <?php $alertSFTPConnStatusLong->updateMessage("alertMsg"); ?>
                        }
                    } else {
                        if (parsedData.status_msgs.length==0) {
                            <?php $alertSFTPConnStatus->showAlert(); ?>
                            let alertMsg = "<i class='fas fa-exclamation-triangle'></i> "+parsedData.message;
                            <?php $alertSFTPConnStatus->updateMessage("alertMsg"); ?>
                        } else {
                            <?php $alertSFTPConnStatusLong->showAlert(); ?>
                            <?php $alertSFTPConnStatusLong->updateTextareaMessage("parsedData.status_msgs"); ?>
                            let alertMsg = "<i class='fas fa-exclamation-triangle'></i> "+parsedData.message;
                            <?php $alertSFTPConnStatusLong->updateMessage("alertMsg"); ?>
                        }
                        console.log(parsedData);
                    }
                    $test_button.html('<i class="fas fa-cloud-upload-alt fa-sm"></i> <?php DUP_PRO_U::esc_html_e('Test SFTP Connection'); ?>');
                },
                error: function (respData) {
                    $test_button.html('<i class="fas fa-cloud-upload-alt fa-sm"></i> <?php DUP_PRO_U::esc_html_e('Test SFTP Connection'); ?>');
                    <?php $alertSFTPConnStatus->showAlert(); ?>
                    let alertMsg = "<i class='fas fa-exclamation-triangle'></i> <?php DUP_PRO_U::esc_html_e('AJAX error while testing SFTP connection'); ?>";
                    <?php $alertSFTPConnStatus->updateMessage("alertMsg"); ?>
                    console.log(respData);
                }
            });
        }

        DupPro.Storage.SFTP.ReadPrivateKey = function (file_obj)
        {
            var files = file_obj.files;
            var private_key = files[0];
            var reader = new FileReader();
            reader.onload = function (e) {
                $("#sftp_private_key").val(e.target.result);
            }
            reader.readAsText(private_key);
        }


        //=========================================================================
        //AMAZON S3 SPECIFIC
        //=========================================================================

        $("#s3_endpoint_backblaze")[0].onchange = function () {
            // Update s3 region based on current endpoint
            var endpoint = $("#s3_endpoint_backblaze").val();
            // Find 2 dots in endpoint
            var dot1Pos = endpoint.indexOf(".", 0);
            var dot2Pos = endpoint.indexOf(".", dot1Pos+1);
            if (dot2Pos === -1) { // 2nd dot not found
                $("#s3_region_backblaze").val("");
                return;
            }
            var region = endpoint.substring(dot1Pos+1, dot2Pos);
            $("#s3_region_backblaze").val(region);
        };

        DupPro.Storage.S3.SendFileTest = function () {
            var s3_provider = $('#s3_provider').val();
            var current_access_key = $('#s3_access_key_'+s3_provider).val();
            var current_secret_key = $('#s3_secret_key_'+s3_provider).val();
            var current_endpoint = $('#s3_endpoint_'+s3_provider).val();
            var current_region = $('#s3_region_'+s3_provider).val();
            var current_storage_class = $('#s3_storage_class_'+s3_provider).val();
            var current_storage_folder = $('#_s3_storage_folder_'+s3_provider).val();
            var current_bucket = $('#s3_bucket_'+s3_provider).val();            
            var current_ACL_full_control = $('#s3_ACL_full_control_'+s3_provider).prop('checked') ? 1 : 0;
            
            var data = {
                action: 'duplicator_pro_s3_send_file_test',
                storage_id: <?php echo $storage->id; ?>,
                access_key: current_access_key,
                secret_key: current_secret_key,
                endpoint: current_endpoint,
                region: current_region,
                storage_class: current_storage_class,
                storage_folder: current_storage_folder,
                bucket: current_bucket,
                ACL_full_control: current_ACL_full_control,
                nonce: '<?php echo wp_create_nonce('duplicator_pro_s3_send_file_test'); ?>'
            };

            var $test_button = $('#button_s3_send_file_test_'+s3_provider);
            $test_button.html('<i class="fas fa-circle-notch fa-spin"></i> <?php DUP_PRO_U::esc_html_e("Attempting Connection Please Wait..."); ?>');

            $.ajax({
                type: "POST",
                url: ajaxurl,
                data: data,
                success: function (respData) {
                    try {
                        var parsedData = DupPro.parseJSON(respData);
                    } catch(err) {
                        console.error(err);
                        console.error('JSON parse failed for response data: ' + respData);
                        $test_button.html('<i class="fas fa-cloud-upload-alt fa-sm"></i> <?php DUP_PRO_U::esc_html_e("Test S3 Connection"); ?>');
                        <?php $alertS3ConnStatus->showAlert(); ?>
                        let alertMsg = "<i class='fas fa-exclamation-triangle'></i> <?php DUP_PRO_U::esc_html_e('JSON parse failed for response data.'); ?>";
                        <?php $alertS3ConnStatus->updateMessage("alertMsg"); ?>
                        console.log(respData);
                        return false;
                    }
                    
                    if (parsedData.success) {
                        if (parsedData.status_msgs.length==0) {
                            <?php $alertS3ConnStatus->showAlert(); ?>
                            let alertMsg = "<span style='color:green'><b><input type='checkbox' class='checkbox' checked disabled='disabled'>"+parsedData.message+"</b></span>";
                            <?php $alertS3ConnStatus->updateMessage("alertMsg"); ?>
                        } else {
                            <?php $alertS3ConnStatusLong->showAlert(); ?>
                            <?php $alertS3ConnStatusLong->updateTextareaMessage("parsedData.status_msgs"); ?>
                            let alertMsg = "<span style='color:green'><b><input type='checkbox' class='checkbox' checked disabled='disabled'>"+parsedData.message+"</b></span>";
                            <?php $alertS3ConnStatusLong->updateMessage("alertMsg"); ?>
                        }
                    } else {
                        if (parsedData.status_msgs.length==0) {
                            <?php $alertS3ConnStatus->showAlert(); ?>
                            let alertMsg = "<i class='fas fa-exclamation-triangle'></i> " + parsedData.message;
                            <?php $alertS3ConnStatus->updateMessage("alertMsg"); ?>
                        } else {
                            <?php $alertS3ConnStatusLong->showAlert(); ?>
                            <?php $alertS3ConnStatusLong->updateTextareaMessage("parsedData.status_msgs"); ?>
                            let alertMsg = "<i class='fas fa-exclamation-triangle'></i> " + parsedData.message;
                            <?php $alertS3ConnStatusLong->updateMessage("alertMsg"); ?>
                        }
                        console.log(parsedData);
                    } 
                    $test_button.html('<i class="fas fa-cloud-upload-alt fa-sm"></i> <?php DUP_PRO_U::esc_html_e("Test S3 Connection"); ?>');
                },
                error: function (respData) {
                    $test_button.html('<i class="fas fa-cloud-upload-alt fa-sm"></i> <?php DUP_PRO_U::esc_html_e("Test S3 Connection"); ?>');
                    <?php $alertS3ConnStatus->showAlert(); ?>
                    let alertMsg = "<i class='fas fa-exclamation-triangle'></i> <?php DUP_PRO_U::esc_html_e('AJAX error while testing S3 connection'); ?>";
                    <?php $alertS3ConnStatus->updateMessage("alertMsg"); ?>
                    console.log(respData);
                }
            });
        }

        // COMMON STORAGE RELATED METHODS
        DupPro.Storage.Copy = function ()
        {
            $("#dup-storage-form-action").val('copy-storage');
            $("#dup-storage-form").parsley().destroy();
            $("#dup-storage-form").submit();
        };

        DupPro.Storage.LocalFilterToggle = function ()
        {
            $("#_local_filter_protection").is(":checked")
                    ? $("#_local_filter_protection_message").hide(400)
                    : $("#_local_filter_protection_message").show(400);

        };

        // Toggles Save Provider button for existing Storages only
        DupPro.UI.formOnChangeValues($('#dup-storage-form'), function() {
            $('#button_save_provider').prop('disabled', false);
        });

        //Init
        DupPro.Storage.LocalFilterToggle();
        jQuery('#name').focus().select();

    });
    
</script>