<article class="col-md-8 p-3">
    <div class="bg-success p-2 m-2 row">
        <h3 class="col-md-8">Conférence</h3>
        <p class="col-md-3 bg-danger text-white">Le : <?php the_field('conference_date') ?></p>
        <h1 class="col-12"> <?php the_title() ?> </h1>
    </div>


    <div>
        <?php the_content() ?>
    </div>
</article>