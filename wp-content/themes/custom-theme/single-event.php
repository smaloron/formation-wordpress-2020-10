<?php
get_header();
?>

<main id="site-content" role="main" class="container">

    <div class="row justify-content-center">
        <?php

        if (have_posts()) {

            while (have_posts()) {
                the_post();

                get_template_part('template-parts/event-details');
            }
        }

        ?>
    </div>
</main><!-- #site-content -->


<?php get_footer(); ?>