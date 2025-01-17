<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RC_MIGRATE_SHOPIFY_TO_WORDPRESS_DATA {
	private $params;
	private $default;
	private static $prefix;
	protected $my_options;

	/**
	 * RC_MIGRATE_SHOPIFY_TO_WORDPRESS_DATA constructor.
	 * Init setting
	 */
	public function __construct() {
		self::$prefix = 'rch-';
		global $rch_settings;
		if ( ! $rch_settings ) {
			$rch_settings = get_option( 'rch_params', array() );
		}
		$this->default = array(
			'rch_domain'                      => '',
			'rch_api_key'                     => '',
			'rch_api_secret'                  => '',
			'download_images'                 => '1',
			'product_status'                  => 'publish',
			'product_categories'              => array(),
			'import_categories'               => '1',
			'number'                          => '',
			'validate'                        => '',
			'request_timeout'                 => '600',
			'products_per_request'            => '5',
			'product_import_sequence'         => 'title asc'		
		);

		$this->params = apply_filters( 'rch_params', wp_parse_args( $rch_settings, $this->default ) );
	}

	public function get_params( $name = "" ) {
		if ( ! $name ) {
			return $this->params;
		} elseif ( isset( $this->params[ $name ] ) ) {
			return apply_filters( 'rch_params' . $name, $this->params[ $name ] );
		} else {
			return false;
		}
	}

	public function get_default( $name = "" ) {
		if ( ! $name ) {
			return $this->default;
		} elseif ( isset( $this->default[ $name ] ) ) {
			return apply_filters( 'rch_params_default' . $name, $this->default[ $name ] );
		} else {
			return false;
		}
	}

	public static function set( $name, $set_name = false ) {
		if ( is_array( $name ) ) {
			return implode( ' ', array_map( array( 'RC_MIGRATE_SHOPIFY_TO_WORDPRESS_DATA', 'set' ), $name ) );
		} else {
			if ( $set_name ) {
				return esc_attr__( str_replace( '-', '_', self::$prefix . $name ) );

			} else {
				return esc_attr__( self::$prefix . $name );

			}
		}
	}

	public static function get_option( $option_name, $default = false ) {
		return get_option( $option_name, $default );
	}

	public static function update_option( $option_name, $option_value ) {
		return update_option( $option_name, $option_value );
	}

	public static function delete_option( $option_name ) {
		return delete_option( $option_name );
	}

	/**
	 * @param $files
	 */
	public static function delete_files( $files ) {
		if ( is_array( $files ) ) {
			if ( count( $files ) ) {
				foreach ( $files as $file ) { // iterate files
					if ( is_file( $file ) ) {
						unlink( $file );
					} // delete file
				}
			}
		} elseif ( is_file( $files ) ) {
			unlink( $files );
		}
	}

	public static function deleteDir( $dirPath ) {
		if ( is_dir( $dirPath ) ) {
			if ( substr( $dirPath, strlen( $dirPath ) - 1, 1 ) != '/' ) {
				$dirPath .= '/';
			}
			$files = glob( $dirPath . '*', GLOB_MARK );
			foreach ( $files as $file ) {
				if ( is_dir( $file ) ) {
					self::deleteDir( $file );
				} else {
					unlink( $file );
				}
			}
			rmdir( $dirPath );
		}
	}

	protected static function create_plugin_cache_folder() {
		if ( ! is_dir( RC_MIGRATE_SHOPIFY_TO_WORDPRESS_CACHE ) ) {
			wp_mkdir_p( RC_MIGRATE_SHOPIFY_TO_WORDPRESS_CACHE );
			file_put_contents( RC_MIGRATE_SHOPIFY_TO_WORDPRESS_CACHE . '.htaccess', '<IfModule !mod_authz_core.c>
Order deny,allow
Deny from all
</IfModule>
<IfModule mod_authz_core.c>
  <RequireAll>
    Require all denied
  </RequireAll>
</IfModule>
' );
		}
	}

	public static function create_cache_folder( $path ) {
		self::create_plugin_cache_folder();
		if ( ! is_dir( $path ) ) {
			wp_mkdir_p( $path );
		}
	}

	public static function get_cache_path( $rch_domain, $rch_api_key, $rch_api_secret ) {
		return RC_MIGRATE_SHOPIFY_TO_WORDPRESS_CACHE . md5( $rch_api_key ) . '_' . md5( $rch_api_secret ) . '_' . $rch_domain;
	}

	public static function log( $log_file, $logs_content ) {
		$logs_content = PHP_EOL . "[" . date( "Y-m-d H:i:s" ) . "] " . $logs_content;
		if ( is_file( $log_file ) ) {
			file_put_contents( $log_file, $logs_content, FILE_APPEND );
		} else {
			file_put_contents( $log_file, $logs_content );
		}
	}

	public static function sku_exists( $sku = '' ) {
		global $wpdb;
		$sku_exists = false;
		if ( $sku ) {
			/*Not sure which method is faster
			$id_from_sku = wc_get_product_id_by_sku( $sku );
			$product     = $id_from_sku ? wc_get_product( $id_from_sku ) : false;
			$sku_exists  = $product && 'importing' !== $product->get_status();
			*/
			$table_posts    = "{$wpdb->prefix}posts";
			$table_postmeta = "{$wpdb->prefix}postmeta";
			$query          = "SELECT count(*) from {$table_postmeta} join {$table_posts} on {$table_postmeta}.post_id={$table_posts}.ID where {$table_posts}.post_type in ('product','product_variation') and {$table_posts}.post_status in ('publish','draft','private','pending') and {$table_postmeta}.meta_key = '_sku' and {$table_postmeta}.meta_value = %s";
			$results        = $wpdb->get_var( $wpdb->prepare( $query, $sku ) );
			if ( intval( $results ) > 0 ) {
				$sku_exists = true;
			}
		}

		return $sku_exists;
	}

	/**
	 * @param $shopify_id
	 * @param bool $is_variation
	 * @param bool $count
	 * @param bool $multiple
	 *
	 * @return array|null|object|string
	 */
	public static function product_get_woo_id_by_shopify_id( $shopify_id, $is_variation = false, $count = false, $multiple = false ) {
		global $wpdb;
		if ( $shopify_id ) {
			$table_posts    = "{$wpdb->prefix}posts";
			$table_postmeta = "{$wpdb->prefix}postmeta";
			if ( $is_variation ) {
				$post_type = 'product_variation';
				$meta_key  = '_shopify_variation_id';
			} else {
				$post_type = 'product';
				$meta_key  = '_shopify_product_id';
			}
			if ( $count ) {
				$query   = "SELECT count(*) from {$table_postmeta} join {$table_posts} on {$table_postmeta}.post_id={$table_posts}.ID where {$table_posts}.post_type = '{$post_type}' and {$table_posts}.post_status != 'trash' and {$table_postmeta}.meta_key = '{$meta_key}' and {$table_postmeta}.meta_value = %s";
				$results = $wpdb->get_var( $wpdb->prepare( $query, $shopify_id ) );
			} else {
				$query = "SELECT {$table_postmeta}.* from {$table_postmeta} join {$table_posts} on {$table_postmeta}.post_id={$table_posts}.ID where {$table_posts}.post_type = '{$post_type}' and {$table_posts}.post_status != 'trash' and {$table_postmeta}.meta_key = '{$meta_key}' and {$table_postmeta}.meta_value = %s";
				if ( $multiple ) {
					$results = $wpdb->get_results( $wpdb->prepare( $query, $shopify_id ), ARRAY_A );
				} else {
					$results = $wpdb->get_var( $wpdb->prepare( $query, $shopify_id ), 1 );
				}
			}

			return $results;
		} else {
			return false;
		}
	}

	public static function query_get_id_by_shopify_id( $shopify_id, $type = 'order', $count = false, $multiple = false, $meta_key = '' ) {
		global $wpdb;
		if ( $shopify_id ) {
			$table_posts    = "{$wpdb->prefix}posts";
			$table_postmeta = "{$wpdb->prefix}postmeta";
			switch ( $type ) {
				case 'image':
					$post_type = 'attachment';
					break;
				case 'post':
					$post_type = 'post';
					break;
				case 'coupon':
					$post_type = 'shop_coupon';
					break;
				case 'order':
				default:
					$post_type = 'shop_order';
			}
			if ( ! $meta_key ) {
				$meta_key = "_rch_shopify_{$type}_id";
			}
			if ( $count ) {
				$query   = "SELECT count(*) from {$table_postmeta} join {$table_posts} on {$table_postmeta}.post_id={$table_posts}.ID where {$table_posts}.post_type = '{$post_type}' and {$table_postmeta}.meta_key = '{$meta_key}' and {$table_postmeta}.meta_value = %s";
				$results = $wpdb->get_var( $wpdb->prepare( $query, $shopify_id ) );
			} else {
				$query = "SELECT {$table_postmeta}.* from {$table_postmeta} join {$table_posts} on {$table_postmeta}.post_id={$table_posts}.ID where {$table_posts}.post_type = '{$post_type}' and {$table_postmeta}.meta_key = '{$meta_key}' and {$table_postmeta}.meta_value = %s";
				if ( $multiple ) {
					$results = $wpdb->get_results( $wpdb->prepare( $query, $shopify_id ), ARRAY_A );
				} else {
					$results = $wpdb->get_var( $wpdb->prepare( $query, $shopify_id ), 1 );
				}
			}

			return $results;
		} else {
			return false;
		}
	}

	public static function implode_args( $args ) {
		foreach ( $args as $key => $value ) {
			if ( is_array( $value ) ) {
				$args[ $key ] = implode( ',', $value );
			}
		}

		return $args;
	}

	public static function wp_remote_get( $rch_domain, $rch_api_key, $rch_api_secret, $type = 'products', $count = false, $args = array(), $timeout = 300, $return_pagination_link = false, $version = RC_MIGRATE_SHOPIFY_TO_WORDPRESS_REST_ADMIN_VERSION ) {
		$args = self::implode_args( wp_parse_args( $args, array( 'limit' => 250 ) ) );
		$url  = "https://{$rch_api_key}:{$rch_api_secret}@{$rch_domain}/admin";
		if ( $version ) {
			$url .= "/api/{$version}";
		}
		if ( $count ) {
			$url .= "/{$type}/count.json";
		} else {
			$url .= "/{$type}.json";
		}
		$url     = add_query_arg( $args, $url );
		$request = wp_remote_get(
			$url, array(
				'user-agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/61.0.3163.100 Safari/537.36',
				'timeout'    => $timeout,
				'headers'    => array( 'Authorization' => 'Basic ' . base64_encode( $rch_api_key . ':' . $rch_api_secret ) ),
			)
		);
		$return  = array(
			'status' => 'error',
			'data'   => '',
			'code'   => '',
		);
		if ( ! is_wp_error( $request ) ) {
			if ( $return_pagination_link ) {
				$return['pagination_link'] = self::get_pagination_link( $request );
			}
			$body = json_decode( $request['body'], true, 512, JSON_BIGINT_AS_STRING );
			if ( isset( $body['errors'] ) ) {
				$return['data'] = $body['errors'];
			} else {
				$return['status'] = 'success';
				if ( $count ) {
					$return['data'] = absint( $body['count'] );
				} else {
					if ( ! empty( $args['ids'] ) && ! is_array( $args['ids'] ) ) {
						$return['data'] = isset( $body[ $type ][0] ) ? $body[ $type ][0] : array();
					} else {
						$return['data'] = $body[ $type ];
					}
				}
			}
		} else {
			$return['data'] = $request->get_error_message();
		}

		return $return;
	}

	/**
	 * @param $request
	 *
	 * @return mixed|string
	 */
	public static function get_pagination_link( $request ) {
		$link      = wp_remote_retrieve_header( $request, 'link' );
		$page_link = array( 'previous' => '', 'next' => '' );
		if ( $link ) {
			$links = explode( ',', $link );
			foreach ( $links as $url ) {
				$params = wp_parse_url( $url );
				parse_str( $params['query'], $query );
				if ( ! empty( $query['page_info'] ) ) {
					$query_params = explode( '>;', $query['page_info'] );
					if ( trim( $query_params[1] ) === 'rel="next"' ) {
						$page_link['next'] = $query_params[0];
					} else {
						$page_link['previous'] = $query_params[0];
					}
				}
			}
		}

		return $page_link;
	}

	public static function download_image( &$shopify_id, $url, $post_parent = 0, $exclude = array(), $post_title = '', $desc = null ) {
		$new_url   = $url;
		$parse_url = wp_parse_url( $new_url );
		$scheme    = empty( $parse_url['scheme'] ) ? 'http' : $parse_url['scheme'];
		$image_id  = "{$parse_url['host']}{$parse_url['path']}";
		$new_url   = "{$scheme}://{$image_id}";
		preg_match( '/[^\?]+\.(jpg|JPG|jpeg|JPEG|jpe|JPE|gif|GIF|png|PNG)/', $new_url, $matches );
		if ( ! is_array( $matches ) || ! count( $matches ) ) {
			preg_match( '/[^\?]+\.(jpg|JPG|jpeg|JPEG|jpe|JPE|gif|GIF|png|PNG)/', $url, $matches );
			if ( is_array( $matches ) && count( $matches ) ) {
				$new_url  .= "?{$matches[0]}";
				$image_id .= "?{$matches[0]}";
			}
		}
		if ( ! $shopify_id ) {
			$shopify_id = $image_id;
		}
		$thumb_id = self::query_get_id_by_shopify_id( $shopify_id, 'image' );
		if ( ! $thumb_id ) {
			$thumb_id = rch_upload_image( $new_url, $post_parent, $exclude, $post_title, $desc );
		}

		return $thumb_id;
	}

}

new RC_MIGRATE_SHOPIFY_TO_WORDPRESS_DATA();
