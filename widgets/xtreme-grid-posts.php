<?php

add_filter('xtreme-collect-widget-classes', create_function('$classes', '$classes[] = "Xtreme_Grid_Posts_Widget"; return $classes;'));

class Xtreme_Grid_Posts_Widget extends Xtreme_Widget_Base {

	/**
	 * Array of available post types
	 * 
	 * @since 09/26/2013
	 */
	public $post_types = array();
	
	public function __construct() {
		
		$widget_ops = array(
			'classname' => 'xtreme_grid_posts',
			'description' => __( 'This Widget will display your posts in x columns and x rows.', XF_TEXTDOMAIN )
		);
		
		parent::__construct(__FILE__, 'xtreme-grid-posts', __( 'Xtreme Grid Posts', XF_TEXTDOMAIN ), $widget_ops );

		$this->classes = xtreme_get_grids();
			
		/* @since 09/26/2013 */
		$args = apply_filters( 'xtreme-widget-column-posts-types', array( 'public' => TRUE, 'show_in_nav_menus' => TRUE ) );
		$this->post_types = get_post_types( $args, 'objects', 'and' );
	}

	public function widget( $args, $instance ) {
		global $wpdb;
		
		extract( $args );

		$title = apply_filters( 'widget_title', empty( $instance['title'] ) ? '' : $instance['title'] );
		if ( $instance['number_posts'] < 1 ) $instance['number_posts'] = 1;
		/* @since 09/26/2013 */
		$post_type = isset( $instance['post_type'] ) ? esc_attr( $instance['post_type'] ) : 'post';
		$columns = absint($instance['columns']);
		$rows = absint( $instance['rows'] );
		$thumbnail_size = esc_attr( $instance['thumbnail_size'] );
		if ( ! isset( $instance['as_backgroundimage'] ) ) $instance['as_backgroundimage'] = 0;
		if ( ! isset( $instance['wrap_content'] ) ) $instance['wrap_content'] = 1;
		$el = 'div';
		$html5 = xtreme_is_html5();
		if ( $html5 ) {
			$el = 'article';
		}
		/* since 1.01 */
		$contentorder = array(
			'content'   => isset( $instance['content_pos'] ) ? (int) $instance['content_pos'] : 4,
			'byline'    => isset( $instance['byline_pos'] ) ? (int) $instance['byline_pos'] : 3,
			'subtitle'  => isset( $instance['subtitle_pos'] ) ? (int) $instance['subtitle_pos'] : 3,
			'posttitle' => isset( $instance['posttitle_pos'] ) ? (int) $instance['posttitle_pos'] : 2,
			'thumbnail' => isset( $instance['image_pos'] ) ? (int) $instance['image_pos'] : 1
		);
		$suppress_posttitle_link = isset( $instance['suppress_posttitle_link']) ? (bool)($instance['suppress_posttitle_link'] == 1) : false;

		$tag = isset ( $instance['posttitle_tag'] ) ? esc_attr( $instance['posttitle_tag'] ) : 'h2';
		$subtag = isset ( $instance['subtitle_tag'] ) ? esc_attr( $instance['subtitle_tag'] ) : 'h3';
		
		if ( ! isset( $instance['content_pos'] ) ) {
			$instance['show_posttitle'] = 1;
			$instance['show_content'] = 1;
			$instance['sticky'] = 0;
		}
		
		if ( ! isset( $instance['show_subtitle'] ) ) $instance['show_subtitle'] = 0;

		asort( $contentorder, SORT_NUMERIC );
		foreach ( $contentorder as $key => $value ) {
			if ( $instance['show_' . $key] ) {
				$new_sort[] = $key;
			}
		}
		
		/* @since 09/26/2013 */
		$tax_names = get_object_taxonomies( $post_type );
		if ( ! empty( $tax_names ) && in_array( 'category', $tax_names ) )
			$category = $instance['category'];
		else
			$category = '';

		$query_args = array(
			'cat'              => $instance['category'],
			'showposts'        => (int) $instance['number_posts'],
			'offset'           => (int) $instance['offset'],
			'nopaging'         => 0,
			'post_type'        => $post_type,
			'post_status'      => 'publish',
			XF_STICKY_HANDLING => (bool) $instance['sticky'],
			'orderby'          => esc_attr( $instance['orderby'] ),
			'order'            => esc_attr( $instance['order'] )
		);

		/**
		 * exclude the current post_id on is_singular()
		 * @since 1.6.3
		 * @link https://github.com/inpsyde/xtreme-one/issues/184
		 */
		if( is_singular() ){
			$id = get_the_ID();
			if( !!$id ) {
				$query_args[ 'post__not_in' ] = array( $id );
			}
		}

		$r = new WP_Query( $query_args );
		if ( $r->have_posts() ):
			$i = 0;
			$count = $r->post_count;
			echo $before_widget;
			if ( $title ) echo $before_title . $title . $after_title;
			
			while ( $r->have_posts() ) : $r->the_post();
				echo $this->html_before( $columns, $i );
				$i++;
				 ?>
				<<?php echo $el ?> <?php post_class() ?>>
				<?php
				 if ( isset( $new_sort ) ) {
					$c = count( $new_sort );
					for ( $y = 0; $y < $c; $y++ ) {
						switch ( $new_sort[$y] ) {
							case 'thumbnail':
								if ( $instance['as_backgroundimage'] == 0 ) {
									xtreme_post_thumbnail( $thumbnail_size, esc_attr( $instance['image_alignment'] ) );
								} else {
									xtreme_background_post_thumbnail( $thumbnail_size, esc_attr( $instance['image_alignment'] ), esc_html( $instance['excerpt_morelink_text'] ) );
								}
								break;

							case 'subtitle':
								xtreme_widget_post_subtitle( $subtag );
								break;

							case 'posttitle':
								if ( $html5 ) echo '<header>';
								xtreme_widget_post_headline( $tag, ! $suppress_posttitle_link );
								if ( $html5 && $new_sort[$y+1]  !== 'byline' )
									echo '</header>';
								break;

							case 'byline':
								if ( !$html5 ) {
									xtreme_byline();
								} else {
									xtreme_html5_byline();
									if ($y !== 0) {
										if ( $new_sort[$y-1] == 'posttitle') echo '</header>';
									}
								}
								break;

							case 'content':
								if ( $instance['wrap_content'] == 1 ) {
									echo '<div class="entry-content">';
								}
								switch( $instance['content_type'] ) {
									case 'xtreme_excerpt':
										$excerpt_length     = esc_attr( $instance['excerpt_length'] );
										$excerpt_more_text  = esc_html( $instance['excerpt_morelink_text'] );
										$excerpt_more       = esc_html( $instance['excerpt_more'] );
										$excerpt_show_tags = isset( $instance['excerpt_show_tags'] );
										xtreme_excerpt( $excerpt_length, $excerpt_more_text, $excerpt_more, true, $excerpt_show_tags );
										break;
									case 'excerpt':
										the_excerpt();
										echo " <p><span class='read-more'><a href='" . get_permalink() . "' title='" . __( 'Read more about', XF_TEXTDOMAIN ) . ' ' . esc_attr( get_the_title() ) . "'>" . esc_html( $instance['excerpt_morelink_text'] ) . "</a></span></p>";
										break;
									case 'content':
										the_content();
										break;
								}
								if ( $instance['wrap_content'] == 1 ) {
									echo '</div>';
								}
								break;
						}
					}
					echo '</' . $el . '>';
					echo $this->html_after( $columns, $i );
				}
			endwhile;
			
			wp_reset_query();
			
			if ( $count < (int) $instance['number_posts'] ) {
				while( $count < (int) $instance['number_posts'] ) {
					$count++;
					echo $this->html_before( $columns, $count );
					echo "&nbsp;";
					echo $this->html_after( $columns, $count );
				}
			}
			
			echo $after_widget;
			
		endif;
	}

