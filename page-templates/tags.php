<?php
/**
 * Template Name: Tags
 *
 * @package WordPress
 * @subpackage Twenty_Fourteen
 * @since Twenty Fourteen 1.0
 */

get_header(); ?>

<div id="main-content" class="main-content">

<?php
	if ( is_front_page() && twentyfourteen_has_featured_posts() ) {
		// Include the featured content template.
		get_template_part( 'featured-content' );
	}
?>

	<div id="primary" class="content-area">
		<div id="content" class="site-content" role="main">
			<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
				<?php
					the_title( '<header class="entry-header"><h1 class="entry-title">', '</h1></header><!-- .entry-header -->' );
				?>
				<div class="entry-content">
					<?php

					wp_list_categories(array(
						'orderby' => 'name',
						'show_count' => true,
						'hierarchical' => true,
						'number' => null,
						'taxonomy' => 'post_tag',
					));

					edit_post_link( __( 'Edit', 'twentyfourteen' ), '<footer class="entry-meta"><span class="edit-link">', '</span></footer>' );
					?>
				</div>
			</article><!-- #post-## -->
		</div><!-- #content -->
	</div><!-- #primary -->
</div><!-- #main-content -->

<?php
get_sidebar();
get_footer();
