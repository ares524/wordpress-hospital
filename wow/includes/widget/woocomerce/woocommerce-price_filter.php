<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

/**
 * Widget Woocomemrce Ajax Price Filter List
 *
 * @package wow
 * @author Chrom Themes
 * @link http://chromthemes.com
 * @version 2.0
 *  Note: This is a modified version of the "WooCommerce Price Filer" widget
 */
class CHfw_WC_Widget_Price_Filter extends WC_Widget {

	/* ---------------------------------------------------------------------------
	 * INIT
	 * --------------------------------------------------------------------------- */
	public function __construct() {
		$this->widget_cssclass    = 'ch_widget_price_filter';
		$this->widget_description = __( 'Shows a price filter list in a widget which lets you narrow down the list of shown products when viewing product categories.', 'woocommerce' );
		$this->widget_id          = 'ch_woocommerce_price_filter';
		$this->widget_name        = __( 'CH WooCommerce Ajax Price Filter List', 'chfw-lang' );
		$this->settings           = array(
			'title'             => array(
				'type'  => 'text',
				'std'   => __( 'Filter by price', 'woocommerce' ),
				'label' => __( 'Title', 'woocommerce' )
			),
			'price_range_size'  => array(
				'type'  => 'number',
				'step'  => 1,
				'min'   => 1,
				'max'   => '',
				'std'   => 50,
				'label' => __( 'Price range size', 'chfw-lang' )
			),
			'max_price_ranges'  => array(
				'type'  => 'number',
				'step'  => 1,
				'min'   => 1,
				'max'   => '',
				'std'   => 10,
				'label' => __( 'Max price ranges', 'chfw-lang' )
			),
			'hide_empty_ranges' => array(
				'type'  => 'checkbox',
				'std'   => 1,
				'label' => __( 'Hide empty price ranges', 'chfw-lang' )
			)
		);

		parent::__construct();
	}

