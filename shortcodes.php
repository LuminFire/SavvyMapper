<?php

add_shortcode('dm','dm_shortcodes');
function dm_shortcodes($scatts){
    global $post;

    extract(shortcode_atts(Array(
        'attr' => NULL,
        'show' => NULL,
        'onarchive' => 'show',
        'vizes' => NULL,
        'popup' => 'true',
        'marker' => NULL,
        'zoom' => 'default',
        'lat' => 'default',
        'lng' => 'default',
    ),$scatts));

    // If we're supposed to hide the map on archive pages, bail early.
    if(strtolower($onarchive) == 'hide'){
        if(is_archive()){
            return '';
        }
    }

    $cartoObj = dm_makePostCDBOjb();

    if(!empty($attr)){
        $props = $cartoObj->features[0]->properties;
        if(isset($props->{$attr})){
            return '<span class="dapper-attr">' . $props->{$attr} . '</span>';
        }
    }else if(!empty($show) && $show == 'map'){
        $mappings = get_option('dm_table_mapping');

        // merge vizes
        if(strtolower($vizes) === 'false'){
            $visualizations = '';
        } else {
            $vizes = explode(',',$vizes);
            $vizes = array_merge(explode("\n",$mappings[$post->post_type]['visualizations']),$vizes);
            $vizes = array_filter($vizes);
            $visualizations = implode(',',$vizes);
        }

        // show markers or not?
        if(is_null($maker)){
            $show_markers = ($mapping[$post->post_type]['show_markers'] === 'checked' ? 'true' : 'false');
        }else{
            $show_markers = (strtolower($marker) == 'true');
        }

        $html = '';
        $popup_contents = '<table class="leafletpopup">';
        foreach($cartoObj->features[0]->properties as $k => $v){
            $popup_contents .= '<tr><th>' . $k . '</th><td>' . $v . '</td></tr>';
        }
        $popup_contents .= '</table>';
        $cartoObj->features[0]->popup_contents = $popup_contents;
        $props = $cartoObj->features[0]->properties;
        $html .= '<div class="dm_map_div dm_page_map_div" ';
        $html .= 'data-post_id="'.$post->ID.'" ';
        $html .= 'data-vizes="' . $visualizations . '" '; 
        $html .= 'data-marker="' . $show_markers . '" ';
        $html .= 'data-lat="' . $lat . '" ';
        $html .= 'data-lng="' . $lng . '" ';
        $html .= 'data-zoom="' . $zoom. '" ';
        $html .= 'data-popup="' . $popup . '" ';
        $html .= '></div>';
        return $html;
    }
    return '';
}
