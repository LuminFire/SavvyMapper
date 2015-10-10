<?php

function dm_add_meta_box(){
    global $post;

    $mappings = get_option('dm_table_mapping');
    $options = get_option( 'dm_settings' );

    if(isset($mappings[$post->post_type])){
        add_meta_box(
            'dm_meta_box',
            'CartoDB Connection',
            'dm_make_meta_box'
        );
    }
}

add_action( 'add_meta_boxes', 'dm_add_meta_box' );

function dm_make_meta_box($post,$metabox){
    $mappings = get_option('dm_table_mapping');
    $target_table = $mappings[$post->post_type]['table'];
    $lookup_field = $mappings[$post->post_type]['lookup'];

    print '<div class="db_lookupbox" data-table="'. $target_table .'" data-lookup="'. $lookup_field .'">';
        print '<label>Look up ' . $lookup_field . ': </label><input class="db_lookup_ac" name="cartodb_lookup_label">';
        print '<input type="hidden" name="cartodb_lookup_value">';
        print '<div class="db_lookup_meta">';
        print '</div>';
    print '</div>';

    print '<div class="dm_map_div"></div>';
}
