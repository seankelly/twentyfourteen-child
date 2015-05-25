<?php
/**
 * Twenty Fourteen Featured Content
 *
 * This module allows you to define a subset of posts to be displayed
 * in the theme's Featured Content area.
 *
 * For maximum compatibility with different methods of posting users
 * will designate a featured post tag to associate posts with. Since
 * this tag now has special meaning beyond that of a normal tags, users
 * will have the ability to hide it from the front-end of their site.
 */
class Featured_Content {

	/**
	 * The maximum number of posts a Featured Content area can contain.
	 *
	 * We define a default value here but themes can override
	 * this by defining a "max_posts" entry in the second parameter
	 * passed in the call to add_theme_support( 'featured-content' ).
	 *
	 * @see Featured_Content::init()
	 *
	 * @since Twenty Fourteen 1.0
	 *
	 * @static
	 * @access public
	 * @var int
	 */
	public static $max_posts = 15;

	/**
	 * Instantiate.
	 *
	 * All custom functionality will be hooked into the "init" action.
	 *
	 * @static
	 * @access public
	 * @since Twenty Fourteen 1.0
	 */
	public static function setup() {
		add_action( 'init', array( __CLASS__, 'init' ), 30 );
	}

	/**
	 * Conditionally hook into WordPress.
	 *
	 * Theme must declare that they support this module by adding
	 * add_theme_support( 'featured-content' ); during after_setup_theme.
	 *
	 * If no theme support is found there is no need to hook into WordPress.
	 * We'll just return early instead.
	 *
	 * @static
	 * @access public
	 * @since Twenty Fourteen 1.0
	 */
	public static function init() {
		$theme_support = get_theme_support( 'featured-content' );

		// Return early if theme does not support Featured Content.
		if ( ! $theme_support ) {
			return;
		}

		/*
		 * An array of named arguments must be passed as the second parameter
		 * of add_theme_support().
		 */
		if ( ! isset( $theme_support[0] ) ) {
			return;
		}

		// Return early if "featured_content_filter" has not been defined.
		if ( ! isset( $theme_support[0]['featured_content_filter'] ) ) {
			return;
		}

		$filter = $theme_support[0]['featured_content_filter'];

		// Theme can override the number of max posts.
		if ( isset( $theme_support[0]['max_posts'] ) ) {
			self::$max_posts = absint( $theme_support[0]['max_posts'] );
		}

		add_filter( $filter,                              array( __CLASS__, 'get_featured_posts' )    );
		add_action( 'customize_register',                 array( __CLASS__, 'customize_register' ), 9 );
		add_action( 'admin_init',                         array( __CLASS__, 'register_setting'   )    );
		add_action( 'switch_theme',                       array( __CLASS__, 'delete_transient'   )    );
		add_action( 'save_post',                          array( __CLASS__, 'delete_transient'   )    );
		add_action( 'delete_post_tag',                    array( __CLASS__, 'delete_post_tag'    )    );
		add_action( 'customize_controls_enqueue_scripts', array( __CLASS__, 'enqueue_scripts'    )    );
		add_action( 'pre_get_posts',                      array( __CLASS__, 'pre_get_posts'      )    );
		add_action( 'wp_loaded',                          array( __CLASS__, 'wp_loaded'          )    );
	}

	/**
	 * Hide "featured" tag from the front-end.
	 *
	 * Has to run on wp_loaded so that the preview filters of the Customizer
	 * have a chance to alter the value.
	 *
	 * @static
	 * @access public
	 * @since Twenty Fourteen 1.0
	 */
	public static function wp_loaded() {
		if ( self::get_setting( 'hide-tag' ) ) {
			add_filter( 'get_terms',     array( __CLASS__, 'hide_featured_term'     ), 10, 3 );
			add_filter( 'get_the_terms', array( __CLASS__, 'hide_the_featured_term' ), 10, 3 );
		}
	}

