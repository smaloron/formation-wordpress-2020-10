<?php get_header() ?>

<div class="content">
    <?php the_post() ?>
    <h1>
    <?php the_title() ?>
    </h1>


    <div style="width: 80%; margin: 5px auto; background-color: white; padding: 15px">
        <?php the_content() ?>
    </div>
   
</div>

<?php get_footer() ?>