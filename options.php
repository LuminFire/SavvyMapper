<?php

require_once(__DIR__ . '/util.php');

add_action( 'admin_menu', 'dm_add_admin_menu' );
add_action( 'admin_init', 'dm_settings_init' );


function dm_add_admin_menu(  ) { 
    add_menu_page( 'DapperMapper', 'DapperMapper', 'manage_options', 'dappermapper', 'dm_options_page' );
}


function dm_settings_init(  ) { 

    $settings = get_option( 'dm_settings' );

    if(isset($_POST)){
        if(isset($_POST['dm_cdb_table'])){
            $mappings = Array();
            foreach($_POST['dm_cdb_table'] as $i => $cdb_tablename){
                if($cdb_tablename){
                    $mappings[$_POST['dm_post_type'][$i]] = Array('table' => $_POST['dm_cdb_table'][$i], 'lookup' => $_POST['dm_lookup_field'][$i]);
                }
            }
            update_option('dm_table_mapping',$mappings);
        }
    }

    register_setting( 'pluginPage', 'dm_settings' );

    add_settings_section(
        'dm_pluginPage_section', 
        __( 'About', 'wordpress' ), 
        'dm_settings_section_callback', 
        'pluginPage'
    );

    add_settings_field( 
        'dm_cartodb_username', 
        __( 'Your CartoDB Username', 'wordpress' ), 
        'dm_cartodb_username', 
        'pluginPage', 
        'dm_pluginPage_section' 
    );

    add_settings_field( 
        'dm_cartodb_api_key', 
        __( 'Your CartoDB API Key', 'wordpress' ), 
        'dm_cartodb_api_key', 
        'pluginPage', 
        'dm_pluginPage_section' 
    );

    if(isset($settings['dm_cartodb_api_key'])){
        add_settings_section(
            'dm_pluginPage_mapping_table', 
            __( 'Post Type to CartoDB Map', 'wordpress' ), 
            'dm_pluginPage_mapping_table', 
            'pluginPage'
        );
    }
}


function dm_cartodb_api_key(  ) { 

    $settings = get_option( 'dm_settings' );
?>
    <input type='text' name='dm_settings[dm_cartodb_api_key]' value='<?php echo $settings['dm_cartodb_api_key']; ?>'>
<?php

}


function dm_cartodb_username(  ) { 

    $settings = get_option( 'dm_settings' );
?>
    <input type='text' name='dm_settings[dm_cartodb_username]' value='<?php echo $settings['dm_cartodb_username']; ?>'>
<?php

}

function dm_settings_section_callback(  ) { 
    echo "<p>";
    echo "Enter your CartoDB API key below to get started.";
    echo "</p>";
}

function dm_pluginPage_mapping_table(){
    $settings = get_option( 'dm_settings' );
    $mappings = get_option('dm_table_mapping');

    $tables = cartoSQL("SELECT * FROM CDB_UserTables()",FALSE);
    $tables = json_decode($tables);
    $post_types = get_post_types(Array('public'   => true,));
    ksort($post_types);

    print '<table>';
    print '<tr><th>Post Type</th><th>CartoDB Table</th><th>CartoDB Lookup Field</th></tr>';
    foreach($post_types as $post_type){
        $selected = FALSE;
        $lookup_field = '';
        if(isset($mappings[$post_type])){
            $selected = $mappings[$post_type]['table'];
            $lookup_field = $mappings[$post_type]['lookup'];
        }

        // get list of visualization IDs
        // curl 'https://stuporglue.cartodb.com/api/v1/map/named?api_key=asdfasdfasdf' -H 'Content-Type: application/json'

        $cdb_select = makeCDBTableSelect($tables->rows,$selected);
        print '<tr><td><input name="dm_post_type[]" value="' . $post_type . '"></td><td>' . $cdb_select . '</td><td><input type="text" name="dm_lookup_field[]" value="' . $lookup_field . '"></td></tr>';
    }
    print "</table>";
}


function dm_options_page(  ) {

?>
    <form action='options.php' method='post'>

        <h2>DapperMapper</h2>

<?php
    settings_fields( 'pluginPage' );
    do_settings_sections( 'pluginPage' );
    submit_button();
?>

    </form>
<?php

}

function makeCDBTableSelect($opts,$selected = NULL){
    $cdb_select = '<select name="dm_cdb_table[]">';
    $cdb_select .= '<option value="">--</option>';
    foreach($opts as $table){
        $selectedattr = ($table->cdb_usertables == $selected ? 'selected="selected"' : '');
        $cdb_select .= '<option value="' . $table->cdb_usertables . '" ' . $selectedattr . '>' . $table->cdb_usertables . '</option>';
    }
    $cdb_select .= "</select>";

    return $cdb_select;
}

?>
