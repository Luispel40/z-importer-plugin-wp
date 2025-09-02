<?php
/**
 * Template Name: HTML Zip Template
 */

if (have_posts()) :
    while (have_posts()) : the_post();
        $content = get_post_field('post_content', get_the_ID()); // Conteúdo puro
        echo $content; // Já vem com HTML do index.html
    endwhile;
endif;

get_footer();