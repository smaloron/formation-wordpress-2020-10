<?php
/**
 * La configuration de base de votre installation WordPress.
 *
 * Ce fichier est utilisé par le script de création de wp-config.php pendant
 * le processus d’installation. Vous n’avez pas à utiliser le site web, vous
 * pouvez simplement renommer ce fichier en « wp-config.php » et remplir les
 * valeurs.
 *
 * Ce fichier contient les réglages de configuration suivants :
 *
 * Réglages MySQL
 * Préfixe de table
 * Clés secrètes
 * Langue utilisée
 * ABSPATH
 *
 * @link https://fr.wordpress.org/support/article/editing-wp-config-php/.
 *
 * @package WordPress
 */

// ** Réglages MySQL - Votre hébergeur doit vous fournir ces informations. ** //
/** Nom de la base de données de WordPress. */
define( 'DB_NAME', 'wp_intro' );

/** Utilisateur de la base de données MySQL. */
define( 'DB_USER', 'root' );

/** Mot de passe de la base de données MySQL. */
define( 'DB_PASSWORD', '' );

/** Adresse de l’hébergement MySQL. */
define( 'DB_HOST', 'localhost' );

/** Jeu de caractères à utiliser par la base de données lors de la création des tables. */
define( 'DB_CHARSET', 'utf8mb4' );

/**
 * Type de collation de la base de données.
 * N’y touchez que si vous savez ce que vous faites.
 */
define( 'DB_COLLATE', '' );

/**#@+
 * Clés uniques d’authentification et salage.
 *
 * Remplacez les valeurs par défaut par des phrases uniques !
 * Vous pouvez générer des phrases aléatoires en utilisant
 * {@link https://api.wordpress.org/secret-key/1.1/salt/ le service de clés secrètes de WordPress.org}.
 * Vous pouvez modifier ces phrases à n’importe quel moment, afin d’invalider tous les cookies existants.
 * Cela forcera également tous les utilisateurs à se reconnecter.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',         '5,x(<4Es&!y.KF6d]AQ{w~I(-ckx!):>:dBilsMaaGx)wZKDJ8V-5x;<0h./Dr6x' );
define( 'SECURE_AUTH_KEY',  ')HR-SfoJ)ti4c:q=*4T(DqJC#=v7SAG+lQ]WY1)[z7LK@<Aj:p0e|:ZdYZiuw!~;' );
define( 'LOGGED_IN_KEY',    '42QaNI%KPRsSj#qoCJvR|nB<}kbqn..(I?S8i%>wYxY!G0P6j8S[v:$o&m8zX#h0' );
define( 'NONCE_KEY',        'xMlA@uz~)I9|fPrfv/NqOd[H|Y[RmeIu-R?HC6f#mgB#=hDK|R:Gz})vL`qgCCt]' );
define( 'AUTH_SALT',        'FaC^Yip8neSdzeGLw!Oj1HOq(yX5GGR~kLrW8n/fxd9}]#,{Or&^#F8k2Kp9h{]P' );
define( 'SECURE_AUTH_SALT', '8 3%Bfb;, gFCo#bLdXz1PI-PVL+;zn|a@F<:^$f-Qbf!@,=B[(`?F?D;j5vYPY4' );
define( 'LOGGED_IN_SALT',   '2*/S2rO)6 -xo{K^A0a5akcrGW3G/_KQM.B(yi[Q>;Rj5,iy4r[>lW06 d,RAu7=' );
define( 'NONCE_SALT',       'G[/$Le~L7(7X{a;8fi<#)IkRx}Q6k3VDfP?A%v/Iyq`DmV*RyVR&:- :k|:4J/+H' );
/**#@-*/

/**
 * Préfixe de base de données pour les tables de WordPress.
 *
 * Vous pouvez installer plusieurs WordPress sur une seule base de données
 * si vous leur donnez chacune un préfixe unique.
 * N’utilisez que des chiffres, des lettres non-accentuées, et des caractères soulignés !
 */
$table_prefix = 'wp_';

/**
 * Pour les développeurs : le mode déboguage de WordPress.
 *
 * En passant la valeur suivante à "true", vous activez l’affichage des
 * notifications d’erreurs pendant vos essais.
 * Il est fortemment recommandé que les développeurs d’extensions et
 * de thèmes se servent de WP_DEBUG dans leur environnement de
 * développement.
 *
 * Pour plus d’information sur les autres constantes qui peuvent être utilisées
 * pour le déboguage, rendez-vous sur le Codex.
 *
 * @link https://fr.wordpress.org/support/article/debugging-in-wordpress/
 */
define( 'WP_DEBUG', false );

/* C’est tout, ne touchez pas à ce qui suit ! Bonne publication. */

/** Chemin absolu vers le dossier de WordPress. */
if ( ! defined( 'ABSPATH' ) )
  define( 'ABSPATH', dirname( __FILE__ ) . '/' );

/** Réglage des variables de WordPress et de ses fichiers inclus. */
require_once( ABSPATH . 'wp-settings.php' );
