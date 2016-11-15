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

/*****
 * WGOM featured posts functions.
 */

/*
 * Return the Cup of Coffee, today's video, and two (or more!) featured posts.
 */
function wgom_top_featured_posts($pinned_categories, $featured_tags) {
	// Query for last Cup of Coffee post (catid = 5) and Video post
	// (catid = 22).
	//$pinned_categories = array(5, 22);

	$pinned_categories_sql = implode(', ', $pinned_categories);
	$category_posts = array();
	foreach ($pinned_categories as $catid) {
		$post = get_posts(array(
			'fields'      => 'ids',
			'numberposts' => 1,
			'category'    => $catid,
			'orderby'     => 'post_date',
			'order'       => 'DESC',
		));
		if ($post) {
			$category_posts[] = $post[0];
		}
	}

	// Map the tag names to IDs. The get_posts function needs the tag ID.
	$featured_tag_ids = array();
	// Return sticky post ids if no tag name is set.
	foreach ($featured_tags as $tag_name => $num_posts) {
		$term = get_term_by('name', $tag_name, 'post_tag');
		if ($term) {
			$featured_tag_ids[] = array($term->term_id => $num_posts);
		}
	}

	$featured_tag_posts = array();
	// Query for featured tag posts.
	foreach ($featured_tag_ids as $featured_tag => $num_posts) {
		$featured_tag_post = get_posts( array(
			'fields'      => 'ids',
			'numberposts' => $num_posts,
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
	}

	$pinned_row_ids = array_merge($category_posts, $featured_tag_posts);
	return $pinned_row_ids;
}

/*
 * Return posts with the most recent activity, but aren't in any of the
 * categories or posts to skip.
 */
function wgom_recently_active_posts($number_active_posts, $skip_categories, $skip_post_ids) {
	global $wpdb;
	$cur_featured_ids = implode(',', $skip_post_ids);
	$posts_table = $wpdb->posts;
	$rel_table = $wpdb->term_relationships;
	$tax_table = $wpdb->term_taxonomy;
	$comments_table = $wpdb->comments;
	$top_cats = implode(', ', $skip_categories);
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
		LIMIT $number_active_posts
	";

	$active_res = $wpdb->get_results($get_active_posts, ARRAY_N);
	$active_row_ids = array();
	foreach ($active_res as $row) {
		$active_row_ids[] = $row[0];
	}

	return $active_row_ids;
}

/*
 * Return the latest posts that aren't in any of the categories or posts to skip.
 */
function wgom_newest_posts($number_newest_posts, $skip_categories, $skip_post_ids) {
	// Third row: 4 most recent posts not already selected for
	// inclusion. Merge posts so far to remove them from the most
	// recent post listing.
	global $wpdb;
	$cur_featured_ids = implode(',', $skip_post_ids);
	$skip_category_ids = implode(',', $skip_categories);
	$posts_table = $wpdb->posts;
	$rel_table = $wpdb->term_relationships;
	$tax_table = $wpdb->term_taxonomy;
	$get_recent_posts = "
		SELECT DISTINCT $posts_table.ID
		FROM $posts_table
		LEFT JOIN $rel_table ON $posts_table.ID = $rel_table.object_ID
		LEFT JOIN $tax_table ON $rel_table.term_taxonomy_id = $tax_table.term_taxonomy_id
		WHERE post_status = 'publish'
		AND post_type = 'post'
		AND $tax_table.taxonomy = 'category'
		AND $tax_table.term_id NOT IN ($skip_category_ids)
		AND ID NOT IN ($cur_featured_ids)
		ORDER BY post_date DESC
		LIMIT $number_newest_posts
	";

	$recent_res = $wpdb->get_results($get_recent_posts, ARRAY_N);
	$recent_row_ids = array();
	foreach ($recent_res as $row) {
		$recent_row_ids[] = $row[0];
	}
	return $recent_row_ids;
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

/*
 * This function is used in content-featured-post.php.
 */
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
add_action('twentyfourteen_credits', 'wgom_footer_timer');
add_action('wp_enqueue_scripts', 'enqueue_parent_theme_style');
add_action('wp_footer', 'wgom_footer');
add_action('wp_head', 'wgom_head');
add_filter('comment_text', 'wgom_filter_oembed_comments', 0);
add_filter('the_content', 'wgom_filter_add_ratings');

add_action('comment_form_after', 'wgom_filter_show_top_videos');
add_action('comment_form_comments_closed', 'wgom_filter_show_top_videos');

require get_stylesheet_directory() . '/inc/featured-content.php';
