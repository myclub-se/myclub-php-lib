<?php

namespace MyClub\Common\Services;

if ( !defined( 'ABSPATH' ) ) exit;

use WP_Query;


/**
 * Provides functionality to manage images in the WordPress media library.
 */
class BaseImageService
{
    const MYCLUB_IMAGES = 'myclub-images';

    /**
     * Add a featured image to a post in the WordPress database.
     *
     * @param int $post_id The ID of the post to add the featured image to.
     * @param object|null $image The image information object. Should contain 'raw' property with 'url' property.
     * @param string $prefix Optional. The prefix to be added to the image URL before adding it to the database. Default is an empty string.
     * @param string $caption Optional. The caption to be added to the image. Default is an empty string.
     * @param string $image_type Optional. The image type to be assigned to the image. Default is an empty string.
     * @return void
     * @since 1.0.0
     */
    static function addFeaturedImage( int $post_id, ?object $image, string $prefix = '', string $caption = '', string $image_type = '' ): void
    {
        $attachment_id = null;

        if ( isset( $image ) ) {
            $attachment = BaseImageService::addImage( $image->raw->url, $prefix, $caption, $image_type );
            if ( isset( $attachment ) ) {
                $attachment_id = $attachment[ 'id' ];
            }

            $old_attachment_id = get_post_thumbnail_id( $post_id );

            if ( $attachment_id !== null && $old_attachment_id !== $attachment_id ) {
                if ( $old_attachment_id ) {
                    delete_post_thumbnail( $post_id );
                    wp_delete_attachment( $old_attachment_id, true );
                }

                set_post_thumbnail( $post_id, $attachment_id );
            }
        }
    }

    /**
     * Add an image to the WordPress media library.
     *
     * @param string $image_url The URL of the image to add.
     * @param string $prefix Optional. Prefix to be added to the image filename. Default is an empty string.
     *
     * @return array|null The attachment information of the attachment or null
     *
     * @since 1.0.0
     */
    static function addImage( string $image_url, string $prefix = '', string $caption = '', string $image_type = '' ): ?array
    {
        $attachment_id = null;
        $image = pathinfo( $image_url );

        // Construct sanitized filename
        $name = sanitize_title( $prefix . urldecode( $image[ 'filename' ] ) );
        $filename = $name;
        if ( array_key_exists( 'extension', $image ) ) {
            $filename .= '.' . $image[ 'extension' ];
        }

        // Sanitize the value for _source_image_url meta query comparison
        $meta_value = sanitize_text_field( $prefix . $image_url );

        // *** Step 1: Query for an existing attachment using _source_image_url ***
        $args = array (
            'posts_per_page' => 1,
            'post_type'      => 'attachment',
            'meta_query'     => [
                [
                    'key'     => '_source_image_url',
                    'value'   => $meta_value,
                    'compare' => '='
                ]
            ]
        );

        $query_results = new WP_Query( $args );
        if ( isset( $query_results->posts, $query_results->posts[ 0 ] ) ) {
            $attachment_id = $query_results->posts[ 0 ]->ID;
        } else {
            $args_fallback = array (
                'posts_per_page' => 1,
                'post_type'      => 'attachment',
                'name'           => $name
            );

            $fallback_query = new WP_Query( $args_fallback );
            if ( isset( $fallback_query->posts, $fallback_query->posts[ 0 ] ) ) {
                $attachment_id = $fallback_query->posts[ 0 ]->ID;

                if ( !get_post_meta( $attachment_id, '_source_image_url', true ) ) {
                    update_post_meta( $attachment_id, '_source_image_url', $meta_value );
                }
            } else {
                require_once( ABSPATH . 'wp-admin/includes/file.php' );

                $file = [
                    'name'     => $filename,
                    'tmp_name' => download_url( $image_url )
                ];

                if ( !is_wp_error( $file[ 'tmp_name' ] ) ) {
                    $attachment_id = media_handle_sideload( $file );

                    if ( is_wp_error( $attachment_id ) ) {
                        wp_delete_file( $file[ 'tmp_name' ] );
                        $attachment_id = null;
                    } else {
                        update_post_meta( $attachment_id, '_source_image_url', $meta_value );
                    }
                }
            }
        }

        if ( $attachment_id !== null ) {
            $image_url = null;
            $image_src_array = wp_get_attachment_image_src( $attachment_id, 'medium' );

            if ( $image_src_array ) {
                $image_url = $image_src_array[ 0 ];
            }

            // Assign taxonomy term if available
            if ( $image_type && taxonomy_exists( BaseImageService::MYCLUB_IMAGES ) ) {
                wp_set_object_terms( $attachment_id, $image_type, BaseImageService::MYCLUB_IMAGES, false );
            }

            wp_update_post( array (
                'ID'           => $attachment_id,
                'post_excerpt' => $caption
            ) );

            return [
                'id'  => $attachment_id,
                'url' => $image_url
            ];
        } else {
            return $attachment_id;
        }
    }
}