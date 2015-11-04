<?php

add_shortcode('dm-attr','dm_get_attr');
function dm_get_attr($atts){
    global $cartoObj;
    global $post;

    $atts = array_map('sanitize_text_field',$atts);

    // TODO: Replace this global with a singleton probably
    if(!isset($cartoObj)){
        $mappings = get_option('dm_table_mapping');
        $target_table = $mappings[$post->post_type]['table'];
        $lookup_field = $mappings[$post->post_type]['lookup'];

        $cartodb_id = get_post_meta($post->ID,'cartodb_lookup_value',TRUE);
        $cartodb_label = get_post_meta($post->ID,'cartodb_lookup_label',TRUE);
        $cartoObj = cartoSQL("SELECT * FROM " . $target_table . " WHERE cartodb_id='" . $cartodb_id . "'"); 
    }

    $props = $cartoObj->features[0]->properties;
    if(isset($props->{$atts[0]})){
        return '<span class="dapper-attr">' . $props->{$atts[0]} . '</span>';
    }
    return '';
}

add_shortcode('dm-map','dm_get_map');
function dm_get_map(){
    global $cartoObj;
    global $post;

    // TODO: Replace this global with a singleton probably
    if(!isset($cartoObj)){
        $target_table = $mappings[$post->post_type]['table'];
        $lookup_field = $mappings[$post->post_type]['lookup'];

        $cartodb_id = get_post_meta($post->ID,'cartodb_lookup_value',TRUE);
        $cartodb_label = get_post_meta($post->ID,'cartodb_lookup_label',TRUE);
        $cartoObj = cartoSQL("SELECT * FROM " . $target_table . " WHERE cartodb_id='" . $cartodb_id . "'"); 
    }
    $html = '';

    $popup_contents = '<table class="leafletpopup">';
    foreach($cartoObj->features[0]->properties as $k => $v){
        $popup_contents .= '<tr><th>' . $k . '</th><td>' . $v . '</td></tr>';
    }
    $popup_contents .= '</table>';

    $cartoObj->features[0]->popup_contents = $popup_contents;


    $props = $cartoObj->features[0]->properties;

    $html .= '<div class="dm_map_div dm_page_map_div" data-post_type="'. $post->post_type . '" data-postid="'.$post->ID.'"></div>';
    /*
    $html .= '<script>';
    $html .= 'var dm_singleFeature = ' . json_encode($cartoObj->features[0]) . ';';
    $html .= '</script>';
     */
    return $html;
}
