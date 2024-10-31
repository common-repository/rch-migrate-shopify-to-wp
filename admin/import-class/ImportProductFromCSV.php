<?php


class ImportProductFromCSV
{
    private $plugin_slug;
    private $version;

    function __construct() {
        $this->plugin_slug = 'import-from-csv';
        $this->version = '1.0.1';
        add_action( 'admin_menu', array( $this, 'cdimportfromshopify_page_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_styles' ) );
    }

    function cdimportfromshopify_page_menu() {
		add_management_page(
            'Import From CSV',
            'Import From CSV',
            'manage_options',
            $this->plugin_slug,
            array(
                $this,
                'import_from_shopify_page_content'
            )
        );
    }

    public function enqueue_styles( $hook ) {
        wp_enqueue_style( 'admin-import_shopify', plugin_dir_url( __FILE__ ) . '../css/rch_import_csv', array(), $this->version, 'all' );
    }

    public function admin_notices( $message, $show = true ) {
        if ( !$show ) {
            return;
        }
        ?>
        <div class="updated">
            <p><?php echo $message; ?></p>
        </div>
        <?php
    }

    public function admin_error( $message, $show = true ) {
        if ( !$show ) {
            return;
        }
        ?>
        <div class="error">
            <p><?php echo $message; ?></p>
        </div>
        <?php
    }

    function  import_from_shopify_page_content() {
        ?>
        <h1><?php _e('Import from CSV', $this->plugin_slug) ?></h1>
        <?php
        $def_tab      = 'import_customers';
        $active_tab = isset($_GET['tab']) ? $_GET['tab'] : $def_tab;
        ?>
        <div class="import-shopify-block">
            <h2 class="nav-tab-wrapper">
                <a href="?page=<?php echo $this->plugin_slug; ?>&tab=import_customers" class="nav-tab <?php echo $active_tab == 'import_customers' ? 'nav-tab-active' : ''; ?>"><?php _e('Import Customers', $this->plugin_slug) ?></a>
                <a href="?page=<?php echo $this->plugin_slug; ?>&tab=import_products" class="nav-tab <?php echo $active_tab == 'import_products' ? 'nav-tab-active' : ''; ?>"><?php _e('Import Products', $this->plugin_slug) ?></a>
            </h2>

            <?php

            switch ($active_tab) {
                case 'import_customers':
                    echo $this->get_import_customers_tab();
                break;
                case 'import_products':
                    echo $this->get_import_products_tab();
                break;
                default:
					echo $this->get_import_customers_tab();
            }
            ?>
        </div>
        <?php
    }

