<?php

add_action('wp_ajax_carto_archive','getCartoArchiveGeoJSON');
add_action('wp_admin_ajax_carto_archive','getCartoArchiveGeoJSON');
function getCartoArchiveGeoJSON(){
    global $wpdb;
    $post_type = $_GET['post_type'];
    $table = $_GET['table'];

    $res = $wpdb->get_results( "SELECT 
        p.ID,
        pm.meta_value
        FROM 
        wp_posts p,
        wp_postmeta pm
        WHERE 
        p.post_type='".$post_type."' AND 
        p.post_status='publish' AND
        pm.post_id=p.ID AND
        pm.meta_key='cartodb_lookup_value'");

    $ids = Array();
    foreach($res as $one){
        $ids[$one->meta_value] = $one->ID;
    }

    $json = cartoSQL('SELECT * FROM ' . $table . ' WHERE cartodb_id IN (' . implode(',',array_keys($ids)) . ')');

    if(is_null($json)){
        http_response_code(500);
        exit();
    }

    $post_type_info = get_post_type_object($post_type);

    foreach($json->features as &$feature){
        $permalink = get_permalink($ids[$feature->properties->cartodb_id]);

        $popup_contents = '<table class="leafletpopup">';
        $popup_contents .= '<tr><th colspan="2"><a href="' . $permalink . '">View ' .$post_type_info->labels->singular_name .'</a></tr>';
        foreach($feature->properties as $k => $v){
            $popup_contents .= '<tr><th>' . $k . '</th><td>' . $v . '</td></tr>';
        }
        $popup_contents .= '</table>';
        $feature->popup_contents = $popup_contents;
    }

    header("Content-Type: application/json");
    print json_encode($json);
    exit();
}


add_action( 'wp_ajax_carto_ajax', 'getCartoGeoJSON' );
add_action( 'wp_admin_ajax_carto_ajax', 'getCartoGeoJSON' );
function getCartoGeoJSON(){
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

    $sql .= " LIMIT 500";

    $json = cartoSQL($sql);

    if(is_null($json)){
        http_response_code(500);
        exit();
    }

    header("Content-Type: application/json");
    print json_encode($json);
    exit();
}
