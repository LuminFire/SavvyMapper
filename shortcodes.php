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
        return $props->{$atts[0]};
    }
    return '';
}