	public function update( $new_instance, $old_instance ) {
		
		$instance = $old_instance;
		$instance['title']                   = strip_tags( $new_instance['title'] );
		/* @since 09/26/2013 */
		$instance['post_type']               = strip_tags( $new_instance['post_type'] );
		$instance['columns']                 = ( int ) $new_instance['columns'];
		$instance['rows']                    = ( int ) $new_instance['rows'];
		$instance['numberposts']             = ( int ) $new_instance['numberposts'];
		$instance['category']                = intval( $new_instance['category'] );
		$instance['orderby']                 = strip_tags( $new_instance['orderby'] );
		$instance['order']                   = strip_tags( $new_instance['order'] );
		$instance['offset']                  = absint( strip_tags( $new_instance['offset'] ) );
		$instance['content_type']            = strip_tags( $new_instance['content_type'] );
		$instance['show_thumbnail']          = isset( $new_instance['show_thumbnail'] ) ? 1 : 0;
		$instance['as_backgroundimage']      = isset( $new_instance['as_backgroundimage'] ) ? 1 : 0;
		$instance['thumbnail_size']          = strip_tags( $new_instance['thumbnail_size'] );
		$instance['image_alignment']         = strip_tags( $new_instance['image_alignment'] );
		$instance['image_pos']               = intval( strip_tags( $new_instance['image_pos'] ) );
		$instance['byline_pos']              = intval( strip_tags( $new_instance['byline_pos'] ) );
		$instance['excerpt_length']          = absint( strip_tags( $new_instance['excerpt_length'] ) );
		$instance['excerpt_more']            = strip_tags( $new_instance['excerpt_more'] );
		$instance['excerpt_morelink_text']   = strip_tags( $new_instance['excerpt_morelink_text'] );
		$instance['excerpt_show_tags']       = isset( $new_instance['excerpt_show_tags'] ) ? $instance['excerpt_show_tags'] : 0;

		$instance['show_byline']             = isset( $new_instance['show_byline'] ) ? 1 : 0;
		$instance['show_posttitle']          = isset( $new_instance['show_posttitle'] ) ? 1 : 0;
		$instance['posttitle_pos']           = intval( strip_tags( $new_instance['posttitle_pos'] ) );
		$instance['posttitle_tag']           = strip_tags( $new_instance['posttitle_tag'] );
		$instance['suppress_posttitle_link'] =  isset( $new_instance['suppress_posttitle_link']) ? $new_instance['suppress_posttitle_link'] : 0;
		if( current_theme_supports( 'xtreme-subtitles' ) ) {
			$instance['show_subtitle'] = isset( $new_instance['show_subtitle'] ) ? 1 : 0;
			$instance['subtitle_pos']  = intval( strip_tags($new_instance['subtitle_pos'] ) );
			$instance['subtitle_tag']  = strip_tags( $new_instance['subtitle_tag'] );
		}
		$instance['show_content'] = isset( $new_instance['show_content'] ) ? 1 : 0;
		$instance['wrap_content'] = isset( $new_instance['wrap_content'] ) ? 1 : 0;
		$instance['content_pos']  = intval( strip_tags( $new_instance['content_pos'] ) );
		$instance['sticky']       = isset( $new_instance['sticky'] ) ? 1 : 0;
		
		// if empty, then use the old rule raw * column
		if ( empty( $new_instance['numberposts'] ) )
			$instance['number_posts'] = ( int )( $new_instance['columns'] * $new_instance['rows'] );
		else
			$instance['number_posts'] = ( int ) $new_instance['numberposts'];
				
		if ( $instance['sticky'] ) {
			$instance['number_posts'] = $instance['number_posts'];
		} else {
			$st = count( get_option('sticky_posts') );
			$instance['number_posts'] = $instance['number_posts'] - $st;
		}
		
		return $instance;
	}

