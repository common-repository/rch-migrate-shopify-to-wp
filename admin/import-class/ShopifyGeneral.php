<?php


class ShopifyGeneral
{
    public $array_of_field_names;

    public function get_field_names(){
        return $this->array_of_field_names;
    }

    public function combine_fields( $data, $fields, $fields_position, $new_item = true ){
        $return_data = $data;
        foreach( $return_data as $field_key => $field_name ){
            if( array_key_exists( $fields_position[$field_name], $fields ) ) {
                $return_data[$field_key] = $fields[$fields_position[$field_name]];
            }else{
                if( $new_item ) {
                    $return_data[$field_key] = '';
                }else{
                    unset($return_data[$field_key]);
                }
            }
        }
        return $return_data;
    }

	public function update_user_name( $order_data, $user_first_name = 'first_name', $user_last_name = 'last_name' ){
		if( array_key_exists( $user_first_name, $order_data ) && array_key_exists( $user_last_name, $order_data ) ){

			$full_name          = explode( ' ', $order_data[$user_first_name] );
			$full_name_count    = count( $full_name );

			if( $full_name_count > 1 ){
				$order_data[$user_last_name] = end( $full_name );
				array_pop($full_name);
				$order_data[$user_first_name] = implode( ' ', $full_name );
			}else{
				$order_data[$user_first_name]   = $full_name[0];
				$order_data[$user_last_name]    = '';
			}
		}
		return $order_data;
	}

	public function validate_email( $email ){
		if ( !preg_match( "/[-0-9a-zA-Z.+_]+@[-0-9a-zA-Z.+_]+.[a-zA-Z]{2,4}/", $email ) ){
			return false;
		}
		return true;
	}
}