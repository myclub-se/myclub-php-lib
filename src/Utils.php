<?php

namespace MyClub\Common;

if ( !defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use DateTime;
use DateTimeZone;
use Exception;

/**
 * A utility class for managing URLs, cache, and posts in a WordPress environment.
 */
class Utils
{
    /**
     * Change the host name in a given URL to match the host name of the WordPress site.
     *
     * @param string $oldUrl The URL with the host name to be changed.
     *
     * @return string The modified URL with the updated host name.
     *
     * @since 1.0.0
     */
    static function changeHostName( string $oldUrl ): string
    {
        $host_url_parts = wp_parse_url( home_url() );
        $old_url_parts = wp_parse_url( $oldUrl );

        $scheme = isset( $host_url_parts[ 'scheme' ] ) ? $host_url_parts[ 'scheme' ] . '://' : '';
        $host = $host_url_parts[ 'host' ];

        $port = isset( $old_url_parts[ 'port' ] ) ? ':' . $old_url_parts[ 'port' ] : '';
        $path = isset( $old_url_parts[ 'path' ] ) ? $old_url_parts[ 'path' ] : '';
        $query = isset( $old_url_parts[ 'query' ] ) ? '?' . $old_url_parts[ 'query' ] : '';

        return $scheme . $host . $port . $path . $query;
    }

    /**
     * Clears the cache for a specific page or post based on the detected caching plugin.
     *
     * @param int $post_id The ID of the post or page whose cache needs to be cleared.
     *
     * @return bool True if the cache was successfully cleared, false if no supported caching plugin was detected or
     * an error occurred.
     */
    static function clearCacheForPage( int $post_id ): bool
    {
        $cache_plugin = Utils::detectCachePlugin();

        try {
            switch ( $cache_plugin ) {
                case 'breeze':
                    do_action( 'breeze_clear_post_cache', $post_id );
                    return true;

                case 'cache_enabler':
                    do_action( 'cache_enabler_clear_page_cache_by_post', $post_id );
                    return true;


                case 'hummingbird':
                    do_action( 'wphb_clear_post_cache', $post_id );
                    return true;

                case 'hyper_cache':
                    if ( function_exists( 'hyper_cache_clean_page' ) ) {
                        hyper_cache_clean_page( $post_id );
                    }
                    return true;
                case 'litespeed_cache':
                    do_action( 'litespeed_purge_post', $post_id );
                    return true;

                case 'siteground_optimizer':
                    if ( function_exists( 'sg_cachepress_purge_cache' ) ) {
                        sg_cachepress_purge_cache( $post_id );
                    }
                    return true;

                case 'swift_performance':
                    if ( function_exists( 'swift_performance_cache_clean' ) ) {
                        swift_performance_cache_clean( $post_id );
                    }
                    return true;

                case 'wp_fastest_cache':
                    if ( function_exists( 'wpfc_clear_post_cache_by_id' ) ) {
                        wpfc_clear_post_cache_by_id( $post_id );
                    }
                    return true;

                case 'wp_optimize':
                    if ( function_exists( 'new_woocache_purge_enabled' ) ) {
                        new_woocache_purge_enabled( $post_id );
                    }
                    return true;

                case 'wp_rocket':
                    if ( function_exists( 'rocket_clean_post' ) ) {
                        rocket_clean_post( $post_id ); // Clean cache for this post
                    }
                    return true;

                case 'wp_super_cache':
                    if ( function_exists( 'wp_cache_post_change' ) ) {
                        wp_cache_post_change( $post_id );
                    }
                    return true;

                case 'w3_total_cache':
                    if ( function_exists( 'w3tc_flush_post' ) ) {
                        w3tc_flush_post( $post_id );
                    }
                    return true;

                case 'nitropack':
                    if ( function_exists( 'nitropack_purge_post' ) ) {
                        nitropack_purge_post( $post_id );
                        return true;
                    }
                    if ( function_exists( 'nitropack_purge_url' ) ) {
                        $url = get_permalink( $post_id );
                        if ( $url ) {
                            nitropack_purge_url( $url );
                            return true;
                        }
                    }
                    return false;

                case 'memcached_cache':
                case 'redis_cache':
                    if ( function_exists( 'wp_cache_delete' ) ) {
                        wp_cache_delete( $post_id );
                    }
                    return true;

                default:
                    // No caching plugin detected or not supported
                    return false;
            }
        } catch ( \Throwable $e ) {
            error_log( 'Exception caught in clear_cache_for_page: ' . $e->getMessage() );
            return false;
        }
    }

    /**
     * Detects the active caching plugin by checking the list of active plugins.
     *
     * @return string|false The identifier of the detected caching plugin ('wp_super_cache', 'w3_total_cache',
     * 'wp_rocket', or 'litespeed_cache'), or false if no supported caching plugin is detected.
     * @since 1.0.0
     */
    static function detectCachePlugin()
    {
        // Use unique identifiers for each plugin (classes, functions, or constants)
        if ( class_exists( 'WP_Super_Cache' ) || defined( 'WPCACHEHOME' ) ) {
            return 'wp_super_cache';
        } elseif ( class_exists( 'W3TC' ) || defined( 'W3TC' ) ) {
            return 'w3_total_cache';
        } elseif ( class_exists( 'RocketLazyLoad' ) || defined( 'WP_ROCKET_VERSION' ) ) {
            return 'wp_rocket';
        } elseif ( class_exists( 'LiteSpeed_Cache' ) || defined( 'LSCWP_V' ) ) {
            return 'litespeed_cache';
        } elseif ( class_exists( 'WpFastestCache' ) || defined( 'WPFC_MAIN_PATH' ) ) {
            return 'wp_fastest_cache';
        } elseif ( class_exists( 'Cache_Enabler' ) || defined( 'CE_PLUGIN_FILE' ) ) {
            return 'cache_enabler';
        } elseif ( class_exists( 'HyperCache' ) || defined( 'HYPER_CACHE_DIR' ) ) {
            return 'hyper_cache';
        } elseif ( class_exists( 'Breeze\Cache' ) || defined( 'BREEZE_VERSION' ) ) {
            return 'breeze';
        } elseif ( class_exists( 'Swift_Performance' ) || defined( 'SWIFT_PERFORMANCE_ACTIVATE' ) ) {
            return 'swift_performance';
        } elseif ( class_exists( 'SiteGround_Optimizer\Options' ) || defined( 'SG_CACHEPRESS_ENV' ) ) {
            return 'siteground_optimizer';
        } elseif ( class_exists( 'Hummingbird\WP_Hummingbird' ) || defined( 'WPHB_VERSION' ) ) {
            return 'hummingbird';
        } elseif ( class_exists( 'WP_Optimize' ) || defined( 'WP_OPTIMIZE_VERSION' ) ) {
            return 'wp_optimize';
        } elseif ( class_exists( 'RedisObjectCache' ) || defined( 'WP_REDIS_VERSION' ) ) {
            return 'redis_cache';
        } elseif ( class_exists( 'Memcached\Backend' ) || defined( 'WP_MEMCACHED_VERSION' ) ) {
            return 'memcached_cache';
        } elseif ( class_exists( 'NitroPack\Integration' ) || defined( 'NITROPACK_VERSION' ) ) {
            return 'nitropack';
        } elseif ( file_exists( WP_CONTENT_DIR . '/advanced-cache.php' ) ) {
            return 'advanced_cache';
        }

        // Default return false if no cache plugin is detected
        return false;
    }

    /**
     * Formats a given UTC time to the format specified in WordPress options.
     *
     * @param string|int $utc_time The UTC time to format.
     *
     * @return string The formatted date/time string.
     * @since 1.0.0
     */
    static function formatDateTime( $utc_time ): string
    {
        try {
            // Retrieve the timezone string from WordPress options
            $timezone_string = wp_timezone_string();
            if ( !$timezone_string ) {
                $timezone_string = 'Europe/Stockholm';
            }
            $timezone = new DateTimeZone( $timezone_string );

            // Create DateTime object for last sync, correct it to WordPress timezone
            $date_time = new DateTime( $utc_time, new DateTimeZone( 'UTC' ) );
            $date_time->setTimezone( $timezone );

            // Format the date/time string according to your requirements
            $formatted_time = wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $date_time->getTimestamp() );

        } catch ( Exception $e ) {
            $formatted_time = wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $utc_time );
        }

        return $formatted_time;
    }

    /**
     * Sanitize an array by recursively sanitizing text fields.
     *
     * @param array $array The array to be sanitized.
     *
     * @return array The sanitized array.
     *
     * @since 1.0.0
     */
    static function sanitizeArray( array $array ): array
    {
        foreach ( $array as $key => &$value ) {
            if ( is_array( $value ) ) {
                $value = Utils::sanitizeArray( $value );
            } else {
                $value = sanitize_text_field( $value );
            }
        }

        return $array;
    }

    /**
     * Updates an existing option or creates a new one in the WordPress database.
     *
     * @param string $option_name The name of the option to update or create.
     * @param mixed $value The value to store for the option.
     * @param string $autoload Optional. Whether to autoload this option. Default 'yes'.
     * @param bool $check_same Optional. Whether to check if the current value matches the new value before updating. Default false.
     *
     * @return bool True if the option was added or updated, false if $check_same is true and the current value matches the new value.
     *
     * @since 1.0.0
     */
    static function updateOrCreateOption( string $option_name, $value, string $autoload = 'yes', bool $check_same = false ): bool
    {
        $current_value = get_option( $option_name, 'non-existent' );

        if ( $check_same && $current_value === $value ) {
            return true;
        }

        if ( get_option( $option_name, 'non-existent' ) === 'non-existent' ) {
            add_option( $option_name, $value, '', $autoload );
        } else {
            update_option( $option_name, $value, $autoload );
        }

        return false;
    }
}