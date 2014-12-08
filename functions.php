<?php

add_action('wp_enqueue_scripts', 'enqueue_parent_theme_style');
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

add_action('after_setup_theme', 'wgom_twentyfourteen_setup', 11);

require get_stylesheet_directory() . '/inc/featured-content.php';
