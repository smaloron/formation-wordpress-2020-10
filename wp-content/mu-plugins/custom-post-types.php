<?php

/**********************************
 *  Création d'un type post perso
 ***********************************/

function setCustomPostTypes()
{
    register_post_type('event', [
        'public' => true,
        'labels' => [
            'name' => 'Conférences',
            'all_items' => 'Toutes les conférences',
            'add_new_item' => 'Nouvelle conférence',
            'edit_item' => 'Modifier la conférence',
            'singular_name' => 'Conférence'
        ],
        'menu_icon' => 'dashicons-calendar-alt',
        'show_in_rest' => true,
        'has_archive' => true,
        'rewrite' => [
            'slug' => 'conferences'
        ],
        'supports' => ['title', 'editor', 'excerpt']
    ]);

    register_post_type('program', [
        'public' => true,
        'labels' => [
            'name' => 'Programmes de formation',
            'all_items' => 'Toutes les formations',
            'add_new_item' => 'Nouvelle formation',
            'edit_item' => 'Modifier la formation',
            'singular_name' => 'Programme de formation'
        ],
        'menu_icon' => 'dashicons-welcome-learn-more',
        'show_in_rest' => true,
        'has_archive' => true,
        'rewrite' => [
            'slug' => 'formations'
        ],
    ]);

    register_post_type('trainer', [
        'public' => true,
        'labels' => [
            'name' => 'Formateurs',
            'all_items' => 'Tous les formateurs',
            'add_new_item' => 'Nouveau formateur',
            'edit_item' => 'Modifier le formateur',
            'singular_name' => 'Formateur'
        ],
        'menu_icon' => 'dashicons-smiley',
        'show_in_rest' => true,
        'has_archive' => true,
        'rewrite' => [
            'slug' => 'formateurs'
        ],
    ]);
}

add_action('init', 'setCustomPostTypes');

/****************
 *  Personnalisation du lien en savoir plus
 ****************/

function getMoreInfosLink()
{
    global $post;
    return '<a href="' . get_permalink($post->ID) . '"> En savoir plus </a>';
}

add_filter('excerpt_more', 'getMoreInfosLink');