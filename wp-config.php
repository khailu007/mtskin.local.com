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
 * * ABSPATH
 *
 * @link https://wordpress.org/support/article/editing-wp-config-php/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', "khachhang_wp_mtskin" );

/** Database username */
define( 'DB_USER', "root" );

/** Database password */
define( 'DB_PASSWORD', "d@t@base" );

/** Database hostname */
define( 'DB_HOST', "localhost:3316" );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8mb4' );

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
define( 'AUTH_KEY',         'UL$/f=1QwBhLkhL:vAn8c38csChAv2I5Eu{aq1kB.vi=@;<YRt_nC.lUIk8>yKSP' );
define( 'SECURE_AUTH_KEY',  'i`S|J)6BViNF<l>;l&*@(aLaULMJ(22va.:s>5:NuI*^0WPid4`.NCSy_{&Gj%bd' );
define( 'LOGGED_IN_KEY',    'x:H%_n-[ZEJnt54=0Os%An[E#tg;`?$q}=%TPug#i6$r(.eH#iOMG8FfG[&T|_b`' );
define( 'NONCE_KEY',        '@e38FPmC&hgP/2dE/GDRq0UbVO:bY^N8rxUmIUD3+PW.INu!@WuQs3ymCq@F<x,~' );
define( 'AUTH_SALT',        '4/t`e0e=}/IDy^6Q68<#??4&hbWlKE02eu{lSy.7]|t @]X4?8B,pF`1fB]@Xg,Y' );
define( 'SECURE_AUTH_SALT', '26K_$*.dpf4s;xnrhc7XE-Q:J-YnH#FZ8|>W56Gq{K{vah`+,V}k^<6SN]D8>GRZ' );
define( 'LOGGED_IN_SALT',   'pUv7h2emB-bGzEVmicfVS*7>UQ=}sf8<.m++X+/A!(0]UFELXSIFweAJgV6RDu7=' );
define( 'NONCE_SALT',       'NgDNr@+L4tlTf|oR7@m$:}gA)SK6EWl@e?.@z#<x;dv8}?E^8V&RsjftKq8/Ipy.' );

/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'wp_';

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
define( 'WP_DEBUG', false );

/* Add any custom values between this line and the "stop editing" line. */



define( 'DUPLICATOR_AUTH_KEY', '|%lR/W*k(LgSzy:]t[[+x{p+AM|tx$?o0>dq:OH]t~%]ky*#mvW*zPz8,0$gVQ<f' );
define( 'WP_PLUGIN_DIR', 'D:/xampp7/www/mtskin.local.com/wp-content/plugins' );
define( 'WPMU_PLUGIN_DIR', 'D:/xampp7/www/mtskin.local.com/wp-content/mu-plugins' );
/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname(__FILE__) . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
