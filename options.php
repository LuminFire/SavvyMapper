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

    if(empty($settings['dm_cartodb_api_key'])){
        add_settings_section(
            'dm_pluginPage_section', 
            __( 'Getting Started', 'wordpress' ), 
            'dm_getting_started_callback', 
            'pluginPage'
        );
    }

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

    if(!empty($settings['dm_cartodb_api_key'])){

        add_settings_section(
            'dm_pluginPage_section', 
            __( 'Instructions', 'wordpress' ), 
            'dm_instructions_callback', 
            'pluginPage'
        );

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

function dm_getting_started_callback() { 
    echo "<p>";
    echo "Enter your CartoDB Username and API key below and click &ldquo;" . __('Save Changes','wordpress') . "&rdquo; to get started.";
    echo "</p>";
    echo "<p><a href='http://docs.cartodb.com/cartodb-platform/sql-api.html#api-key'>Need help finding your key?</a></p>";
}

function dm_instructions_callback(){

    echo "<p>For any post type you would like to associate with a CartoDB table, select the CartoDB table name from the dropdown,</p>";
    echo "<p>blah blah blah</p>";
}

function dm_pluginPage_mapping_table(){
    $settings = get_option( 'dm_settings' );
    $mappings = get_option('dm_table_mapping');

    $tables_raw = dm_cartoSQL("SELECT * FROM CDB_UserTables()",FALSE);
    $tables_raw = json_decode($tables_raw);

    $tables = Array();
    foreach($tables_raw->rows as $table){
        $tables[$table->cdb_usertables] = Array();
    }

    $columns = dm_cartoSQL("select table_name,column_name from information_schema.columns where table_name IN ('" . implode("','",array_keys($tables)) . "')",FALSE);
    $columns = json_decode($columns);
    foreach($columns->rows as $column){
        $tables[$column->table_name][] = $column->column_name;
    }

    $cdb_tables_and_columns = Array();
    foreach($tables as $table => $fields){
        $cdb_tables_and_columns[$table] = makeCDBFieldSelect($tables,$table);
    }
    $cdb_tables_and_columns['dm_empty_select_list'] = makeCDBFieldSelect($tables);
    print '<script type="text/javascript">var cdb_tables_and_columns = ' . json_encode($cdb_tables_and_columns) . ';</script>';

    $post_types = get_post_types(Array('public' => true,));
    ksort($post_types);

    print '<table>';
    print '<tr><th>Post Type</th><th>CartoDB Table</th><th>CartoDB Lookup Field</th></tr>';
    foreach($post_types as $post_type){
        $post_type_object = get_post_type_object($post_type);
        $selected = FALSE;
        $lookup_field = '';

        if(isset($mappings[$post_type])){
            $selected = $mappings[$post_type]['table'];
            $lookup_field = $mappings[$post_type]['lookup'];
        }

        print '<tr><td><input type="hidden" name="dm_post_type[]" value="' . $post_type . '">'. $post_type_object->labels->singular_name .'</td>';

        $cdb_select = dm_makeCDBTableSelect($tables,$selected);
        print '<td>' . $cdb_select . '</td>';
        
        $cdb_field_select = makeCDBFieldSelect($tables,$selected,$lookup_field);
        print '<td class="dm_cdb_field_select_td">'.$cdb_field_select.'</td></tr>';
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

function dm_makeCDBTableSelect($opts,$selected = NULL){
    $cdb_select = '<select name="dm_cdb_table[]" class="dm_cdb_table_select">';
    $cdb_select .= '<option value="">--</option>';
    foreach($opts as $table => $fields){
        $selectedattr = ($table == $selected ? 'selected="selected"' : '');
        $cdb_select .= '<option value="' . $table . '" ' . $selectedattr . '>' . $table . '</option>';
    }
    $cdb_select .= "</select>";

    return $cdb_select;
}

function makeCDBFieldSelect($tables,$tablename,$selected = NULL){
    $cdb_select = '<select name="dm_lookup_field[]" class="dm_cdb_field_select">';
    $cdb_select .= '<option value="">--</option>';
    foreach($tables[$tablename] as $field){
        $selectedattr = ($field == $selected ? 'selected="selected"' : '');
        $cdb_select .= '<option value="' . $field . '" ' . $selectedattr . '>' . $field . '</option>';
    }
    $cdb_select .= "</select>";

    return $cdb_select;
}

?>