	/**
	 * Get featured posts.
	 *
	 * @static
	 * @access public
	 * @since Twenty Fourteen 1.0
	 *
	 * @return array Array of featured posts.
	 */
	public static function get_featured_posts() {
		$post_ids = self::get_featured_post_ids();

		// No need to query if there is are no featured posts.
		if ( empty( $post_ids ) ) {
			return array();
		}

		$featured_posts = get_posts( array(
			'include'        => $post_ids,
			'posts_per_page' => count( $post_ids ),
		) );

		// Re-order to match the order in $post_ids.
		// Create an array mapping post ID to order number.
		$i = 0;
		$id_order = array();
		foreach ($post_ids as $id) {
			$id_order[$id] = $i++;
		}

		// Take the order number and create a new array mapping that
		// order number to post.
		$ordered_posts = array();
		foreach ($featured_posts as $post) {
			$idx = (int)$id_order[$post->ID];
			$ordered_posts[$idx] = $post;
		}

		// Now order based on the key. array_values will return the
		// posts in the desired order.
		ksort($ordered_posts);
		$featured_posts = array_values($ordered_posts);

		return $featured_posts;
	}

	/**
	 * Get featured post IDs
	 *
	 * This function will return the an array containing the
	 * post IDs of all featured posts.
	 *
	 * Sets the "featured_content_ids" transient.
	 *
	 * @static
	 * @access public
	 * @since Twenty Fourteen 1.0
	 *
	 * @return array Array of post IDs.
	 */
	public static function get_featured_post_ids() {
		// Get array of cached results if they exist.
		$featured_ids = get_transient( 'featured_content_ids' );

		if ( false === $featured_ids ) {
			$featured_ids = self::wgom_featured_post_ids();
		}

		// Ensure correct format before return.
		return array_map( 'absint', $featured_ids );
	}

	public static function wgom_featured_post_ids() {
		$settings = self::get_setting();

		$active_post_num = 4;
		$tag_names = array($settings['tag-name'], 'WGOM ' . $settings['tag-name']);
		$tags = array();
		// Return sticky post ids if no tag name is set.
		foreach ($tag_names as $term_name) {
			$term = get_term_by('name', $term_name, 'post_tag');
			if ($term) {
				$tags[] = $term->term_id;
			} else {
				$active_post_num++;
			}
		}

		// Query for last Cup of Coffee post (catid = 5) and Video post
		// (catid = 22).
		$pinned_categories = array(5, 22);
		$category_posts = array();
		foreach ($pinned_categories as $catid) {
			$post = get_posts(array(
				'fields'      => 'ids',
				'numberposts' => 1,
				'category'    => $catid,
				'orderby'     => 'post_date',
				'order'       => 'DESC',
			));
			$category_posts[] = $post[0];
		}

		$featured_tag_posts = array();
		// Query for featured posts.
		foreach ($tags as $featured_tag) {
			$featured_tag_post = get_posts( array(
				'fields'      => 'ids',
				'numberposts' => 1,
				'orderby'     => 'post_date',
				'order'       => 'DESC',
				'tax_query'   => array(
					array(
						'field'    => 'term_id',
						'taxonomy' => 'post_tag',
						'terms'    => $featured_tag,
					),
				),
			) );

			if (count($featured_tag_post) > 0) {
				$featured_tag_posts[] = $featured_tag_post[0];
			}
			else {
				$active_post_num++;
			}
		}

		$pinned_row_ids = array_merge($category_posts, $featured_tag_posts);

		// Second row: "Active" posts.
		global $wpdb;
		$cur_featured_ids = implode(',', $pinned_row_ids);
		$posts_table = $wpdb->posts;
		$rel_table = $wpdb->term_relationships;
		$tax_table = $wpdb->term_taxonomy;
		$comments_table = $wpdb->comments;
		$top_cats = implode(', ', $pinned_categories);
		// Get N most recent posts not in the above categories.
		$get_active_posts = "
			SELECT DISTINCT $posts_table.ID AS post_id, MAX(DATE_FORMAT(wgom_comments.comment_date, '%Y-%m-%d %H')) AS last_lte
			FROM $posts_table
			LEFT JOIN $comments_table ON $posts_table.ID = $comments_table.comment_post_ID
			LEFT JOIN $rel_table ON $posts_table.ID = $rel_table.object_ID
			LEFT JOIN $tax_table ON $rel_table.term_taxonomy_id = $tax_table.term_taxonomy_id
			WHERE $posts_table.post_status = 'publish'
			AND $posts_table.post_type = 'post'
			AND $posts_table.comment_count > 0
			AND $tax_table.taxonomy = 'category'
			AND $tax_table.term_id NOT IN ($top_cats)
			AND $posts_table.ID NOT IN ($cur_featured_ids)
			GROUP BY $posts_table.ID
			ORDER BY last_lte DESC, post_id DESC
			LIMIT $active_post_num
		";
                /*
		$get_active_posts = "
			SELECT DISTINCT $posts_table.ID, log(comment_count+1) / pow(TIMESTAMPDIFF(HOUR, post_date, now())+12, 2) AS score
			FROM $posts_table
			LEFT JOIN $rel_table ON $posts_table.ID = $rel_table.object_ID
			LEFT JOIN $tax_table ON $rel_table.term_taxonomy_id = $tax_table.term_taxonomy_id
			WHERE post_status = 'publish'
			AND post_type = 'post'
			AND $tax_table.taxonomy = 'category'
			AND $tax_table.term_id NOT IN ($top_cats)
			AND ID NOT IN ($cur_featured_ids)
			ORDER BY score DESC
			LIMIT $active_post_num
		";
                */

		$active_res = $wpdb->get_results($get_active_posts, ARRAY_N);
		$active_row_ids = array();
		foreach ($active_res as $row) {
			$active_row_ids[] = $row[0];
		}

		// Third row: 4 most recent posts not already selected for
		// inclusion. Merge posts so far to remove them from the most
		// recent post listing.
		$featured_ids = array_merge($pinned_row_ids, $active_row_ids);
		$cur_featured_ids = implode(',', $featured_ids);
		$get_recent_posts = "
			SELECT DISTINCT $posts_table.ID
			FROM $posts_table
			LEFT JOIN $rel_table ON $posts_table.ID = $rel_table.object_ID
			LEFT JOIN $tax_table ON $rel_table.term_taxonomy_id = $tax_table.term_taxonomy_id
			WHERE post_status = 'publish'
			AND post_type = 'post'
			AND $tax_table.taxonomy = 'category'
			AND $tax_table.term_id NOT IN ($top_cats)
			AND ID NOT IN ($cur_featured_ids)
			ORDER BY post_date DESC
			LIMIT 4
		";

		$recent_res = $wpdb->get_results($get_recent_posts, ARRAY_N);
		$recent_row_ids = array();
		foreach ($recent_res as $row) {
			$recent_row_ids[] = $row[0];
		}

		$featured_ids = array_merge($featured_ids, $recent_row_ids);
		set_transient('featured_content_ids', $featured_ids);
		return $featured_ids;
	}