	public function form( $instance ) {
		
		$title                   = isset( $instance['title'] ) ? esc_attr( $instance['title'] ) : '';
		/* @since 09/26/2013 */
		$post_type               = isset( $instance['post_type'] ) ? $instance['post_type'] : 'post';
		$columns                 = isset( $instance['columns'] ) ? absint( $instance['columns'] ) : 1;
		$rows                    = isset( $instance['rows'] ) ? absint( $instance['rows'] ) : 1;
		$numberposts             = isset( $instance['numberposts'] ) ? absint( $instance['numberposts'] ) : '';
		$category                = isset( $instance['category'] ) ? $instance['category'] : 0;
		$orderby                 = isset( $instance['orderby'] ) ? $instance['orderby'] : 'date';
		$order                   = isset( $instance['order'] ) ? $instance['order'] : 'DESC';
		$offset                  = ( isset( $instance['offset'] ) && ! empty( $instance['offset'] ) ) ? $instance['offset'] : 0;
		$content_type            = isset( $instance['content_type'] ) ? $instance['content_type'] : 'xtreme_excerpt';
		$show_posttitle          = isset( $instance['show_posttitle'] ) ? $instance['show_posttitle'] : 1;
		$posttitle_pos           = isset( $instance['posttitle_pos'] ) ? $instance['posttitle_pos'] : 2;
		$posttitle_tag           = isset( $instance['posttitle_tag'] ) ? $instance['posttitle_tag'] : 'h2';
		$suppress_posttitle_link =  isset( $instance['suppress_posttitle_link']) ? $instance['suppress_posttitle_link'] : 0;
		if ( current_theme_supports( 'xtreme-subtitles' ) ) {
			$show_subtitle = isset( $instance['show_subtitle'] ) ? $instance['show_subtitle'] : 0;
			$subtitle_pos  = isset( $instance['subtitle_pos'] ) ? $instance['subtitle_pos'] : 3;
			$subtitle_tag  = isset( $instance['subtitle_tag'] ) ? $instance['subtitle_tag'] : 'h3';
		}
		$show_byline           = isset( $instance['show_byline'] ) ? $instance['show_byline'] : 0;
		$byline_pos            = isset( $instance['byline_pos'] ) ? $instance['byline_pos'] : 3;
		$show_content          = isset( $instance['show_content'] ) ? $instance['show_content'] : 1;
		$wrap_content          = isset( $instance['wrap_content'] ) ? $instance['wrap_content'] : 1;
		$content_pos           = isset( $instance['content_pos'] ) ? $instance['content_pos'] : 4;
		$show_thumbnail        = isset( $instance['show_thumbnail'] ) ? $instance['show_thumbnail'] : 0;
		$as_backgroundimage    = isset( $instance['as_backgroundimage'] ) ? $instance['as_backgroundimage'] : 0;
		$thumbnail_size        = isset( $instance['thumbnail_size'] ) ? $instance['thumbnail_size'] : 'thumbnail';
		$image_alignment       = isset( $instance['image_alignment'] ) ? $instance['image_alignment'] : 'alignleft';
		$image_pos             = isset( $instance['image_pos'] ) ? $instance['image_pos'] : 1;
		$excerpt_length        = isset( $instance['excerpt_length'] ) ? min(max(5, $instance['excerpt_length']), 80) : 40;
		$excerpt_more          = isset( $instance['excerpt_more'] ) ? $instance['excerpt_more'] : '...';
		$excerpt_morelink_text = isset( $instance['excerpt_morelink_text'] ) ? $instance['excerpt_morelink_text'] : __( 'Read more...', XF_TEXTDOMAIN );
		$excerpt_show_tags     = isset( $instance['excerpt_show_tags'] ) ? $instance['excerpt_show_tags'] : 0;

		$sticky                = isset( $instance['sticky']) ? $instance['sticky'] : 0;
		if( $sticky ) {
			$number_posts = ( int )( $columns * $rows );
		} else {
			$st = count( get_option( 'sticky_posts ' ) );
			$number_posts = ( int )( $columns * $rows - $st );
		}
		?>
		<p>
			<label for="<?php echo $this->get_field_id( 'title' ) ?>"><?php _e( 'Title (or leave it blank):', XF_TEXTDOMAIN ) ?></label>
			<input class="widefat" id="<?php echo $this->get_field_id( 'title' ) ?>" name="<?php echo $this->get_field_name( 'title' ) ?>" type="text" value="<?php echo esc_html( $title ) ?>" />
		</p>
		<h3><?php _e( 'Post Options', XF_TEXTDOMAIN ) ?></h3>
		<?php /* @since 09/26/2013 */ ?>
		<div class="<?php echo $this->get_field_id( 'post_type' ) ?>">
			<p>
				<label for="<?php echo $this->get_field_id( 'post_type' ) ?>"><?php _e( 'Post Type:', XF_TEXTDOMAIN ) ?></label>
				<select id="<?php echo $this->get_field_id( 'post_type' ) ?>" name="<?php echo $this->get_field_name( 'post_type' ) ?>">
			<?php
			foreach ( $this->post_types as $type => $post_type_object ) {
				
				$name = esc_attr( ucfirst( $post_type_object->labels->name ) );
				$singular_name = ucfirst( $post_type_object->labels->singular_name );
				
			?>
				<option value="<?php echo $type; ?>" <?php selected( $type, $post_type ) ?>><?php echo $name; ?></option>
			<?php
			}
			?>
				</select>
			</p>
		</div>
		
		<p>
			<label for="<?php echo $this->get_field_id( 'columns' ) ?>"><?php _e( 'Columns:', XF_TEXTDOMAIN ); ?></label>
			<select id="<?php echo $this->get_field_id( 'columns' ) ?>" name="<?php echo $this->get_field_name( 'columns' ) ?>" >
			<?php $arr = array( 1,2,3,4,5 );
			foreach ( $arr as $v ) :
			?>
				<option value="<?php echo $v ?>" <?php selected( $v, $columns ) ?>><?php echo $v ?></option>
			<?php endforeach;
			unset( $arr );?>
			</select>
		</p>
		<p>
			<label for="<?php echo $this->get_field_id( 'rows' ) ?>"><?php _e('Rows:', XF_TEXTDOMAIN ) ?></label>
			<select id="<?php echo $this->get_field_id( 'rows' ) ?>" name="<?php echo $this->get_field_name( 'rows' ) ?>">
			<?php $arr = array( 1,2,3,4,5,6 );
			foreach ( $arr as $v ) :
			?>
				<option value="<?php echo $v ?>" <?php selected( $v, $rows ) ?>><?php echo $v ?></option>
			<?php endforeach;
			unset( $arr );?>
			</select>
		</p>
		<p>
			<label for="<?php echo $this->get_field_id( 'numberposts' ) ?>"><?php _e( 'Number:', XF_TEXTDOMAIN ); ?></label>
			<input id="<?php echo $this->get_field_id( 'numberposts' ) ?>" name="<?php 
			echo $this->get_field_name( 'numberposts' ) ?>" type="text" value="<?php 
			echo $numberposts; ?>" size="3" /> <?php _e( 'Posts', XF_TEXTDOMAIN ) ?>
			<br />
			<small><?php esc_attr_e( 'Leave empty fo the number of posts is columns * rows', XF_TEXTDOMAIN ) ?></small>
		</p>
		
		<input type="hidden" id="<?php echo $this->get_field_id( 'number_posts' ) ?>" name="<?php echo $this->get_field_name( 'number_posts' ) ?>" value="" />
		<p>
			<input id="<?php echo $this->get_field_id( 'sticky' ) ?>" type="checkbox" name="<?php echo $this->get_field_name( 'sticky' ) ?>" value="1" <?php checked( 1, $sticky ) ?>/>
			<label for="<?php echo $this->get_field_id( 'sticky' ) ?>"><?php _e( 'Ignore Sticky Posts', XF_TEXTDOMAIN ) ?></label>
		</p>
		<?php
		/* @since 09/26/2013 */
		$tax_names = get_object_taxonomies( $post_type );
		if ( ! empty( $tax_names ) && in_array( 'category', $tax_names ) ) {
		?>
		<p>
			<label for="<?php echo $this->get_field_id( 'category' ) ?>"><?php _e( 'Category:', XF_TEXTDOMAIN ) ?></label>
			<?php wp_dropdown_categories(
				array(
					'name'            => $this->get_field_name( 'category' ),
					'selected'        => $category,
					'orderby'         => 'name' ,
					'hierarchical'    => 1,
					'show_option_all' => __( 'All Categories', XF_TEXTDOMAIN ),
					'hide_empty'      => 1
					)
			) ?>
		</p>
		<?php
		}
		
		$sort = array(
			'date'          => __( 'Post Date', XF_TEXTDOMAIN ),
			'title'         => __( 'Post Title', XF_TEXTDOMAIN ),
			'ID'            => __( 'Post ID', XF_TEXTDOMAIN ),
			'rand'          => __( 'Random', XF_TEXTDOMAIN ),
			'comment_count' => __( 'Comment Count', XF_TEXTDOMAIN )
		) ?>
		<p>
			<label for="<?php echo $this->get_field_id( 'orderby' ) ?>"><?php _e( 'Order By:', XF_TEXTDOMAIN ) ?></label>
			<select id="<?php echo $this->get_field_id( 'orderby' ) ?>" name="<?php echo $this->get_field_name( 'orderby' ) ?>">
			<?php foreach( $sort as $key => $val ): ?>
				<option value="<?php echo $key ?>" <?php selected( $key, $orderby ) ?>><?php echo esc_attr( $val ) ?></option>
			<?php endforeach ?>
			</select>
		</p>

		<?php
		$the_orders = array(
			'DESC'  => __( 'lowest to highest values', XF_TEXTDOMAIN ),
			'ASC'   => __( 'highest to lowest values', XF_TEXTDOMAIN ),
		)
		?>
		<p>
			<label for="<?php echo $this->get_field_id( 'order' ) ?>"><?php _e( 'Order from:', XF_TEXTDOMAIN ) ?></label>
			<select id="<?php echo $this->get_field_id( 'order' ) ?>" name="<?php echo $this->get_field_name( 'order' ) ?>">
				<?php foreach( $the_orders as $key => $val ): ?>
					<option value="<?php echo $key ?>" <?php selected( $key, $order ) ?>><?php echo esc_attr( $val ) ?></option>
				<?php endforeach ?>
			</select>
		</p>


		<p>
			<label for="<?php echo $this->get_field_id( 'offset' ) ?>"><?php _e( 'Post Offset:', XF_TEXTDOMAIN ) ?></label>
			<input type="text" id="<?php echo $this->get_field_id( 'offset ') ?>" name="<?php echo $this->get_field_name( 'offset' ) ?>" value="<?php echo esc_attr($offset) ?>" size="3" />
		</p>
		<h3><?php _e( 'Post Template Options', XF_TEXTDOMAIN ) ?></h3>
		<p>
			<input class="x-switcher" id="<?php echo $this->get_field_id('show_thumbnail') ?>" type="checkbox" name="<?php echo $this->get_field_name('show_thumbnail') ?>" value="1" <?php checked(1, $show_thumbnail) ?>/>
			<label for="<?php echo $this->get_field_id( 'show_thumbnail' ) ?>"><?php _e( 'Show Featured Image', XF_TEXTDOMAIN ) ?></label>
		</p>
		<div class="<?php echo $this->get_field_id( 'show_thumbnail' ) ?>">
			<p>
				<input id="<?php echo $this->get_field_id('as_backgroundimage') ?>" type="checkbox" name="<?php echo $this->get_field_name('as_backgroundimage') ?>" value="1" <?php checked(1, $as_backgroundimage) ?>/>
				<label for="<?php echo $this->get_field_id( 'as_backgroundimage' ) ?>"><?php _e( 'Show as Background Image', XF_TEXTDOMAIN ) ?></label>
			</p>
			<p>
				<label for="<?php echo $this->get_field_id( 'thumbnail_size' ) ?>"><?php _e( 'Size:', XF_TEXTDOMAIN ) ?></label>
				<select class="x_thumbnailsize" id="<?php echo $this->get_field_id( 'thumbnail_size' ) ?>" name="<?php echo $this->get_field_name( 'thumbnail_size' ) ?>">
				<?php global $_wp_additional_image_sizes;
					$sizes = get_intermediate_image_sizes();
					foreach( $sizes as $size ) : ?>
						<option value="<?php echo $size ?>" <?php selected( $size, $thumbnail_size, true ) ?>><?php echo esc_attr( $size ) ?></option>
					<?php endforeach; ?>
				</select>
			</p>
			
			<p>
				<?php $align = array(
					'alignnone' => __( 'none', XF_TEXTDOMAIN ),
					'alignleft' => __( 'left', XF_TEXTDOMAIN ),
					'alignright' => __( 'right', XF_TEXTDOMAIN ),
					'aligncenter' => __( 'center', XF_TEXTDOMAIN )
				) ?>
				<label for="<?php echo $this->get_field_id( 'image_alignment' ) ?>"><?php _e( 'Image Alignment:', XF_TEXTDOMAIN ) ?></label>
				<select id="<?php echo $this->get_field_id( 'image_alignment' ) ?>" name="<?php echo $this->get_field_name( 'image_alignment' ) ?>">
				<?php foreach( $align as $a => $b ): ?>
					<option value="<?php echo $a ?>" <?php selected( $a, $image_alignment ) ?>><?php echo $b ?></option>
				<?php endforeach; ?>
				</select>
			</p>
			<p>
				<label for="<?php echo $this->get_field_id( 'image_pos' ) ?>"><?php _e( 'Position:', XF_TEXTDOMAIN ) ?></label>
				<select id="<?php echo $this->get_field_id( 'image_pos' ) ?>" name="<?php echo $this->get_field_name( 'image_pos' ) ?>">
				<?php foreach(array( 1,2,3,4,5 ) as $c ): ?>
					<option value="<?php echo $c ?>" <?php selected( $c, $image_pos ) ?>><?php echo $c ?></option>
				<?php endforeach; ?>
				</select>
			</p>
		</div>

		<p>
			<input class="x-switcher" id="<?php echo $this->get_field_id( 'show_posttitle' ) ?>" type="checkbox" name="<?php echo $this->get_field_name( 'show_posttitle' ) ?>" value="1" <?php checked( 1, $show_posttitle ) ?>/>
			<label for="<?php echo $this->get_field_id( 'show_posttitle' ) ?>"><?php _e( 'Show Post Title', XF_TEXTDOMAIN ) ?></label>
		</p>
		<div class="<?php echo $this->get_field_id('show_posttitle') ?>">
			<p>
				<input id="<?php echo $this->get_field_id( 'suppress_posttitle_link' ) ?>" type="checkbox" name="<?php echo $this->get_field_name( 'suppress_posttitle_link' ) ?>" value="1" <?php checked( 1, $suppress_posttitle_link ) ?>/>
				<label for="<?php echo $this->get_field_id( 'suppress_posttitle_link' ) ?>"><?php _e( 'Post Title without Link', XF_TEXTDOMAIN ) ?></label>
			</p>
			<p>
				<label for="<?php echo $this->get_field_id( 'posttitle_tag' ) ?>"><?php _e( 'Tag:', XF_TEXTDOMAIN ) ?></label>
				<select id="<?php echo $this->get_field_id( 'posttitle_tag' ) ?>" name="<?php echo $this->get_field_name( 'posttitle_tag' ) ?>">
				<?php foreach( array( 'h2','h3','h4','h5' ) as $tag ): ?>
					<option value="<?php echo $tag ?>" <?php selected( $tag, $posttitle_tag ) ?>><?php echo esc_attr( $tag ) ?></option>
				<?php endforeach; ?>
				</select>
			</p>
			<p>
				<label for="<?php echo $this->get_field_id( 'posttitle_pos' ) ?>"><?php _e( 'Position:', XF_TEXTDOMAIN ) ?></label>
				<select id="<?php echo $this->get_field_id( 'posttitle_pos' ) ?>" name="<?php echo $this->get_field_name( 'posttitle_pos' ) ?>">
				<?php foreach( array( 1,2,3,4,5 ) as $g ): ?>
					<option value="<?php echo $g ?>" <?php selected( $g, $posttitle_pos ) ?>><?php echo esc_attr( $g ) ?></option>
				<?php endforeach; ?>
				</select>
			</p>
		</div>
<?php if (current_theme_supports('xtreme-subtitles')): ?>
		<p>
			<input class="x-switcher" id="<?php echo $this->get_field_id( 'show_subtitle' ) ?>" type="checkbox" name="<?php echo $this->get_field_name( 'show_subtitle' ) ?>" value="1" <?php checked( 1, $show_subtitle ) ?>/>
			<label for="<?php echo $this->get_field_id( 'show_subtitle' ) ?>"><?php _e( 'Show Subtitle', XF_TEXTDOMAIN ) ?></label>
		</p>
		<div class="<?php echo $this->get_field_id('show_subtitle') ?>">
			<p>
				<label for="<?php echo $this->get_field_id( 'subtitle_tag' ) ?>"><?php _e( 'Tag:', XF_TEXTDOMAIN ) ?></label>
				<select id="<?php echo $this->get_field_id( 'subtitle_tag' ) ?>" name="<?php echo $this->get_field_name( 'subtitle_tag' ) ?>">
				<?php foreach( array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'div' ) as $subtag ): ?>
					<option value="<?php echo $subtag ?>" <?php selected( $subtag, $subtitle_tag ) ?>><?php echo esc_attr( $subtag ) ?></option>
				<?php endforeach; ?>
				</select>
			</p>
			<p>
				<label for="<?php echo $this->get_field_id( 'subtitle_pos' ) ?>"><?php _e( 'Position:', XF_TEXTDOMAIN ) ?></label>
				<select id="<?php echo $this->get_field_id( 'subtitle_pos' ) ?>" name="<?php echo $this->get_field_name( 'subtitle_pos' ) ?>">
				<?php foreach( array( 1,2,3,4,5 ) as $x ): ?>
					<option value="<?php echo $x ?>" <?php selected( $x, $subtitle_pos ) ?>><?php echo esc_attr( $x ) ?></option>
				<?php endforeach; ?>
				</select>
			</p>
		</div>
<?php endif; ?>
		<p>
			<input class="x-switcher" id="<?php echo $this->get_field_id( 'show_byline' ) ?>" type="checkbox" name="<?php echo $this->get_field_name( 'show_byline' ) ?>" value="1" <?php checked( 1, $show_byline ) ?>/>
			<label for="<?php echo $this->get_field_id( 'show_byline' ) ?>"><?php _e( 'Show Byline', XF_TEXTDOMAIN ) ?></label>
		</p>
		<div class="<?php echo $this->get_field_id( 'show_byline' ) ?>">
			<p>
				<label for="<?php echo $this->get_field_id( 'byline_pos' ) ?>"><?php _e( 'Position:', XF_TEXTDOMAIN ) ?></label>
				<select id="<?php echo $this->get_field_id( 'byline_pos' ) ?>" name="<?php echo $this->get_field_name( 'byline_pos' ) ?>">
				<?php foreach( array( 1,2,3,4,5 ) as $f ): ?>
					<option value="<?php echo $f ?>" <?php selected( $f, $byline_pos ) ?>><?php echo esc_attr( $f ) ?></option>
				<?php endforeach; ?>
				</select>
			</p>
		</div>

