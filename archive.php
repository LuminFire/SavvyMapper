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
            print '<article class="hentry archivemapwrap"><div class="dm_map_div dm_archive_map" data-archive_type="'.$post_type.'"></div></article>';
        }
    }
}
