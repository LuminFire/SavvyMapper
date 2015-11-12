<?php

// Customizations to the archive page
add_action('loop_start','dm_make_archive_map_maybe');
function dm_make_archive_map_maybe($query){
    if(!is_archive()){
        return;
    }
    if( $query->is_main_query() ){

        $mappings = get_option('dm_table_mapping');
        $post_type = get_post_type();

        if(isset($mappings[$post_type])){
            $mappings = get_option('dm_table_mapping');

            $vizes = explode("\n",$mappings[$post_type]['visualizations']);
            $vizes = array_filter($vizes);
            $visualizations = implode(',',$vizes);

            $show_markers = ($mappings[$post_type]['show_markers'] === 'checked' ? 'true' : 'false');

            $html = '<article class="hentry archivemapwrap">';
            $html .= '<div class="dm_map_div dm_archive_map" ';
            $html .= 'data-archive_type="'.$post_type.'" ';
            $html .= 'data-vizes="' . $visualizations . '" '; 
            $html .= 'data-marker="' . $show_markers . '" ';
            $html .= '></div>';
            $html .= '</article>';

            print $html;
        }
    }
}
