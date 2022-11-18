<?php
/**
 *  資料庫匯入後 將"舊資料庫"的設定搬到"新資料庫"
 *  ex : 舊資料庫 emea_dev_243p2_20220812
 *       新資料庫 emea_m2_uat_243p2_20221006
 *
 *  php ../dev/migrate_uat_DB.php emea_dev_243p2_20220812 emea_m2_uat_243p2_20221006
 **/
if( ! isset($argv[2]) ){
    echo '請輸入 DB name';
    exit;
}

$old_db = $argv[1];
$new_db = $argv[2];

// ==============================

$env = include('app/etc/env.php');
$region = $env['region'];

$default = $env['db']['connection']['default'];
$old_conn = new mysqli($default['host'],$default['username'],$default['password'],$old_db);
$new_conn = new mysqli($default['host'],$default['username'],$default['password'],$new_db);

if ($old_conn->connect_error
    or $new_conn->connect_error){
    echo "連接 MySQL 失敗: " . mysqli_connect_error();
    exit;
}

// ==========
//  core_config_data
//      刪除 bcc 的 email
// ==========
$sql = 'DELETE FROM `core_config_data`  WHERE path like "%copy_to"';
$new_conn->query($sql);

// ==========
//  core_config_data
//      disable push order
// ==========
$sql = 'DELETE FROM `core_config_data` WHERE `path` = "sales/eop/enabled";';
$new_conn->query($sql);

// ==========
//  core_config_data
//      frontend url
//      elasticsearch setting
//      admin url
// ==========
$sql = 'DELETE FROM `core_config_data`
            WHERE scope in ("websites","stores") AND  path IN (
                "web/unsecure/base_static_url", "web/secure/base_static_url",
                "web/unsecure/base_url",        "web/secure/base_url",
                "web/unsecure/base_media_url",  "web/secure/base_media_url")';
$new_conn->query($sql);

$sql = 'SELECT * FROM `core_config_data`
                WHERE `path` IN (
                    "web/unsecure/base_static_url", "web/secure/base_static_url",
                    "web/unsecure/base_url",        "web/secure/base_url",
                    "web/unsecure/base_media_url",  "web/secure/base_media_url",
                    
                    "catalog/search/engine",
                    "catalog/search/elasticsearch7_server_hostname",
                    "catalog/search/elasticsearch7_server_port",
                    
                    "admin/security/use_form_key",
                    "admin/security/session_lifetime",
                    "admin/url/custom");';
$res = $old_conn->query($sql);
$old_core_config_data = $res->fetch_all(MYSQLI_ASSOC);

foreach( $old_core_config_data as $row ){
    $sql = 'INSERT INTO `core_config_data`
                (`scope`, `scope_id`, `path`,`value`)
            VALUES
                ("'. $row['scope'] .'", "'. $row['scope_id'] .'", "'. $row['path'] .'", "'. $row['value'] .'")
            ON DUPLICATE KEY UPDATE
                `value` = "'. $row['value'] .'";';
    $new_conn->query($sql);
}

// ==========
//  setup_module
// ==========
$sql = 'DELETE FROM `setup_module`';
$new_conn->query($sql);

$sql = 'SELECT * FROM `setup_module`';
$res = $old_conn->query($sql);

$old_setup_module = $res->fetch_all(MYSQLI_ASSOC);

foreach( $old_setup_module as $row ){
    $sql = 'INSERT INTO `setup_module`
                (`module`, `schema_version`, `data_version`)
            VALUES
                ("'. $row['module'] .'", "'. $row['schema_version'] .'", "'. $row['data_version'] .'");';
    $new_conn->query($sql);
}

// ==========

// 修正 搜尋某些關鍵字後 會跳轉到正式機的問題
$sql = 'update search_query SET redirect = "" where where redirect != "";';
$new_conn->query($sql);

$new_conn->close();
$old_conn->close();
