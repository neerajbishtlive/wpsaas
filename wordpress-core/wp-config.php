<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the web site, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * Localized language
 * * ABSPATH
 *
 * @link https://wordpress.org/support/article/editing-wp-config-php/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'wp_saas_control' );

/** Database username */
define( 'DB_USER', 'root' );

/** Database password */
define( 'DB_PASSWORD', 'Bhunee@@1315' );

/** Database hostname */
define( 'DB_HOST', '127.0.0.1' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication unique keys and salts.
 *
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',          'Kg/`k^Zr( yk61s*vLv3o--D1=dV1C%W10K/EBN`+7AH[h?)BgwlW=0EFhn.Qz-0' );
define( 'SECURE_AUTH_KEY',   'GP>rT)OmG(Pn}m9[@Z`vl=N>)grNHJb(u.]P],4kR4L5Nt6tm${vnrD}KzhXslx#' );
define( 'LOGGED_IN_KEY',     '<TDS]@bH;zS+OGHFM9VAt_)Ky4raL^hsp&P.`=DXd~p9$>LIV^{[*ox{dOO /Qf3' );
define( 'NONCE_KEY',         'yoHyo*Ux p`vSFZnB/+vHFpqTTtpd`Fl V)Rk+KZ4dW2{o3 #%T^HQWO~*A>[(pD' );
define( 'AUTH_SALT',         'nC01XJe*X9P7SOMZqY3=ITDAquAQdgt19?8X)iCh0.DT,0xnuFj[>ZovpS}IlDZt' );
define( 'SECURE_AUTH_SALT',  'E|FeJqtk Z#($D>5sGISig0}D!h,Wa*o?$#xSN[w `:lV7a,%9snyqEZau%UkP p' );
define( 'LOGGED_IN_SALT',    '6oAbm>3y)9`7z|?!I~/e5DKs)C!eQ:s2U[yG2Me[Gy7;gTO#u4$(ME&um;fsn8V9' );
define( 'NONCE_SALT',        'Rs,8O5eMg[70_PX:[rzBsA:T@qx]37%UL8kOWXunEsSqY/KRD^uQg`uKuU,1>0Nh' );
define( 'WP_CACHE_KEY_SALT', 'dV:JR9(o0[=asOD3`AZ7.>mc-;1JH K@SDE~r46J#IP>1&1fUuofjGL85*q!hDQk' );


/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'wp_wpclitest_';


/* Add any custom values between this line and the "stop editing" line. */



/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://wordpress.org/support/article/debugging-in-wordpress/
 */
if ( ! defined( 'WP_DEBUG' ) ) {
	define( 'WP_DEBUG', false );
}

/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