		<p>
			<input class="x-switcher" id="<?php echo $this->get_field_id( 'show_content' ) ?>" type="checkbox" name="<?php echo $this->get_field_name( 'show_content' ) ?>" value="1" <?php checked( 1, $show_content ) ?>/>
			<label for="<?php echo $this->get_field_id( 'show_content' ) ?>"><?php _e( 'Show Content', XF_TEXTDOMAIN ) ?></label>
		</p>
		<div class="<?php echo $this->get_field_id( 'show_content' ) ?>">
			<p>
				<input class="x-switcher" id="<?php echo $this->get_field_id( 'wrap_content' ) ?>" type="checkbox" name="<?php echo $this->get_field_name( 'wrap_content' ) ?>" value="1" <?php checked( 1, $wrap_content ) ?>/>
				<label for="<?php echo $this->get_field_id( 'wrap_content' ) ?>"><?php _e( 'Wrap Content in a Div', XF_TEXTDOMAIN ) ?></label>
			</p>
		<?php $type = array(
			'xtreme_excerpt' => __( 'Xtreme Excerpt', XF_TEXTDOMAIN ),
			'excerpt' => __( 'Excerpt', XF_TEXTDOMAIN ),
			'content' => __( 'Full Content', XF_TEXTDOMAIN )
		) ?>
			<p>
				<label for="<?php echo $this->get_field_id( 'content_type' ) ?>"><?php _e( 'Content Type:', XF_TEXTDOMAIN ) ?></label>
				<select class="x-content" id="<?php echo $this->get_field_id( 'content_type' ) ?>" name="<?php echo $this->get_field_name( 'content_type' ) ?>">
				<?php foreach( $type as $c => $d ): ?>
					<option value="<?php echo $c ?>" <?php selected( $c, $content_type ); ?>><?php echo esc_attr( $d ) ?></option>
				<?php endforeach; ?>
				</select>
			</p>
			<div class="<?php echo $this->get_field_id( 'content_type' ) ?>">
				<p class="excerpt_length">
					<label for="<?php echo $this->get_field_id( 'excerpt_length' ) ?>"><?php _e( 'Excerpt Length:', XF_TEXTDOMAIN ); ?></label>
					<input id="<?php echo $this->get_field_id( 'excerpt_length' ) ?>" name="<?php echo $this->get_field_name( 'excerpt_length' ) ?>" type="text" value="<?php echo esc_attr( $excerpt_length ) ?>" size="3" /> <?php _e( 'Words', XF_TEXTDOMAIN ) ?>
					<br /><small><?php printf( __( '(at most %s)', XF_TEXTDOMAIN ), 80 ) ?></small>
				</p>
				<p class="excerpt_more">
					<label for="<?php echo $this->get_field_id( 'excerpt_more' ) ?>"><?php _e( 'End of Excerpt:', XF_TEXTDOMAIN) ?></label>
					<input id="<?php echo $this->get_field_id( 'excerpt_more' ) ?>" name="<?php echo $this->get_field_name( 'excerpt_more' ) ?>" type="text" value="<?php echo esc_html( $excerpt_more ) ?>"  />
				</p>
				<p class="excerpt_morelink_text">
					<label for="<?php echo $this->get_field_id( 'excerpt_morelink_text' ) ?>"><?php _e( 'Excerpt More Link Text:', XF_TEXTDOMAIN ) ?></label>
					<input id="<?php echo $this->get_field_id( 'excerpt_morelink_text' ) ?>" name="<?php echo $this->get_field_name( 'excerpt_morelink_text' ) ?>" type="text" value="<?php echo esc_html( $excerpt_morelink_text ) ?>" />
				</p>
				<p class="excerpt_morelink_text">
					<label for="<?php echo $this->get_field_id( 'excerpt_show_tags' ) ?>"><?php _e( 'Enable Tags', XF_TEXTDOMAIN ) ?></label>
					<input id="<?php echo $this->get_field_id( 'excerpt_show_tags' ) ?>" name="<?php echo $this->get_field_name( 'excerpt_show_tags' ) ?>" type="checkbox" value="1" <?php checked( 1, $excerpt_show_tags ) ?>" />
				</p>
			</div>
			<p>
				<label for="<?php echo $this->get_field_id( 'content_pos' ) ?>"><?php _e( 'Position:', XF_TEXTDOMAIN ) ?></label>
				<select id="<?php echo $this->get_field_id( 'content_pos' ) ?>" name="<?php echo $this->get_field_name( 'content_pos' ) ?>">
				<?php foreach( array( 1,2,3,4,5 ) as $f ): ?>
					<option value="<?php echo $f ?>" <?php selected( $f, $content_pos ) ?>><?php echo $f ?></option>
				<?php endforeach; ?>
				</select>
			</p>
		</div>
		<?php
	}

	function html_before( $columns, $count ) {
		
		$this->columns = ( int ) $columns;
		$this->count   = ( int ) $count;
		$html          = "";
		
		if ( $this->columns === 1 ) {
			return $html;
		}
		
		if ( $this->count % $this->columns == 0 ) {
			$html .= "<div class='ym-grid linearize-level-2'>\n";
		}
		$html .= "<div class='" . $this->classes[$this->columns][$this->count % $this->columns]['outer'] . "'>\n";
		$html .= "<div class='" . $this->classes[$this->columns][$this->count % $this->columns]['inner'] . "'>\n";
		
		return $html;
	}

	function html_after( $columns, $count ) {
		
		$this->columns = ( int ) $columns;
		$this->count   = ( int ) $count;
		$html          = "";
		
		if ( $this->columns === 1 ) {
			return $html;
		}
		
		if ( $this->count % $this->columns == 0 ) {
			$html .= "</div>\n</div>\n</div>\n";
		} else {
			$html .= "</div>\n</div>\n";
		}
		
		return $html;
	}
	
}