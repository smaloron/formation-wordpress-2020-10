<?php
// Affichage des programmes de formation
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

        <!-- liste des conférences associées -->
        <div class="col-8">
            <?php
            $relatedEvents = new WP_Query([
                'post_type' => 'event',
                'meta_query' => [
                    [
                        'key' => 'related_programs',
                        'compare' => 'LIKE',
                        'value' => '"' . get_the_ID() . '"'
                    ]
                ]
            ]);

            var_dump($relatedEvents->have_posts());

            if ($relatedEvents->have_posts()) :?>
                <h3>Les Conférences</h3>
                <ul class="list-group mb-3">
                    <?php 

                    while ($relatedEvents->have_posts()) :
                        $relatedEvents->the_post();
                    ?>
                        
                        <li class="list-group-item">
                            <a href="<?= get_the_permalink() ?>">
                                <?php the_title() ?>
                            </a>
                        </li>
                    <?php endwhile ?>
                </ul>
            <?php endif ?>
        </div>

    </div>
</main><!-- #site-content -->


<?php get_footer(); ?>