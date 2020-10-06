<article class="col-md-8 p-3">
    <a href="<?= get_post_type_archive_link('trainer') ?>">Liste des formateurs</a>
    <div class="bg-success p-2 m-2 row">
        <h3 class="col-md-8">Formateur</h3>
        <p class="col-md-4">
            <img src="<?= get_the_post_thumbnail_url($post, 'teacherSquare') ?>" class="img-thumbnail rounded-circle img-fluid">
        </p>
        <h1 class="col-12"> <?php the_title() ?> </h1>
    </div>
    <div>
        <?php the_content() ?>
    </div>
</article>