	/**
	 * Return an array with IDs of posts maked as sticky.
	 *
	 * @static
	 * @access public
	 * @since Twenty Fourteen 1.0
	 *
	 * @return array Array of sticky posts.
	 */
	public static function get_sticky_posts() {
		return array_slice( get_option( 'sticky_posts', array() ), 0, self::$max_posts );
	}

	/**
	 * Delete featured content ids transient.
	 *
	 * Hooks in the "save_post" action.
	 *
	 * @see Featured_Content::validate_settings().
	 *
	 * @static
	 * @access public
	 * @since Twenty Fourteen 1.0
	 */
	public static function delete_transient() {
		delete_transient( 'featured_content_ids' );
	}

	/**
	 * Exclude featured posts from the home page blog query.
	 *
	 * Filter the home page posts, and remove any featured post ID's from it.
	 * Hooked onto the 'pre_get_posts' action, this changes the parameters of
	 * the query before it gets any posts.
	 *
	 * @static
	 * @access public
	 * @since Twenty Fourteen 1.0
	 *
	 * @param WP_Query $query WP_Query object.
	 * @return WP_Query Possibly-modified WP_Query.
	 */
	public static function pre_get_posts( $query ) {

		// Bail if not home or not main query.
		if ( ! $query->is_home() || ! $query->is_main_query() ) {
			return;
		}

		// Bail if the blog page is not the front page.
		if ( 'posts' !== get_option( 'show_on_front' ) ) {
			return;
		}

		$featured = self::get_featured_post_ids();

		// Bail if no featured posts.
		if ( ! $featured ) {
			return;
		}

		// We need to respect post ids already in the blacklist.
		$post__not_in = $query->get( 'post__not_in' );

		if ( ! empty( $post__not_in ) ) {
			$featured = array_merge( (array) $post__not_in, $featured );
			$featured = array_unique( $featured );
		}

		$query->set( 'post__not_in', $featured );
	}