	/**
	 * widget function.
	 *
	 * @see WP_Widget
	 *
	 * @param array $args
	 * @param array $instance
	 */
	public function widget( $args, $instance ) {
		global $wp, $wp_the_query;

		extract( $args );

		if ( ! is_post_type_archive( 'product' ) && ! is_tax( get_object_taxonomies( 'product' ) )
		) {
			return;
		}

		if ( ! $wp_the_query->post_count ) {
			return;
		}

		$min_price = isset( $_GET['min_price'] ) ? esc_attr( $_GET['min_price'] ) : '';
		$max_price = isset( $_GET['max_price'] ) ? esc_attr( $_GET['max_price'] ) : '';


		//wp_enqueue_script( 'wc-price-slider' );


		$title = apply_filters( 'widget_title', $instance['title'], $instance, $this->id_base );

		// Remember current filters/search

		//$fields = '';


		// (copied from line 201)
		if ( get_option( 'permalink_structure' ) == '' ) {
			$link = remove_query_arg( array(
				'page',
				'paged'
			), add_query_arg( $wp->query_string, '', esc_url(home_url( $wp->request ) ) ));
		} else {
			$link = preg_replace( '%\/page/[0-9]+%', '', esc_url(home_url( $wp->request )) );
		}


		if ( get_search_query() ) {

			//$fields .= '<input type="hidden" name="s" value="' . get_search_query() . '" />';
			$link = add_query_arg( 's', get_search_query(), $link );

		}

		if ( ! empty( $_GET['post_type'] ) ) {
			//$fields .= '<input type="hidden" name="post_type" value="' . esc_attr( $_GET['post_type'] ) . '" />';
			$link = add_query_arg( 'post_type', esc_attr( $_GET['post_type'] ), $link );

		}

		if ( ! empty ( $_GET['product_cat'] ) ) {
			//$fields .= '<input type="hidden" name="product_cat" value="' . esc_attr( $_GET['product_cat'] ) . '" />';
			$link = add_query_arg( 'product_cat', esc_attr( $_GET['product_cat'] ), $link );

		}

		if ( ! empty( $_GET['product_tag'] ) ) {
			//$fields .= '<input type="hidden" name="product_tag" value="' . esc_attr( $_GET['product_tag'] ) . '" />';
			$link = add_query_arg( 'product_tag', esc_attr( $_GET['product_tag'] ), $link );
		}

		if ( ! empty( $_GET['orderby'] ) ) {
			//$fields .= '<input type="hidden" name="orderby" value="' . esc_attr( $_GET['orderby'] ) . '" />';
			$link = add_query_arg( 'orderby', esc_attr( $_GET['orderby'] ), $link );
		}

		if ( ! empty( $_GET['min_rating'] ) ) {
			//$fields .= '<input type="hidden" name="min_rating" value="' . esc_attr( $_GET['min_rating'] ) . '" />';
			$link = add_query_arg( 'min_rating', esc_attr( $_GET['min_rating'] ), $link );
		}

		//if ( $_chosen_attributes ) {
		if ( $_chosen_attributes = WC_Query::get_layered_nav_chosen_attributes() ) {
			foreach ( $_chosen_attributes as $attribute => $data ) {
				$taxonomy_filter = 'filter_' . str_replace( 'pa_', '', $attribute );

				//$fields .= '<input type="hidden" name="' . esc_attr( $taxonomy_filter ) . '" value="' . esc_attr( implode( ',', $data['terms'] ) ) . '" />';
				$link = add_query_arg( esc_attr( $taxonomy_filter ), esc_attr( implode( ',', $data['terms'] ) ), $link );

				if ( 'or' == $data['query_type'] ) {
					//$fields .= '<input type="hidden" name="' . esc_attr( str_replace( 'pa_', 'query_type_', $attribute ) ) . '" value="or" />';
					$link = add_query_arg( esc_attr( str_replace( 'pa_', 'query_type_', $attribute ) ), 'or', $link );
				}
			}
		}

		// Find min and max price in current result set
		$prices = $this->get_filtered_price();
		$min    = floor( $prices->min_price );
		$max    = ceil( $prices->max_price );

		if ( $min == $max ) {
			return;
		}

		echo $before_widget . $before_title . $title . $after_title;

		/*if ( get_option( 'permalink_structure' ) == '' ) {
			$form_action = remove_query_arg( array( 'page', 'paged' ), add_query_arg( $wp->query_string, '', home_url( $wp->request ) ) );
		} else {
			$form_action = preg_replace( '%\/page/[0-9]+%', '', home_url( $wp->request ) );
		}*/

		/**
		 * Adjust max if the store taxes are not displayed how they are stored.
		 * Min is left alone because the product may not be taxable.
		 * Kicks in when prices excluding tax are displayed including tax.
		 */
		if ( wc_tax_enabled() && 'incl' === get_option( 'woocommerce_tax_display_shop' ) && ! wc_prices_include_tax() ) {
			$tax_classes = array_merge( array( '' ), WC_Tax::get_tax_classes() );
			$class_max   = $max;

			foreach ( $tax_classes as $tax_class ) {
				if ( $tax_rates = WC_Tax::get_rates( $tax_class ) ) {
					$class_max = $max + WC_Tax::get_tax_total( WC_Tax::calc_exclusive_tax( $max, $tax_rates ) );
				}
			}

			$max = $class_max;
		}



		// Apply WooCommerce filters on min and max prices (required for updating currency-switcher prices)
		$min            = apply_filters( 'woocommerce_price_filter_widget_min_amount', $min );
		$max_unfiltered = $max;
		$max            = apply_filters( 'woocommerce_price_filter_widget_max_amount', $max );

		$count = 0;
		// If the filtered max-price (see above) is different from the original price (currency-switcher used) - apply "woocommerce_price_filter_widget_max_amount" filter to adapt price-range to the different prices
		if ( $max_unfiltered != $max ) {
			$range_size = round( apply_filters( 'woocommerce_price_filter_widget_max_amount', intval( $instance['price_range_size'] ) ), 0 );
			$range_size = apply_filters( 'price_filter_range_size', $range_size ); // Requested: Make range-size filterable (can be useful when prices vary)
		} else {
			$range_size = intval( $instance['price_range_size'] );
		}
		$max_ranges = ( intval( $instance['max_price_ranges'] ) - 1 );

		$output = '<ul class="price-filter">';

		if ( strlen( $min_price ) > 0 ) {
			$output .= '<li><a href="' . esc_url( $link ) . '">' . esc_html__( 'All', 'chfw-lang' ) . '</a></li>';
		} else {
			$output .= '<li class="current">' . esc_html__( 'All', 'chfw-lang' ) . '</li>';
		}

		for ( $range_min = 0; $range_min < ( $max + $range_size ); $range_min += $range_size ) {
			$range_max = $range_min + $range_size;

			// Hide empty price ranges?
			if ( intval( $instance['hide_empty_ranges'] ) ) {
				// Are there products in this price range?
				if ( $min > $range_max || ( $max + $range_size ) < $range_max ) {
					continue;
				}
			}

			$count ++;

			$min_price_output = wc_price( $range_min );

			if ( $count == $max_ranges ) {
				$price_output = $min_price_output . '+';

				if ( $range_min != $min_price ) {
					$url = add_query_arg( array( 'min_price' => $range_min, 'max_price' => $max ), $link );
					$output .= '<li><a href="' . esc_url( $url ) . '">' . $price_output . '</a></li>';
				} else {
					$output .= '<li class="current">' . $price_output . '</li>';
				}

				break; // Max price ranges limit reached, break loop
			} else {
				$price_output = $min_price_output . ' - ' . wc_price( $range_min + $range_size );

				if ( $range_min != $min_price || $range_max != $max_price ) {
					$url = add_query_arg( array( 'min_price' => $range_min, 'max_price' => $range_max ), $link );
					$output .= '<li><a href="' . esc_url( $url ) . '">' . $price_output . '</a></li>';
				} else {
					$output .= '<li class="current">' . $price_output . '</li>';
				}
			}
		}

		echo $output . '</ul>';

		echo $after_widget;
	}

