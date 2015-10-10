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
function curl_request( $url, $data = Array(), $debug = FALSE){

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

function cartoSQL($sql,$json = TRUE){

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

    return json_decode(curl_request($url));
}
