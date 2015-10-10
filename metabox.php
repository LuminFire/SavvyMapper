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

add_action( 'save_post', 'dm_save_meta');

add_action( 'add_meta_boxes', 'dm_add_meta_box' );

function dm_make_meta_box($post,$metabox){
    $mappings = get_option('dm_table_mapping');
    $target_table = $mappings[$post->post_type]['table'];
    $lookup_field = $mappings[$post->post_type]['lookup'];

    $cartodb_id = get_post_meta($post->ID,'cartodb_lookup_value',TRUE);
    $cartodb_label = get_post_meta($post->ID,'cartodb_lookup_label',TRUE);

    print '<div class="db_lookupbox" data-table="'. $target_table .'" data-lookup="'. $lookup_field .'">';
        print '<label>Look up ' . $lookup_field . ': </label><input class="db_lookup_ac" name="cartodb_lookup_label" value="' . $cartodb_label . '">';
        print '<input type="hidden" name="cartodb_lookup_value" value="' . $cartodb_id . '">';
        wp_nonce_field( 'dm_meta_box', 'dm_meta_box_nonce' );
        print '<div class="dm_lookup_meta">';
        print '</div>';
    print '</div>';

    print '<div class="dm_map_div"></div>';
}

function dm_save_meta($post_id){
    // Check if our nonce is set.
    if(!isset($_POST['dm_meta_box_nonce'])){
        return;
    }

    // Verify that the nonce is valid.
    if(!wp_verify_nonce($_POST['dm_meta_box_nonce'],'dm_meta_box')){
        return;
    }

    // If this is an autosave, our form has not been submitted, so we don't want to do anything.
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }

    // Check the user's permissions.
    if ( isset( $_POST['post_type'] ) && 'page' == $_POST['post_type'] ) {
        if ( ! current_user_can( 'edit_page', $post_id ) ) {
            return;
        }
    } else {
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }
    }

    $cartodb_id = sanitize_text_field($_POST['cartodb_lookup_value']);
    $cartodb_label = sanitize_text_field($_POST['cartodb_lookup_label']);

    update_post_meta($post_id,'cartodb_lookup_value',$cartodb_id);
    update_post_meta($post_id,'cartodb_lookup_label',$cartodb_label);
}
