<?php

// Customizations to the archive page
add_action('loop_start','make_archive_map_maybe');
function make_archive_map_maybe($query){
    if(!is_archive()){
        return;
    }
    if( $query->is_main_query() ){

        $mappings = get_option('dm_table_mapping');
        $post_type = get_post_type();
        if(isset($mappings[$post_type])){
            print '<article class="hentry archivemapwrap"><div class="dm_map_div dm_archive_map" data-table="'.$mappings[$post_type]['table'].'" data-post_type="'.$post_type.'"></div></article>';
        }
    }
}
