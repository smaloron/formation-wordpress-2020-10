<?php
get_header();
?>

<div id="site-content" class="content">
    <h1>Welcome</h1>

    <!-- requête sur les conférences -->
    <h2 class="text-center">Les prochaines conférences</h2>
    <div class="container">
        <div class="row">
            <?php
            $today = date('Y-m-d H:i');
            $lastEvents = new WP_Query([
                'post_type' => 'event',
                'posts_per_page' => 2,
                'meta_key' => 'conference_date',
                'orderby' => 'meta_value',
                'order' => 'ASC',
                'meta_query' => [
                    [
                        'key' => 'conference_date',
                        'compare' => '>=',
                        'value' => $today,
                        'type' => 'numeric'
                    ]
                ]
            ]);

            while ($lastEvents->have_posts()) {
                $lastEvents->the_post();

                get_template_part('template-parts/event-item');
            }
            ?>
        </div>
    </div>

</div>

<?php get_template_part('template-parts/footer-menus-widgets'); ?>

<?php
get_footer();
?>