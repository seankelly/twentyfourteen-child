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

/**
 * This function exists to link to remote images that I don't want to attach to
 * the post.
 * @return string Image URL.
 */

function wgom_get_category_featured_image($categories) {
	foreach ($categories as $c) {
		$post = get_post();
		if (intval($c) === 22) {
			// A Video post. Hunt for the first URL matching
			// youtube's v=CODE. Grab the code and use that to get
			// the 0.jpg.
			$image = '';
			preg_match('/(?:youtu.be\/|v=)([\w-]+)/', $post->post_content, $videos);
			if (count($videos) > 0) {
				$video_id = $videos[1];
				$iframe_html = "<iframe src='https://www.youtube.com/embed/$video_id?feature=oembed' allowfullscreen='' frameborder='0' width='670' height='372'></iframe>";
				return $iframe_html;
			}
			break;
		}
	}
}

function wgom_get_featured_image() {
	// This is the usual code that gets executed.
	if ( has_post_thumbnail() ) {
		if ( 'grid' == get_theme_mod( 'featured_content_layout' ) )
			the_post_thumbnail();
		else
			the_post_thumbnail( 'twentyfourteen-full-width' );
	}
	else {
		$all_categories = get_the_category();
		$categories = array();
		foreach ($all_categories as $cat) {
			$categories[] = intval($cat->term_id);
		}

		// This is the WGOM extension part.
		$image_html = wgom_get_category_featured_image($categories);
		if (!empty($image_html)) {
			echo $image_html;
		}
	}

	wgom_get_featured_overlay();
}

function wgom_get_featured_overlay() {
	// Check for certain tags to overlay another image.
	$tag_images = array(
		'Guest DJ'   => '<img class="overlay" width="249" height="71" src="/wp-content/uploads/2013/12/guest-dj.jpg" />',
		'Theme Week' => '<img class="overlay" src="/wp-content/uploads/2014/06/TmWk.png" />',
		'e-6 bait'   => '<img class="overlay" src="/wp-content/uploads/2014/01/e-6-bait.jpg" />',
		'new music'  => '<img class="overlay" src="/wp-content/uploads/2014/08/NM.png" />',
		'RIP'        => '<img class="overlay" src="/wp-content/uploads/2015/06/candle.png" />',
		'SXSW 2014'  => '<img class="overlay" src="/wp-content/uploads/2014/03/SXSW.png" />',
		'SXSW 2015'  => '<img class="overlay" src="/wp-content/uploads/2015/04/SXSW-2015.png" />',
		'MLB.TV Free Game Of The Day' => '<img class="overlay" src="/wp-content/uploads/2014/08/FGotD.png" />',
		'NSFW' => '<img class="overlay-left" src="/wp-content/uploads/2015/08/NSFW.jpg" />',
		'Best of 2015' => '<img class="overlay-left" src="/wp-content/uploads/2015/12/best-of-fs8.png" />',
		'Best of 2016' => '<img class="overlay-left" src="/wp-content/uploads/2015/12/best-of-fs8.png" />',
		'Best of 2017' => '<img class="overlay-left" src="/wp-content/uploads/2015/12/best-of-fs8.png" />',
	);

	$the_tags = get_the_tags();
	$tags = array();
	if (!empty($the_tags)) {
		foreach ($the_tags as $t) {
			if (array_key_exists($t->name, $tag_images)) {
				echo $tag_images[$t->name];
			}
		}
	}
}

/*
 * Get the ratings text for any post in a valid category.
 */
