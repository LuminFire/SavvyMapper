<?php

add_action('wp_ajax_carto_query','dm_ajaxCartoQuery');
add_action('wp_admin_ajax_carto_query','dm_ajaxCartoQuery');
function dm_ajaxCartoQuery(){
    if(isset($_GET['archive_type'])){
        $post_type = $_GET['archive_type'];
        $sql = dm_get_sql_for_archive_post($post_type);
    }else if(isset($_GET['post_id'])){
        $post_id = $_GET['post_id'];
        $sql = dm_get_sql_for_single_post($post_id);
    }

    dm_fetch_and_format_features($sql);
}


add_action( 'wp_ajax_carto_metabox', 'dm_getCartoGeoJSON' );
add_action( 'wp_admin_ajax_carto_metabox', 'dm_getCartoGeoJSON' );
function dm_getCartoGeoJSON(){
    // $_GET['table'];
    // $_GET['lookup'];

    if(strlen($_GET['table']) === 0){
        http_response_code(400);
        exit();
    }
    $sql = "SELECT * FROM " . $_GET['table'];

    if(isset($_GET['term'])){
        $sql .= " WHERE " . $_GET['lookup'] . " LIKE '" . $_GET['term'] . "%'";
    }else if(isset($_GET['cartodb_id']) && !empty($_GET['cartodb_id'])){
        $sql .= " WHERE cartodb_id = '" . $_GET['cartodb_id'] . "'";
    }

    $sql .= " ORDER BY " . $_GET['lookup'];

    // no limits. We've got clustering
    // $sql .= " LIMIT 500";

    $json = cartoSQL($sql);

    if(is_null($json)){
        http_response_code(500);
        exit();
    }

    header("Content-Type: application/json");
    print json_encode($json);
    exit();
}
