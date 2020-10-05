<?php get_header() ?>

<div class="content">
    <?php the_post() ?>
    <h1>
    <?php the_title() ?>
    </h1>

    <div style="display: flex">
        <div style="flex: 1 1 20%">
            <p>par <?php the_author() ?></p>
            <p>le <?php the_date() ?></p>
        </div>

        <div style="flex: 1 1 80%">
            <?php the_content() ?>
        </div>
    </div>
</div>

<?php get_footer() ?>