function wgom_get_ratings_text() {
	$all_categories = get_the_category();
	$valid_category = false;
	foreach ($all_categories as $cat) {
		if (intval($cat->term_id) === 22) {
			$valid_category = true;
			break;
		}
	}
	if ($valid_category === false) {
		return;
	}

	$post_data = get_post_custom();
	if (is_array($post_data)) {
		$ratings_text = '';

		$ratings_users = array_key_exists('ratings_users', $post_data) ? intval($post_data['ratings_users'][0]) : 0;
		$ratings_score = array_key_exists('ratings_score', $post_data) ? intval($post_data['ratings_score'][0]) : 0;

		if ($ratings_users === 0) {
			$ratings_text = ' Rate it!';
		}
		else if ($ratings_users < 3) {
			$ratings_text = " $ratings_users ratings";
		}
		else {
			$ratings_avg = $ratings_score / $ratings_users;
			$avg_text = sprintf("%.1f", $ratings_avg);
			$ratings_text = " $ratings_users ratings: $avg_text avg";
		}

		return $ratings_text;
	}
}

function wgom_filter_add_ratings($content) {
	if (get_post_format() === 'video' && function_exists('the_ratings')) {
		$content .= the_ratings($start_tag = 'span', $custom_id = 0, $display = false);
	}
	return $content;
}

function wgom_filter_show_top_videos() {
	$MINIMUM_VOTES = 5;
	$NUMBER_VIDEOS = 10;
	if (get_post_format() === 'video' && function_exists('the_ratings')) {
		/*
		 * calculate: W = \frac{Rv + Cm}{v+m}
		 * m = minimum number of votes to be ranked (user option).
		 */
		global $wpdb;
		$m = $MINIMUM_VOTES;
		// Get average rating (C).
		$overall_avg = floatval($wpdb->get_var('SELECT AVG(rating_rating) FROM wgom_ratings'));
		// Get post ID, total rating for post (R*v), and number of votes (v).
		// Limit posts to those from the last year. Do a crude filter
		// by estimating one day is 86,400 seconds and there are 365.24
		// days in a year.
		$ratings_since = intval(time() - 86400*365.24);
		$ratings = $wpdb->get_results("SELECT DISTINCT(rating_postid) AS postid, SUM(rating_rating) AS s, COUNT(rating_rating) AS v FROM wgom_ratings WHERE rating_timestamp > $ratings_since GROUP BY rating_postid", 'ARRAY_N');

		// Keep track of every post ID to pick a random video.
		$all_posts = array();
		$ranked_posts = array();
		// postid | total rating | number of ratings | average rating
		foreach ($ratings as $post) {
			$postid = $post[0];
			$v = intval($post[2]);
			$all_posts[] = $postid;
			if ($v < $m) {
				continue;
			}
			$Rv = floatval($post[1]);
			$W = ($Rv + $overall_avg * $m) / ($v + $m);
			$ranked_posts[$post[0]] = $W;
		}

		$num = intval($NUMBER_VIDEOS);
		arsort($ranked_posts);

		// Get the top $num posts. These will be used for another query to get
		// the post data.
		$i = 0;
		$final_posts = array();
		foreach ($ranked_posts as $postid => $rank) {
			if ($i >= $num)
				break;
			$final_posts[] = $postid;
			$i++;
		}

		// Get those posts and stash in an array. MySQL will return them in a
		// random order, so need to output them after collecting everything.
		$query = new \WP_Query(array('post__in' => array_merge($final_posts, array($random_post))));
		$post_objs = array();
		while ($query->have_posts()) {
			$post = $query->next_post();
			$post_objs[$post->ID] = $post;
		}

		// Output the top N posts in the right order.
		$top_video_content = "<div class='top-videos'><h6>Recent Top Videos</h6>";
		foreach ($final_posts as $postid) {
			$top_video_content .= print_post($post_objs[$postid]);
		}
		$top_video_content .= '</div>';

		echo $top_video_content;
	}
}

function endswith($str, $needle) {
	$str_len = strlen($str);
	$needle_len = strlen($needle);
	if ($str_len < $needle_len) {
		return false;
	}
	return substr_compare($str, $needle, $str_len - $needle_len, $needle_len) === 0;
}

