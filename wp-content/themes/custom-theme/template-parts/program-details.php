<article class="col-md-8 p-3">
    <a href="<?= get_post_type_archive_link('program') ?>">Liste des formations</a>
    <div class="bg-success p-2 m-2 row">
        <h3 class="col-md-8">Formation</h3>
        <p class="col-md-3 bg-danger text-white">
            <?php the_field('sigle') ?> <br>
            <?php the_field('duration') ?> heures
        </p>
        <h1 class="col-12"> <?php the_title() ?> </h1>
    </div>
    <div>
        <?php the_content() ?>
    </div>
</article>