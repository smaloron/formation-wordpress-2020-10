<?php 
/*
Template Name: Book template
*/

get_header() 

?>

<style>

    .box {
        display: flex;
        padding: 15px;
        box-shadow: 5px 5px 3px black;
        background-color: white;
    }

</style>

<div class="content">
    <h3>Détail du livre </h3>
    <?php the_post() ?>
    <h1>
    <?php the_title() ?>
    </h1>

    <div class="box">
        <div style="flex: 1 1 20%">
            <p>par <?php the_author() ?></p>
            <p>le <?php the_date() ?></p>
            <p> nombre de pages : <?php the_field("nombre_de_pages") ?> </p>
            <p> auteur : <?php the_field("auteur") ?> </p>
            <p> Difficulté : <?php the_field("difficulte") ?> </p>
            <img src="<?php the_field("couverture")?>" style="width: 100%" >
            <?php var_dump(the_field("couverture")) ?>

        </div>

        <div style="flex: 1 1 80%">
            <?php the_content() ?>
        </div>
    </div>
</div>

<?php get_footer() ?>