    public function get_import_customers_tab(){
        global $ShopifyCustomer;

        $list_of_roles      = cdis_get_editable_roles();
        $roles              = array_keys( $list_of_roles );
        $show_message       = false;
        $show_error         = false;
        $error_message      = '';
        $customers_updated  = 0;

        if( isset( $_POST ) && isset( $_POST['user_role'] ) && isset( $_FILES['customersfile'] )
            && !empty( $_FILES['customersfile']['tmp_name'] ) ) {
            $array_of_field_names = $ShopifyCustomer->get_field_names();
            $array_of_fields = array();

            $row = 0;
            if (($handle = fopen($_FILES['customersfile']['tmp_name'], "r")) !== FALSE) {
                while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                    $row ++;
                    if ( $row == 1 ) {
                        $array_of_fields = cdis_get_fields($data, $array_of_field_names);
                        continue;
                    }

                    //Continue if Email is empty
                    if ( empty( $data[$array_of_fields['Email']] ) || !$ShopifyCustomer->validate_email( $data[$array_of_fields['Email']] ) ) {
                        continue;
                    }

                    if( !email_exists( $data[$array_of_fields['Email']] ) || ( isset( $_POST['user_update_ex'] )
                        && $_POST['user_update_ex'] == 'yes' && !email_exists( $data[$array_of_fields['Email']] ) ) ) {
                        $userdata   = $ShopifyCustomer->prepare_fields( $data, $array_of_fields, $roles );
                        if( $_POST['user_send_msg'] == 'yes' ){
                            $send_notification = true;
                        }else{
                            $send_notification = false;
                        }
                        $user_id            = $ShopifyCustomer->insert_user( $userdata, $send_notification );
						$customers_updated  ++;
                    }elseif( isset( $_POST['user_update_ex'] ) && $_POST['user_update_ex'] == 'yes' ) {
                        $userdata           = $ShopifyCustomer->prepare_fields($data, $array_of_fields, $roles, false);
                        $user_id            = $ShopifyCustomer->update_user($data[$array_of_fields['Email']], $userdata);
						$customers_updated  ++;
                    }

                    if( $user_id ){
                        $show_message = true;
                    }

                }
                fclose($handle);
            }else{
                $show_error = true;
				$error_message  = $this->fileUploadErrorCodeToMessage( 10 );
            }
        }elseif( isset( $_POST ) && isset( $_FILES['customersfile'] ) && isset( $_FILES['customersfile']['error'] )
            && !empty( $_FILES['customersfile']['error'] ) ){
            $show_error     = true;
            $error_message  = $this->fileUploadErrorCodeToMessage($_FILES['customersfile']['error']);
        }elseif( isset( $_POST ) && isset( $_POST['user_role'] ) ){
            $show_error     = true;
            $error_message  = $this->fileUploadErrorCodeToMessage( 4 );
        }
        ?>
        <div class="icustomers-block">
            <form method="POST" enctype="multipart/form-data" accept-charset="utf-8" >
                <table class="icustomers-table">
                    <tr>
                        <td colspan="2">
                            <?php $this->admin_notices(  $customers_updated . __(' Customers were imported', $this->plugin_slug) , $show_message ); ?>
                            <?php $this->admin_error(  $error_message, $show_error ); ?>
                        </td>
                    </tr>
                    <tr>
                        <td><label for="user_role"><?php _e('Role (required)', $this->plugin_slug) ?></label></td>
                        <td>
                            <select name="user_role">
                                <?php foreach( $list_of_roles as $role_slug => $role ){ ?>
                                    <option value="<?php echo $role_slug; ?>" <?php echo ( ( isset( $_POST['user_role'] ) && $_POST['user_role'] == $role_slug ) || ( !isset( $_POST['user_role'] ) && $role_slug == 'customer' ) )? 'selected':''; ?>><?php echo $role['name']; ?></option>
                                <?php } ?>
                            </select>
                            <p class="description"><?php _e('This is role that will defined for new users', $this->plugin_slug) ?></p>
                        </td>
                    </tr>
                    <tr>
                        <td><label for="user_send_msg"><?php _e('Send notification', $this->plugin_slug) ?></label></td>
                        <td>
                            <select name="user_send_msg">
                                <option value="yes"><?php _e('Yes', $this->plugin_slug) ?></option>
                                <option value="no" selected><?php _e('No', $this->plugin_slug) ?></option>
                            </select>
                            <p class="description"><?php _e('RNew user will receive notification with their credentials', $this->plugin_slug) ?></p>
                        </td>
                    </tr>
                    <tr>
                        <td><label for="user_update_ex"><?php _e('Update existing users ?', $this->plugin_slug) ?></label></td>
                        <td>
                            <select name="user_update_ex">
                                <option value="yes"><?php _e('Yes', $this->plugin_slug) ?></option>
                                <option value="no"><?php _e('No', $this->plugin_slug) ?></option>
                            </select>
                            <p class="description"><?php _e('Update user if already exists in WordPress database', $this->plugin_slug) ?></p>
                        </td>
                    </tr>
                    <tr>
                        <td><label for="user_update_ex_role"><?php _e('Update role for existing users ?', $this->plugin_slug) ?></label></td>
                        <td>
                            <select name="user_update_ex_role">
                                <option value="yes"><?php _e('Yes', $this->plugin_slug) ?></option>
                                <option value="no" selected><?php _e('No', $this->plugin_slug) ?></option>
                            </select>
                            <p class="description"><?php _e('Role will update for exists users', $this->plugin_slug) ?></p>
                        </td>
                    </tr>
                    <tr>
                        <td><label for="customersfile"><?php _e('CSV file from Shopify (required)', $this->plugin_slug) ?></label></td>
                        <td>
                            <input type="file" name="customersfile" id="customersfile" size="35" class="customersfile" />
                            <p class="description"><?php _e('Please, upload file exported from Shopify', $this->plugin_slug) ?></p>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="2">
                            <input type="submit" value="<?php _e('Import Customers', $this->plugin_slug) ?>" class="button button-primary button-large">
                        </td>
                    </tr>
                </table>
            </form>
        </div>
        <?php
    }

    public function get_import_products_tab(){
        global $ShopifyProduct;

		$product_data       = array();
        $show_message       = false;
		$show_error         = false;
		$error_message      = '';
		$products_updated  = 0;

		if( isset( $_POST ) && isset( $_FILES['producsfile'] )
			&& !empty( $_FILES['producsfile']['tmp_name'] ) ) {
			$array_of_field_names   = $ShopifyProduct->get_field_names();
			$array_of_fields        = array();
			$slug_list              = array();

			$row = 0;
			if (($handle = fopen($_FILES['producsfile']['tmp_name'], "r")) !== FALSE) {
				while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
					$row ++;
					if ( $row == 1 ) {
						$array_of_fields = cdis_get_fields($data, $array_of_field_names);
						continue;
					}

                    $ShopifyProduct->prepare_fields( $product_data, $data, $array_of_fields );
				}
				fclose($handle);
				$product_cnt = 0;
				foreach( $product_data as $slug => $product ){
					if( isset( $_POST['product_update_ex'] ) && $_POST['product_update_ex'] == 'yes' ){
						$ShopifyProduct->insert_product( $product, false );
                    }else {
						$ShopifyProduct->insert_product( $product );
					}
					$product_cnt ++;
                }
				if( $product_cnt > 0 ){
					$products_updated   = $product_cnt;
					$show_message       = true;
                }
				//var_dump($product_data);
			}else{
				$show_error = true;
				$error_message  = $this->fileUploadErrorCodeToMessage( 10 );
			}
		}elseif( isset( $_POST ) && isset( $_FILES['producsfile'] ) && isset( $_FILES['producsfile']['error'] )
			&& !empty( $_FILES['producsfile']['error'] ) ){
			$show_error     = true;
			$error_message  = $this->fileUploadErrorCodeToMessage($_FILES['producsfile']['error']);
		}elseif( isset( $_POST ) && isset( $_FILES['producsfile'] ) ){
			$show_error     = true;
			$error_message  = $this->fileUploadErrorCodeToMessage( 4 );
		}
		?>
        <div class="iproducts-block">
            <form method="POST" enctype="multipart/form-data" accept-charset="utf-8" >
                <table class="iproducts-table">
                    <tr>
                        <td colspan="2">
							<?php $this->admin_notices(  $products_updated . __(' Products were imported', $this->plugin_slug), $show_message ); ?>
							<?php $this->admin_error(  $error_message, $show_error ); ?>
                        </td>
                    </tr>
                    <tr>
                        <td><label for="customersfile"><?php _e('CSV file from Shopify (required)', $this->plugin_slug) ?></label></td>
                        <td>
                            <input type="file" name="producsfile" id="producsfile" size="35" class="producsfile" />
                            <p class="description"><?php _e('Please, upload file exported from Shopify', $this->plugin_slug) ?></p>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="2">
                            <input type="submit" value="<?php _e('Import Products', $this->plugin_slug) ?>" class="button button-primary button-large">
                        </td>
                    </tr>
                </table>
            </form>
        </div>
		<?php
    }

    private function fileUploadErrorCodeToMessage($code)
    {
        switch ($code) {
            case UPLOAD_ERR_INI_SIZE:
                $message = "The uploaded file exceeds the upload_max_filesize directive in php.ini";
                break;
            case UPLOAD_ERR_FORM_SIZE:
                $message = "The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form";
                break;
            case UPLOAD_ERR_PARTIAL:
                $message = "The uploaded file was only partially uploaded";
                break;
            case UPLOAD_ERR_NO_FILE:
                $message = "No file was uploaded";
                break;
            case UPLOAD_ERR_NO_TMP_DIR:
                $message = "Missing a temporary folder";
                break;
            case UPLOAD_ERR_CANT_WRITE:
                $message = "Failed to write file to disk";
                break;
            case UPLOAD_ERR_EXTENSION:
                $message = "File upload stopped by extension";
                break;
            case 10:
				$message = "File can't be read";
				break;

            default:
                $message = "Unknown upload error";
                break;
        }
        return __($message, $this->plugin_slug);
    }
}

new ImportProductFromCSV;