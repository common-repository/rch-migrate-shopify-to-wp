<?php


class ImportProducts extends ShopifyGeneral
{
	public $product_data;
	function __construct()
	{
		$this->array_of_field_names	= array(
			'Handle',
			'Title',
			'Body (HTML)',
			'Vendor',
			'Tags',
			'Published',
			'Option1 Name',
			'Option1 Value',
			'Option2 Name',
			'Option2 Value',
			'Option3 Name',
			'Option3 Value',
			'Variant SKU',
			'Variant Grams',
			'Variant Inventory Qty',
			'Variant Price',
			'Variant Compare At Price',
			'Variant Taxable',
			'Image Src',
			'Variant Image'
		);
		$this->product_data			= array(
			'slug'			=> 'Handle',
			'title'			=> 'Title',
			'published'		=> 'Published',
			'description'	=> 'Body (HTML)',
			'sku'			=> 'Variant SKU',
			'price'			=> 'Variant Price',
			'regular_price' => 'Variant Compare At Price',
			'qty'			=> 'Variant Inventory Qty',
			'image'			=> 'Image Src'
		);
	}

	public function prepare_fields( &$prepared_products, $fields, $fields_position, $new_item = true ){
		$attr_data					= array();
		$product_data				= $this->combine_fields( $this->product_data, $fields, $fields_position, $new_item );

		//Set attributes
		if( array_key_exists( $product_data['slug'], $prepared_products ) ){
			for( $i = 1; $i < 4; $i ++ ) {
				if (!empty($fields[$fields_position['Option' . $i . ' Value']])) {
					$option_key = ( isset( $prepared_products[$product_data['slug']]['attribute_name']['Option' . $i . ' Name'] ) )?
						key($prepared_products[$product_data['slug']]['attribute_name']['Option' . $i . ' Name']):'';
					$attr_name	= ( !empty( $fields[$fields_position['Option' . $i . ' Name']] ) )?
						$fields[$fields_position['Option' . $i . ' Name']]:$option_key;
					$attr_data[] = array(
						'name'		=> $attr_name,
						'options'	=> $fields[$fields_position['Option' . $i . ' Value']]
					);
					if( isset( $prepared_products[$product_data['slug']]['attribute_name']['Option' . $i . ' Name'] ) &&
					is_array( $prepared_products[$product_data['slug']]['attribute_name']['Option' . $i . ' Name'] ) &&
						array_key_exists( $option_key, $prepared_products[$product_data['slug']]['attribute_name']['Option' . $i . ' Name'] ) ){
						$prepared_products[$product_data['slug']]['attribute_name']['Option' . $i . ' Name'][$option_key][] = $fields[$fields_position['Option' . $i . ' Value']];
					}
				}
			}
			$prepared_products[$product_data['slug']]['type']			= 'variable';
		}else{
			$prepared_products[$product_data['slug']]					= $product_data;
			$prepared_products[$product_data['slug']]['attributes']		= array();
			$prepared_products[$product_data['slug']]['attribute_name']	= array();

			for( $i = 1; $i < 4; $i ++ ) {
				if (!empty($fields[$fields_position['Option' . $i . ' Value']])) {
					$attr_data[] = array(
						'name'		=> $fields[$fields_position['Option' . $i . ' Name']],
						'options'	=> $fields[$fields_position['Option' . $i . ' Value']]
					);
					$prepared_products[$product_data['slug']]['attribute_name']['Option' . $i . ' Name'] = array(
						$fields[$fields_position['Option' . $i . ' Name']] => array( $fields[$fields_position['Option' . $i . ' Value']] )
					);
				}
			}
			//Set instock
			$prepared_products[$product_data['slug']]['instock']	= ( intval( $product_data['qty'] ) > 0 )? 'instock':'outofstock';
			$prepared_products[$product_data['slug']]['published']	= ( $product_data['published'] == 'true' )? 'publish':'draft';
			$prepared_products[$product_data['slug']]['type']		= 'simple';
		}

		if( !empty( $attr_data ) ) {
			$prepared_products[$product_data['slug']]['attributes'][] = array_merge( $product_data, array( 'attributes' => $attr_data ) );
		}
	}

