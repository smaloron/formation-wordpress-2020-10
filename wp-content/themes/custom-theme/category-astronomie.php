<?php get_header() ?>

<style>
    .content {
        width: 80%; 
        margin: 5px auto; 
        background-color: black; 
        color: white;
        padding: 15px
    }
</style>

<div class="content">
    <?php 
        // Boucle tant qu'il reste des articles à afficher
        while(have_posts()):
    ?>

    <?php 
        // Récupération de l'article en cours
        the_post() 
    ?>

    <div>
            <h3> 
                <!-- Affichage du titre de l'article  avec un lien vers le détail -->
                <a href="<?php the_permalink() ?>">
                <?php the_title() ?> 
                </a>
            </h3>
            <!-- Affichage du résumé de l'article -->
            <p> <?php the_excerpt() ?> </p>
    </div>

    <?php endwhile; ?>
   
</div>

<?php get_footer() ?>