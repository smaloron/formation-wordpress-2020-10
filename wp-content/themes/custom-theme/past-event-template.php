<?php
get_header();
?>

<?php
/*
Template Name: anciennes conférences
*/
?>
<main id="site-content" role="main" class="container">

    <div class="row justify-content-center">
        <h1>Liste de nos conférences</h1>
        <?php

        $today = date('Y-m-d H:i');
        $lastEvents = new WP_Query([
            'post_type' => 'event',
            'posts_per_page' => -1,
            'meta_key' => 'conference_date',
            'orderby' => 'meta_value',
            'order' => 'ASC',
            'meta_query' => [
                [
                    'key' => 'conference_date',
                    'compare' => '<',
                    'value' => $today,
                ]
            ]
        ]);

        if ($lastEvents->have_posts()) {

            while ($lastEvents->have_posts()) {
                $lastEvents->the_post();

                get_template_part('template-parts/event-item');
            }
        }

        ?>
    </div>
</main><!-- #site-content -->


<?php get_footer(); ?>