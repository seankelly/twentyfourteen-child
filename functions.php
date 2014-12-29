<?php

function enqueue_parent_theme_style() {
	wp_enqueue_style('parent-style', get_template_directory_uri() . '/style.css');
}


function wgom_twentyfourteen_setup() {
	// Override the number of posts to return for Featured_Content.
	remove_theme_support('featured-content');
        add_theme_support('featured-content', array(
		'featured_content_filter' => 'twentyfourteen_get_featured_posts',
		'max_posts' => 12,
	));
}


/*
 * Overrides of twentyfourteen_* functions.
 * -----------------------------------------------------------------------------
 */
/***
 * This function copied straight from Twenty Fourteen.
 */
/**
 * Display navigation to next/previous post when applicable.
 *
 * @since Twenty Fourteen 1.0
 */
function twentyfourteen_post_nav() {
        // Don't print empty markup if there's nowhere to navigate.
        $previous = ( is_attachment() ) ? get_post( get_post()->post_parent ) : get_adjacent_post( false, '', true );
        $next     = get_adjacent_post( false, '', false );

        if ( ! $next && ! $previous ) {
                return;
        }

        ?>
        <nav class="navigation post-navigation" role="navigation">
                <h1 class="screen-reader-text"><?php _e( 'Post navigation', 'twentyfourteen' ); ?></h1>
                <div class="nav-links">
                        <?php
                        if ( is_attachment() ) :
                                previous_post_link( '%link', __( '<span class="meta-nav">Published In</span>%title', 'twentyfourteen' ) );
                        else :
                                previous_post_link( '%link', __( '<span class="meta-nav">Previous Post</span>%title', 'twentyfourteen' ) );
                                next_post_link( '%link', __( '<span class="meta-nav">Next Post</span>%title', 'twentyfourteen' ) );
                        endif;
                        ?>
                        <div style="clear:both;"></div>
                </div><!-- .nav-links -->
        </nav><!-- .navigation -->
        <?php
}

/*
 * WGOM-specific functions.
 * -----------------------------------------------------------------------------
 */

/*
 * Add default featured images to posts in certain categories.
 */
// Mapping of category to default image. This is just the path relative to the
// theme's directory.
$wgom_default_category = array(
	//0 => '',
	// The Cup of Coffee rotates depending on the day.
	5 => array(25505, 25606, 25607, 25805, 25806, 25807, 25808, 25809, 25810, 25811, 25812, 25813, 25814, 25815, 25816, 25817, 25818, 25819, 25820, 26746, 26747, 26748, 26750, 26751, 26752, 26753, 26754, 26755, 26756, 26757, 26758, 26759, 26760, 26761, 26762, 26763, 26764, 26765, 26766, 26767, 26768, 26769, 26770, 26771, 26772, 26773, 26774, 26775, 26776, 26777, 26778, 26779, 26780, 26781, 26782, 26783, 26784, 26785, 26786, 26787, 26788, 26789, 26790, 26791, 26792, 26793, 26794, 26795, 26796, 26797, 26798, 26799, 26800, 26801, 26802, 26803, 26804, 26805, 26806, 26807, 26808, 26809, 26810, 31837),
	// Keeping Track has special support below.
	// At The Movies.
	10 => array(25590, 25525, 31844),
	// First Monday Book Day.
	12 => array(25573, 25574, 25575, 25576),
	// MLB.
	14 => 32706,
	// Minnesota Twins.
	15 => array(25592, 25734, 25735, 25736, 25737, 25738, 26811),
	// NBA.
	16 => array(25739, 26812, 26813, 26814, 26815, 26816, 26817, 26818),
	// NFL.
	17 => array(25594, 26819, 26820),
	// NHL.
	18 => 25595,
	// Soccer.
	19 => 25600,
	// WGOM Videos,
	//22 => '',
	// Friday Music Day.
	57 => array(25743, 25580, 31839, 31847),
	// The Nation Has An Appetite.
	90 => array(25535, 25601, 25602, 25754),
	// Video Games
	102 => array(25596, 25597, 25753, 25599),
	// Father Knows Best.
	277 => array(25506, 25507),
	// WGOM Fitness,
	1928 => array(25604, 25605, 26821, 26822, 26823, 26824, 26835),
);

$wgom_keeping_track_images = array(
	'Happy Birthday' => array(25747, 31840, 31841),
	'Arizona Fall League' => array(32730, 32731, 32732),
	'Australian League' => 25731,
	'Dominican League' => array(25732, 32969, 32995, 32996, 32997),
	'Mexican League' => array(25733, 32787, 32818, 32819, 32820),
	'Puerto Rican League' => 25740,
	'Venezuelan Winter League' => array(25742, 32767, 32768, 32769),
	'Red Wings' => 31843,
	'Rock Cats' => 25751,
	'Kernels' => 25748,
	'Miracle' => 25750,
	'Minor League Updates' => array(28587, 28615, 28775, 31842, 30338),
);

function wgom_get_attach_id($arr) {
	if (is_array($arr)) {
		$image_key = array_rand($arr, 1);
		$image_id = $arr[$image_key];
	}
	else {
		$image_id = $arr;
	}
	return $image_id;
}

/**
 * Set the featured image for a post. This is run from the post_publish action.
 */
function wgom_set_post_featured_image($postid) {
	$post = get_post($postid);

	// If the post already has a featured image set, don't do anything.
	if (has_post_thumbnail($postid)) {
		return;
	}

	global $wgom_default_category, $wgom_keeping_track_images;
	// No thumbnail, so let's try to get a default from the categories.
	$the_cats = get_the_category($postid);
	$categories = array();
	foreach ($the_cats as $cat) {
		$categories[] = $cat->term_id;
	}

	$the_tags = get_the_tags($postid);
	$tags = array();
	if (!empty($the_tags)) {
		foreach ($the_tags as $t) {
			$tags[] = $t->name;
		}
	}

	// Default to nothing for now.
	$image_id = -1;

	// Get the intersection of the desired categories and the categories
	// present in the post.
	foreach ($categories as $c) {
		if (array_key_exists($c, $wgom_default_category)) {
			$attach_id = $wgom_default_category[$c];
			// If the attachment "id" is an array, then pick one
			// randomly from that array.
			$image_id = wgom_get_attach_id($attach_id);
			break;
		}
		elseif (intval($c) === 9) {
			// For the Keeping Track category, look at the tags to
			// determine what image to use.
			$available_images = array();
			foreach ($tags as $t) {
				if (array_key_exists($t, $wgom_keeping_track_images)) {
					// Flatten any arrays to give every
					// image equal chance at being picked.
					$v = $wgom_keeping_track_images[$t];
					if (is_array($v)) {
						$available_images = array_merge($available_images, $v);
					}
					else {
						$available_images[] = $v;
					}
				}
			}
			if (count($available_images) > 0) {
				$image_id = wgom_get_attach_id($available_images);
			}
		}
	}

	if (is_int($image_id) && $image_id > 0) {
		add_post_meta($postid, '_thumbnail_id', $image_id, true);
	}
}

add_action('after_setup_theme', 'wgom_twentyfourteen_setup', 11);
add_action('publish_post', 'wgom_set_post_featured_image');
add_action('wp_enqueue_scripts', 'enqueue_parent_theme_style');

require get_stylesheet_directory() . '/inc/featured-content.php';
