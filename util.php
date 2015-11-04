<?php


/*
 * cURL wrapper which returns request and response headers, curl request meta, post and response body.
 *
 * Slightly simplified version of what's used in esco-4d-api.php for the api debugger page
 *
 * @param $url (String) The URL to make the request to
 * @param $data (Array) The data to post. If array is empty, GET will be used
 * @param $debug (Bool, defaults to FALSE) Should debug info be returned?
 *
 * @return A dict with all the requst info, if debug is TRUE. Otherwise just returns the response body
 */
function dm_curl_request( $url, $data = Array(), $debug = FALSE){

    $post = curl_init();
    curl_setopt($post, CURLOPT_URL, $url);
    curl_setopt($post, CURLOPT_POST, count($data));
    curl_setopt($post, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($post, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($post, CURLINFO_HEADER_OUT, true);
    curl_setopt($post, CURLOPT_VERBOSE, 1);
    curl_setopt($post, CURLOPT_HEADER, 1);

    curl_setopt($post, CURLOPT_CONNECTTIMEOUT, 5); // connect timeout
    curl_setopt($post, CURLOPT_TIMEOUT, 20); //timeout in seconds

    // Set the path to any custom cert files
    // curl_setopt($post, CURLOPT_CAINFO, plugin_dir_path( __FILE__ ).'cacert.pem');

    $response = curl_exec($post);

    $header_size = curl_getinfo($post, CURLINFO_HEADER_SIZE);
    $header = substr($response, 0, $header_size);
    $body = substr($response, $header_size);

    $info = curl_getinfo($post);

    $response = Array(
        'request_headers' => (isset($info['request_header']) ? $info['request_header'] : ''),
        'post_body' => http_build_query($data),
        'response_headers' => $header,
        'body' => $body,
        'errno' => curl_errno($post),
        'error' => curl_error($post),
        'curl_info' => $info,
    );

    curl_close($post);

    if($debug){
        return $response;
    }

    return $response['body'];
}

function dm_cartoSQL($sql,$json = TRUE){

    $options = get_option( 'dm_settings' );
    $un = $options['dm_cartodb_username'];
    $key = $options['dm_cartodb_api_key'];

    $querystring = Array(
        'q' => $sql,
        'api_key' => $key,
    );

    if($json){
        $querystring['format'] = 'GeoJSON';
    }

    $url = 'https://' . $un . '.cartodb.com/api/v2/sql?' . http_build_query($querystring);

    $ret = dm_curl_request($url);

    if($json){
        return json_decode($ret);
    }else{
        return $ret;
    }
}

function dm_get_cdbids_for_post_type($post_type){
    global $wpdb;
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
        if($one->meta_value){
            $ids[$one->meta_value] = $one->ID;
        }
    }
    return $ids;
}

function dm_get_sql_for_archive_post($post_type){
    $mappings = get_option('dm_table_mapping');
    if(!isset($mappings[$post_type])){
        http_response_code(404);
        exit();
    }

    $ids = dm_get_cdbids_for_post_type($post_type);
    $target_table = $mappings[$post_type]['table'];

    if(!empty($target_table)){
        $sql = 'SELECT * FROM "' . $target_table . '" WHERE "cartodb_id" IN (' . implode(',',array_keys($ids)) . ')';
    }

    return $sql;
}

function dm_get_sql_for_single_post($postid){
    $post = get_post($postid);
    $mappings = get_option('dm_table_mapping');
    $target_table = $mappings[$post->post_type]['table'];
    $lookup_field = $mappings[$post->post_type]['lookup'];
    $cartodb_id = get_post_meta($post->ID,'cartodb_lookup_value',TRUE);

    if(!empty($target_table) && !empty($lookup_field) && !empty($cartodb_id)){
        $sql = 'SELECT * FROM "' . $target_table . '" WHERE "cartodb_id"=\'' . $cartodb_id . "'";
        return $sql;
    }
}

function dm_fetch_and_format_features($sql){

    if(empty($sql)){
        http_response_code(500);
        exit();
    }

    $json = dm_cartoSQL($sql);

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

function dm_makePostCDBOjb($the_post = NULL){
    global $post;
    global $cartoObj;

    if(isset($cartoObj)){
        return $cartoObj;
    }

    $the_post = (is_null($the_post) ? $post : $the_post);
    $mappings = get_option('dm_table_mapping');
    $target_table = $mappings[$the_post->post_type]['table'];
    $lookup_field = $mappings[$the_post->post_type]['lookup'];

    $cartodb_id = get_post_meta($the_post->ID,'cartodb_lookup_value',TRUE);
    $cartodb_label = get_post_meta($the_post->ID,'cartodb_lookup_label',TRUE);
    $cartoObj = dm_cartoSQL("SELECT * FROM " . $target_table . " WHERE cartodb_id='" . $cartodb_id . "'"); 

    return $cartoObj;
}
