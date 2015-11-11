<?php

add_shortcode('dm','dm_shortcodes');
function dm_shortcodes($scatts){
    global $post;

    extract(shortcode_atts(Array(
        'attr' => NULL,
        'show' => NULL,
        'onarchive' => 'show',
        'vis' => '',
        'popup' => TRUE,
        'callback' => '',
        'marker' => TRUE,
    ),$scatts));

    $cartoObj = dm_makePostCDBOjb();

    if(!empty($attr)){
        $props = $cartoObj->features[0]->properties;
        if(isset($props->{$attr})){
            return '<span class="dapper-attr">' . $props->{$attr} . '</span>';
        }
    }else if(!empty($show) && $show == 'map'){
        $html = '';
        $popup_contents = '<table class="leafletpopup">';
        foreach($cartoObj->features[0]->properties as $k => $v){
            $popup_contents .= '<tr><th>' . $k . '</th><td>' . $v . '</td></tr>';
        }
        $popup_contents .= '</table>';
        $cartoObj->features[0]->popup_contents = $popup_contents;
        $props = $cartoObj->features[0]->properties;
        $html .= '<div class="dm_map_div dm_page_map_div" data-post_id="'.$post->ID.'"></div>';
        return $html;
    }
    return '';
}
