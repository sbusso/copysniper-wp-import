<?php require_once ( 'header.php' ); ?>

<?php while ( have_posts() ) : the_post(); ?>
		<?php the_content(); ?>
<?php endwhile; ?>

<?php require_once ( 'footer.php' ); ?>