	/**
	 * Reset tag option when the saved tag is deleted.
	 *
	 * It's important to mention that the transient needs to be deleted,
	 * too. While it may not be obvious by looking at the function alone,
	 * the transient is deleted by Featured_Content::validate_settings().
	 *
	 * Hooks in the "delete_post_tag" action.
	 *
	 * @see Featured_Content::validate_settings().
	 *
	 * @static
	 * @access public
	 * @since Twenty Fourteen 1.0
	 *
	 * @param int $tag_id The term_id of the tag that has been deleted.
	 */
	public static function delete_post_tag( $tag_id ) {
		$settings = self::get_setting();

		if ( empty( $settings['tag-id'] ) || $tag_id != $settings['tag-id'] ) {
			return;
		}

		$settings['tag-id'] = 0;
		$settings = self::validate_settings( $settings );
		update_option( 'featured-content', $settings );
	}

	/**
	 * Hide featured tag from displaying when global terms are queried from the front-end.
	 *
	 * Hooks into the "get_terms" filter.
	 *
	 * @static
	 * @access public
	 * @since Twenty Fourteen 1.0
	 *
	 * @param array $terms      List of term objects. This is the return value of get_terms().
	 * @param array $taxonomies An array of taxonomy slugs.
	 * @return array A filtered array of terms.
	 *
	 * @uses Featured_Content::get_setting()
	 */
	public static function hide_featured_term( $terms, $taxonomies, $args ) {

		// This filter is only appropriate on the front-end.
		if ( is_admin() ) {
			return $terms;
		}

		// We only want to hide the featured tag.
		if ( ! in_array( 'post_tag', $taxonomies ) ) {
			return $terms;
		}

		// Bail if no terms were returned.
		if ( empty( $terms ) ) {
			return $terms;
		}

		// Bail if term objects are unavailable.
		if ( 'all' != $args['fields'] ) {
			return $terms;
		}

		$settings = self::get_setting();
		foreach( $terms as $order => $term ) {
			if ( ( $settings['tag-id'] === $term->term_id || $settings['tag-name'] === $term->name ) && 'post_tag' === $term->taxonomy ) {
				unset( $terms[ $order ] );
			}
		}

		return $terms;
	}

	/**
	 * Hide featured tag from display when terms associated with a post object
	 * are queried from the front-end.
	 *
	 * Hooks into the "get_the_terms" filter.
	 *
	 * @static
	 * @access public
	 * @since Twenty Fourteen 1.0
	 *
	 * @param array $terms    A list of term objects. This is the return value of get_the_terms().
	 * @param int   $id       The ID field for the post object that terms are associated with.
	 * @param array $taxonomy An array of taxonomy slugs.
	 * @return array Filtered array of terms.
	 *
	 * @uses Featured_Content::get_setting()
	 */
	public static function hide_the_featured_term( $terms, $id, $taxonomy ) {

		// This filter is only appropriate on the front-end.
		if ( is_admin() ) {
			return $terms;
		}

		// Make sure we are in the correct taxonomy.
		if ( 'post_tag' != $taxonomy ) {
			return $terms;
		}

		// No terms? Return early!
		if ( empty( $terms ) ) {
			return $terms;
		}

		$settings = self::get_setting();
		foreach( $terms as $order => $term ) {
			if ( ( $settings['tag-id'] === $term->term_id || $settings['tag-name'] === $term->name ) && 'post_tag' === $term->taxonomy ) {
				unset( $terms[ $term->term_id ] );
			}
		}

		return $terms;
	}

	/**
	 * Register custom setting on the Settings -> Reading screen.
	 *
	 * @static
	 * @access public
	 * @since Twenty Fourteen 1.0
	 */
	public static function register_setting() {
		register_setting( 'featured-content', 'featured-content', array( __CLASS__, 'validate_settings' ) );
	}