	/**
	 * Get filtered min price for current products.
	 * @return int
	 */
	protected function get_filtered_price() {
		global $wpdb, $wp_the_query;

		$args       = $wp_the_query->query_vars;
		$tax_query  = isset( $args['tax_query'] ) ? $args['tax_query'] : array();
		$meta_query = isset( $args['meta_query'] ) ? $args['meta_query'] : array();

		if ( ! empty( $args['taxonomy'] ) && ! empty( $args['term'] ) ) {
			$tax_query[] = array(
				'taxonomy' => $args['taxonomy'],
				'terms'    => array( $args['term'] ),
				'field'    => 'slug',
			);
		}

		foreach ( $meta_query as $key => $query ) {
			if ( ! empty( $query['price_filter'] ) || ! empty( $query['rating_filter'] ) ) {
				unset( $meta_query[ $key ] );
			}
		}

		$meta_query = new WP_Meta_Query( $meta_query );
		$tax_query  = new WP_Tax_Query( $tax_query );

		$meta_query_sql = $meta_query->get_sql( 'post', $wpdb->posts, 'ID' );
		$tax_query_sql  = $tax_query->get_sql( $wpdb->posts, 'ID' );

		$sql = "SELECT min( CAST( price_meta.meta_value AS UNSIGNED ) ) as min_price, max( CAST( price_meta.meta_value AS UNSIGNED ) ) as max_price FROM {$wpdb->posts} ";
		$sql .= " LEFT JOIN {$wpdb->postmeta} as price_meta ON {$wpdb->posts}.ID = price_meta.post_id " . $tax_query_sql['join'] . $meta_query_sql['join'];
		$sql .= " 	WHERE {$wpdb->posts}.post_type = 'product'
					AND {$wpdb->posts}.post_status = 'publish'
					AND price_meta.meta_key IN ('" . implode( "','", array_map( 'esc_sql', apply_filters( 'woocommerce_price_filter_meta_keys', array( '_price' ) ) ) ) . "')
					AND price_meta.meta_value > '' ";
		$sql .= $tax_query_sql['where'] . $meta_query_sql['where'];

		return $wpdb->get_row( $sql );
	}
}