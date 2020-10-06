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

        <!-- liste de formations associées -->
        <div class="col-8">
            <?php 
                $relatedPrograms = get_field('related_programs');
                if ($relatedPrograms):
            ?>
                <h3>Les formations concernées</h3>
                <ul class="list-group mb-3">
                    <?php foreach($relatedPrograms as $program): ?>
                        <li class="list-group-item">
                            <a href="<?= get_the_permalink($program) ?>">
                                <?= get_the_title($program) ?>
                            </a>
                        </li>
                    <?php endforeach ?>
                </ul>
            <?php endif ?>
        </div>

    </div>
</main><!-- #site-content -->


<?php get_footer(); ?>