function print_post($post) {
	if (function_exists('the_ratings')) {
		$content = '<small>'
			 . '<a href="'
			 . get_permalink($post->ID)
			 . '" rel="bookmark" title="'
			 . __('Permanent Link to')
			 . esc_attr(strip_tags($post->post_title))
			 . '">'
			 . $post->post_title
			 . '</a> '
			 . '<span class="comments-link">'
			 . wgom_get_comments_link($post, __('Be 1st'), __('1 LTE'), __('% LTEs'))
			 . '</span>'
			 ;
		$content .= the_ratings($start_tag = 'div', $custom_id = $post->ID, $display = FALSE);
		$content .= '</small>';
	}

	return $content;
}

function wgom_get_comments_link($post, $zero, $one, $more) {
	$link = '<a href="'
	      . get_comments_link($post->ID)
	      . '">';

	$comment_count = $post->comment_count;
	if ($comment_count === 0) {
		$comments = $zero;
	}
	elseif ($comment_count === 1) {
		$comments = $one;
	}
	elseif ($comment_count >= 2) {
		$comments = preg_replace('/%/', $comment_count, $more);
	}
	// Comments closed, not implemented.
	else {
		return $more;
	}

	return $link . $comments . "</a>";
}

function wgom_video_random_video() {
	// Brief digression. Pick a random post to include in the query to get
	// the top posts.
	// TODO: Fill this in.
	$all_posts = array();
	$final_posts = array();

	$today_date = getdate();
	$today_time = mktime($second = 0, $minute = 0, $hour = 0, $month = $today_date['mon'], $day = $today_date['mday'], $year = $today_date['year']);
	// Also ensure the random post isn't one of the top rated posts.
	$i = 0;
	$limit = 100;
	$random_post = 0;
	do {
		$idx = $today_time % count($all_posts);
		$random_post = $all_posts[$idx];
		$today_time++;
		$i++;
		if ($i > $limit) {
			break;
		}
	} while (array_key_exists($random_post, $final_posts));
}

// From http://wordpress.stackexchange.com/questions/105942/embedding-youtube-video-on-comments.
// Allow special automatic embedding features that happen in posts to also
// happen in comments.
function wgom_filter_oembed_comments($comment) {
	add_filter('embed_oembed_discover', '__return_false', 999);
	$comment = $GLOBALS['wp_embed']->autoembed($comment);
	remove_filter('embed_oembed_discover', '__return_false', 999);
	return $comment;
}

function wgom_head() {
?>
	<link rel="icon" href="//wgom.org/favicon.ico" />
<?php
}

function wgom_footer() {
?>
<script type="text/javascript">
var _gaq = _gaq || [];
_gaq.push(['_setAccount', 'UA-22892586-1']);
_gaq.push(['_setDomainName', 'wgom.org']);
_gaq.push(['_trackPageview']);

(function() {
	var ga = document.createElement('script'); ga.type = 'text/javascript'; ga.async = true;
	ga.src = ('https:' == document.location.protocol ? 'https://ssl' : 'http://www') + '.google-analytics.com/ga.js';
	var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(ga, s);
})();
</script>
<?php
}

function wgom_footer_timer() {
?>
	<p><?php timer_stop(1); ?></p>
	<a href="#page">
		<div class="aligncenter jump-top"></div>
	</a>
<?php
}

add_action('after_setup_theme', 'wgom_twentyfourteen_setup', 11);
//add_action('publish_post', 'wgom_set_post_featured_image');
add_action('twentyfourteen_credits', 'wgom_footer_timer');
add_action('wp_enqueue_scripts', 'enqueue_parent_theme_style');
add_action('wp_footer', 'wgom_footer');
add_action('wp_head', 'wgom_head');
add_filter('comment_text', 'wgom_filter_oembed_comments', 0);
add_filter('the_content', 'wgom_filter_add_ratings');

add_action('comment_form_after', 'wgom_filter_show_top_videos');
add_action('comment_form_comments_closed', 'wgom_filter_show_top_videos');

require get_stylesheet_directory() . '/inc/featured-content.php';