	public function insert_product( $product_data, $new_item = true ){
		/*
		$product_id = wc_get_product_id_by_sku( $product_data['sku'] );
		if( $product_id && $new_item ){
			return false;
		}elseif( $new_item ){
			if( $product_data['type'] == 'variable' ){
				$product = new WC_Product_Variable();
			}else{
				$product = new WC_Product();
			}
		}else{
			$product = wc_get_product( $product_id );
		}
		*/

		if( $product_data['type'] == 'variable' ){
			$product = new WC_Product_Variable();
		}else{
			$product = new WC_Product();
		}
		$product->set_name( $product_data['title'] );
		$product->set_status( $product_data['published'] );
		$product->set_catalog_visibility( 'visible' );
		$product->set_description( $product_data['description'] );
		if( $product_data['type'] == 'simple' ) {
			$product->set_sku($product_data['sku']);
			$product->set_price($product_data['price']);
			$product->set_regular_price($product_data['regular_price']);
			$product->set_manage_stock(true);
			$product->set_stock_quantity($product_data['qty']);
			$product->set_stock_status($product_data['instock']); // in stock or out of stock value
		}
		$product->set_backorders('no');
		$product->set_reviews_allowed(true);
		$product->set_sold_individually(false);

		if( !empty( $product_data['image'] ) ) {
			$image = $this->upload_media( $product_data['image'] );
			$product->set_image_id( $image );
		}

		if( $product_data['type'] == 'variable' ) {
			$att_array = array();

			//Save Attributes
			foreach( $product_data['attribute_name'] as $product_attributes ){
				$option_name	= key( $product_attributes );
				$slug			= sanitize_title($option_name);
				if(!get_taxonomy("pa_" . $slug)) {
					$result = wc_create_attribute(array(
						"name" => $option_name,
						"slug" => $slug,
						"type" => "select",
					));
				}

				foreach( $product_attributes[$option_name] as $value ){
					if( !term_exists( $value, wc_attribute_taxonomy_name($option_name) ) ){
						wp_insert_term( $value, wc_attribute_taxonomy_name( $option_name ), array( 'slug' => sanitize_title( $value ) ) );
					}
				}

				$attribute = new WC_Product_Attribute();
				$attribute->set_id( 0 );
				$attribute->set_name( $option_name );
				$attribute->set_options( $product_attributes[$option_name] );
				$attribute->set_position( 0 );
				$attribute->set_visible( true );
				$attribute->set_variation( true );
				$att_array[] = $attribute;
			}
			$product->set_attributes( $att_array );
			$product_id = $product->save();

			foreach( $product_data['attributes'] as $product_variant ){
				$variation_id = wc_get_product_id_by_sku( $product_variant['sku'] );
				if( $variation_id && $new_item ){
					continue;
				}

				if( $new_item ) {
					$variation_post = array(
						'post_title' => $product->get_title(),
						'post_name' => 'product-' . $product_id . '-variation',
						'post_status' => 'publish',
						'post_parent' => $product_id,
						'post_type' => 'product_variation',
						'guid' => $product->get_permalink()
					);
					// Creating the product variation
					$variation_id = wp_insert_post( $variation_post );
				}

				$variation = new WC_Product_Variation( $variation_id );
				$variation->set_regular_price((empty($product_variant['regular_price']))? $product_variant['price']:$product_variant['regular_price']);
				$variation->set_sku($product_variant['sku']);
				$variation->set_sale_price($product_variant['price']);
				$variation->set_stock_quantity($product_variant['qty']);
				$variation->set_manage_stock(True);
				$variation->set_parent_id($product_id);

				if( !empty( $product_variant['image'] ) ) {
					$image = $this->upload_media( $product_variant['image'] );
					$variation->set_image_id( $image );
				}

				$var_attrs = array();
				foreach( $product_variant['attributes'] as $variant_attr ){
					$slug				= sanitize_title($variant_attr['name']);
					$var_attrs[$slug]	= $variant_attr['options'];
					$term_slug = get_term_by('name', $variant_attr['options'], $variant_attr['name'])->slug;

					// Get the post Terms names from the parent variable product.
					$post_term_names = (array) wp_get_post_terms($product_id, "pa_" . $slug, array('fields' => 'names'));
					// Check if the post term exist and if not we set it in the parent variable product.
					if (!in_array($variant_attr['options'], $post_term_names))
						wp_set_post_terms($product_id, $variant_attr['options'], $variant_attr['name'], true);

					// Set/save the attribute data in the product variation
					update_post_meta($variation_id, 'attribute_' . $slug, $term_slug);
				}

				$variation->set_attributes($var_attrs);
				$variation_id = $variation->save();
			}
		}else{
			$product_id = $product->save();
		}

		return $product_id;
	}

	private function upload_media( $image_url ){
		require_once(ABSPATH . 'wp-admin/includes/image.php');
		require_once(ABSPATH . 'wp-admin/includes/file.php');
		require_once(ABSPATH . 'wp-admin/includes/media.php');
		$media = media_sideload_image( $image_url, 0, null, 'id' );
		return $media;
	}

}