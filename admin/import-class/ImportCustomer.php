<?php


class ImportCustomer extends ShopifyGeneral
{
    private $wp_user_fields;
    private $wp_user_billing_fields;
    private $wp_user_shipping_fields;

    function __construct() {
        $this->array_of_field_names = array(
            'First Name',
            'Last Name',
            'Email',
            'Company',
            'Address1',
            'Address2',
            'City',
            'Province',
            'Province Code',
            'Country',
            'Country Code',
            'Zip',
            'Phone'
        );

        //Default fields
        $this->wp_user_fields = array(
            'user_pass'     => '',
            'user_login'    => 'Email',
            'user_nicename' => 'Email',
            'user_url'      => '',
            'user_email'    => 'Email',
            'display_name'  => 'Email',
            'nickname'      => 'Email',
            'first_name'    => 'First Name',
            'last_name'     => 'Last Name',
            'description'   => '',
            'role'          => ''
        );
        //Billing fields
        $this->wp_user_billing_fields = array(
            'billing_first_name'    => 'First Name',
            'billing_last_name'     => 'Last Name',
            'billing_company'       => 'Company',
            'billing_address_1'     => 'Address1',
            'billing_address_2'     => 'Address2',
            'billing_city'          => 'City',
            'billing_postcode'      => 'Zip',
            'billing_country'       => 'Country Code',
            'billing_state'         => 'Province Code',
            'billing_phone'         => 'Phone',
            'billing_email'         => 'Email'
        );
        //Shipping fields
        $this->wp_user_shipping_fields = array(
            'shipping_first_name'   => 'First Name',
            'shipping_last_name'    => 'Last Name',
            'shipping_company'      => 'Company',
            'shipping_address_1'    => 'Address1',
            'shipping_address_2'    => 'Address2',
            'shipping_city'         => 'City',
            'shipping_postcode'     => 'Zip',
            'shipping_country'      => 'Country Code',
            'shipping_state'        => 'Province Code'
        );
    }

    public function prepare_fields( $fields, $fields_position, $roles, $new_item = true, $split_name = false ){
        $user_data = $this->combine_fields( $this->wp_user_fields, $fields, $fields_position, $new_item );

        //Generate new password
        if( $new_item ) {
            $user_data['user_pass'] = wp_generate_password();
        }

        //Check if selected role and if exists role
        if( isset( $_POST['user_role'] )  && in_array( $_POST['user_role'], $roles )
            && ( $new_item || !$new_item && $_POST['user_update_ex_role'] == 'yes' ) ) {
            $user_data['role'] = $_POST['user_role'];
        }elseif( !$new_item && $_POST['user_update_ex_role'] == 'no' ){
            unset($user_data['role']);
        }

        if( $split_name ){
			$user_data = $this->update_user_name( $user_data );
		}

        $user_meta = array_merge( $this->wp_user_billing_fields, $this->wp_user_shipping_fields );
        foreach( $user_meta as $meta_key => $meta_value ){
            if( array_key_exists( $fields_position[$meta_value], $fields ) ) {
                $user_meta[$meta_key] = $fields[$fields_position[$meta_value]];
            }else{
                if( $new_item ) {
                    $user_meta[$meta_key] = '';
                }else{
                    unset($user_meta[$meta_key]);
                }
            }
        }
		if( $split_name ){
			$user_meta = $this->update_user_name( $user_meta, 'billing_first_name', 'billing_last_name' );
			$user_meta = $this->update_user_name( $user_meta, 'shipping_first_name', 'shipping_last_name' );
		}
        return array( 'user_data' => $user_data, 'user_meta' => $user_meta );
    }

    public function insert_user( $user_data, $send_notification = false ){
        $user_id = wp_insert_user( $user_data['user_data'] ) ;

        if ( ! is_wp_error( $user_id ) ) {
            foreach( $user_data['user_meta'] as $meta_key => $user_meta ){
                update_user_meta( $user_id, $meta_key, $user_meta);
            }
            if( $send_notification ){
                wp_new_user_notification( $user_id, null, 'user' );
            }
            return $user_id;
        }else{
            return false;
        }
    }

    public function update_user( $user_email, $user_data ){
        $user = get_user_by( 'email', $user_email );

        if( isset($user)  && isset($user->ID) ) {
            $user_data['user_data']['ID'] = $user->ID;
            $user_id = wp_update_user($user_data['user_data']);

            if ( !is_wp_error( $user_id ) ) {
                foreach ($user_data['user_meta'] as $meta_key => $user_meta) {
                    update_user_meta( $user_id, $meta_key, $user_meta );
                }
                clean_user_cache( $user_id );
                return $user_id;
            } else {
                return false;
            }
        }else{
            return false;
        }
    }

	public function set_user_fields( $first_name = 'First Name', $last_name = 'Last Name', $email = 'Email' ){
		$data_values = array(
			'user_login'    => $email,
			'user_nicename' => $email,
			'user_email'    => $email,
			'display_name'  => $email,
			'nickname'      => $email,
			'first_name'    => $first_name,
			'last_name'     => $last_name
		);
		$this->wp_user_fields = array_merge( $this->wp_user_fields, $data_values );
	}

	public function set_user_billing_fields( $first_name = 'First Name', $last_name = 'Last Name',
											 $company = 'Company', $address_1 = 'Address1', $address_2 = 'Address2',
											 $city = 'City', $postcode = 'Zip', $country = 'Country Code',
											 $state = 'Province Code', $phone = 'Phone', $email = 'Email' ){
		$data_values = array(
			'billing_first_name'    => $first_name,
			'billing_last_name'     => $last_name,
			'billing_company'       => $company,
			'billing_address_1'     => $address_1,
			'billing_address_2'     => $address_2,
			'billing_city'          => $city,
			'billing_postcode'      => $postcode,
			'billing_country'       => $country,
			'billing_state'         => $state,
			'billing_phone'         => $phone,
			'billing_email'         => $email
		);
		$this->wp_user_billing_fields = array_merge( $this->wp_user_billing_fields, $data_values );
	}

	public function set_user_shipping_fields( $first_name = 'First Name', $last_name = 'Last Name',
											 $company = 'Company', $address_1 = 'Address1', $address_2 = 'Address2',
											 $city = 'City', $postcode = 'Zip', $country = 'Country Code',
											 $state = 'Province Code' ){
		$data_values = array(
			'shipping_first_name'   => $first_name,
			'shipping_last_name'    => $last_name,
			'shipping_company'      => $company,
			'shipping_address_1'    => $address_1,
			'shipping_address_2'    => $address_2,
			'shipping_city'         => $city,
			'shipping_postcode'     => $postcode,
			'shipping_country'      => $country,
			'shipping_state'        => $state
		);
		$this->wp_user_shipping_fields = array_merge( $this->wp_user_shipping_fields, $data_values );
	}
}