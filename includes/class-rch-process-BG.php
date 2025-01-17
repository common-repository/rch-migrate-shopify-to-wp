<?php

class WP_MIGRATE_SHOPIFY_TO_WORDPRESS_Process_New extends WP_Background_Process {

	/**
	 * @var string
	 */
	protected $action = 'rch_process_new';

	/**
	 * Task
	 *
	 * Override this method to perform any actions required on each
	 * queue item. Return the modified item for further processing
	 * in the next pass through. Or, return false to remove the
	 * item from the queue.
	 *
	 * @param mixed $item Queue item to iterate over
	 *
	 * @return mixed
	 */
	protected function task( $item ) {
		$product_id  = isset( $item['parent_id'] ) ? $item['parent_id'] : '';
		$set_gallery = isset( $item['set_gallery'] ) ? $item['set_gallery'] : '';
		$product_ids = isset( $item['product_ids'] ) ? $item['product_ids'] : array();
		$src         = isset( $item['src'] ) ? $item['src'] : '';
		$id          = isset( $item['id'] ) ? $item['id'] : '';
		$alt         = isset( $item['alt'] ) ? $item['alt'] : '';
		try {
			if ( $product_id && $src ) {
				rc_rch_set_time_limit();
				$thumb_id = RC_MIGRATE_SHOPIFY_TO_WORDPRESS_DATA::download_image( $id, $src, $product_id );
				if ( $thumb_id && ! is_wp_error( $thumb_id ) ) {
					update_post_meta( $thumb_id, '_rch_shopify_image_id', $id );
					if ( $alt ) {
						update_post_meta( $thumb_id, '_wp_attachment_image_alt', $alt );
					}
					if ( count( $product_ids ) ) {
						foreach ( $product_ids as $v_id ) {
							if ( in_array( get_post_type( $v_id ), array(
								'product',
								'product_variation'
							) ) ) {
								update_post_meta( $v_id, '_thumbnail_id', $thumb_id );
							}
						}
					}

					if ( 1 == $set_gallery ) {
						$gallery = get_post_meta( $product_id, '_product_image_gallery', true );
						if ( $gallery ) {
							$gallery_array = explode( ',', $gallery );
						} else {
							$gallery_array = array();
						}
						$gallery_array[] = $thumb_id;
						update_post_meta( $product_id, '_product_image_gallery', implode( ',', array_unique( $gallery_array ) ) );
					}
				} else {
					error_log( 'RCH error log - background download images: ' . $thumb_id->get_error_code() . ' - ' . $thumb_id->get_error_message() );
				}
			}

		} catch ( Exception $e ) {
			error_log( 'RCH error log - background download images: ' . $e->getMessage() );

			return false;
		}

		return false;
	}

	/**
	 * Is the updater running?
	 *
	 * @return boolean
	 */
	public function is_downloading() {
		return parent::is_process_running();
	}

	/**
	 * Complete
	 *
	 * Override if applicable, but ensure that the below actions are
	 * performed, or, call parent::complete().
	 */
	protected function complete() {
		if ( ! $this->is_downloading() ) {
			set_transient( 'rch_background_processing_complete', time() );
		}
		// Show notice to user or perform some other arbitrary task...
		parent::complete();
	}

	/**
	 * Delete all batches.
	 *
	 * @return WP_MIGRATE_SHOPIFY_TO_WORDPRESS_Process_New
	 */
	public function delete_all_batches() {
		global $wpdb;

		$table  = $wpdb->options;
		$column = 'option_name';

		if ( is_multisite() ) {
			$table  = $wpdb->sitemeta;
			$column = 'meta_key';
		}

		$key = $wpdb->esc_like( $this->identifier . '_batch_' ) . '%';

		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE {$column} LIKE %s", $key ) ); // @codingStandardsIgnoreLine.

		return $this;
	}

	/**
	 * Is queue empty
	 *
	 * @return bool
	 */
	public function is_queue_empty() {
		return parent::is_queue_empty();
	}
}