	/**
	 * Add settings to the Customizer.
	 *
	 * @static
	 * @access public
	 * @since Twenty Fourteen 1.0
	 *
	 * @param WP_Customize_Manager $wp_customize Customizer object.
	 */
	public static function customize_register( $wp_customize ) {
		$wp_customize->add_section( 'featured_content', array(
			'title'          => __( 'Featured Content', 'twentyfourteen' ),
			'description'    => sprintf( __( 'Use a <a href="%1$s">tag</a> to feature your posts. If no posts match the tag, <a href="%2$s">sticky posts</a> will be displayed instead.', 'twentyfourteen' ),
				esc_url( add_query_arg( 'tag', _x( 'featured', 'featured content default tag slug', 'twentyfourteen' ), admin_url( 'edit.php' ) ) ),
				admin_url( 'edit.php?show_sticky=1' )
			),
			'priority'       => 130,
			'theme_supports' => 'featured-content',
		) );

		// Add Featured Content settings.
		$wp_customize->add_setting( 'featured-content[tag-name]', array(
			'default'              => _x( 'featured', 'featured content default tag slug', 'twentyfourteen' ),
			'type'                 => 'option',
			'sanitize_js_callback' => array( __CLASS__, 'delete_transient' ),
		) );
		$wp_customize->add_setting( 'featured-content[hide-tag]', array(
			'default'              => true,
			'type'                 => 'option',
			'sanitize_js_callback' => array( __CLASS__, 'delete_transient' ),
		) );

		// Add Featured Content controls.
		$wp_customize->add_control( 'featured-content[tag-name]', array(
			'label'    => __( 'Tag Name', 'twentyfourteen' ),
			'section'  => 'featured_content',
			'priority' => 20,
		) );
		$wp_customize->add_control( 'featured-content[hide-tag]', array(
			'label'    => __( 'Don&rsquo;t display tag on front end.', 'twentyfourteen' ),
			'section'  => 'featured_content',
			'type'     => 'checkbox',
			'priority' => 30,
		) );
	}

	/**
	 * Enqueue the tag suggestion script.
	 *
	 * @static
	 * @access public
	 * @since Twenty Fourteen 1.0
	 */
	public static function enqueue_scripts() {
		wp_enqueue_script( 'featured-content-suggest', get_template_directory_uri() . '/js/featured-content-admin.js', array( 'jquery', 'suggest' ), '20131022', true );
	}

	/**
	 * Get featured content settings.
	 *
	 * Get all settings recognized by this module. This function
	 * will return all settings whether or not they have been stored
	 * in the database yet. This ensures that all keys are available
	 * at all times.
	 *
	 * In the event that you only require one setting, you may pass
	 * its name as the first parameter to the function and only that
	 * value will be returned.
	 *
	 * @static
	 * @access public
	 * @since Twenty Fourteen 1.0
	 *
	 * @param string $key The key of a recognized setting.
	 * @return mixed Array of all settings by default. A single value if passed as first parameter.
	 */
	public static function get_setting( $key = 'all' ) {
		$saved = (array) get_option( 'featured-content' );

		$defaults = array(
			'hide-tag' => 1,
			'tag-id'   => 0,
			'tag-name' => _x( 'featured', 'featured content default tag slug', 'twentyfourteen' ),
		);

		$options = wp_parse_args( $saved, $defaults );
		$options = array_intersect_key( $options, $defaults );

		if ( 'all' != $key ) {
			return isset( $options[ $key ] ) ? $options[ $key ] : false;
		}

		return $options;
	}

	/**
	 * Validate featured content settings.
	 *
	 * Make sure that all user supplied content is in an expected
	 * format before saving to the database. This function will also
	 * delete the transient set in Featured_Content::get_featured_content().
	 *
	 * @static
	 * @access public
	 * @since Twenty Fourteen 1.0
	 *
	 * @param array $input Array of settings input.
	 * @return array Validated settings output.
	 */
	public static function validate_settings( $input ) {
		$output = array();

		if ( empty( $input['tag-name'] ) ) {
			$output['tag-id'] = 0;
		} else {
			$term = get_term_by( 'name', $input['tag-name'], 'post_tag' );

			if ( $term ) {
				$output['tag-id'] = $term->term_id;
			} else {
				$new_tag = wp_create_tag( $input['tag-name'] );

				if ( ! is_wp_error( $new_tag ) && isset( $new_tag['term_id'] ) ) {
					$output['tag-id'] = $new_tag['term_id'];
				}
			}

			$output['tag-name'] = $input['tag-name'];
		}

		$output['hide-tag'] = isset( $input['hide-tag'] ) && $input['hide-tag'] ? 1 : 0;

		// Delete the featured post ids transient.
		self::delete_transient();

		return $output;
	}
} // Featured_Content

Featured_Content::setup();
