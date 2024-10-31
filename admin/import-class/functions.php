<?php

//Combine fields based on the position of column
function cdis_get_fields( $data, $field_names ){
    $array_of_fields = array();
    foreach( $data as $position => $field ){
        if( in_array($field, $field_names) ){
            $array_of_fields[$field] = $position;
        }
    }
    return $array_of_fields;
}

function cdis_get_editable_roles() {
    global $wp_roles;

    $all_roles = $wp_roles->roles;
    $editable_roles = apply_filters('editable_roles', $all_roles);

    return $editable_roles;
}