<?php


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
