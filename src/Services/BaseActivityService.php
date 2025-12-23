<?php

namespace MyClub\Common\Services;

if ( !defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use stdClass;

/**
 * Class BaseActivityService
 *
 * A service responsible for managing activities related to posts and club calendars. It provides methods
 * to create, update, delete, and retrieve activities stored in a database table. It also allows listing of
 * activities in various contexts like club calendars or specific post associations.
 */
class BaseActivityService
{
    protected static $wpdb;
    protected static string $activities_table_name;
    protected static string $activities_link_table_name;

    protected static string $activities_table_suffix = '';
    protected static string $activities_link_table_suffix = '';

    /**
     * Initializes the database connection and sets up the table name for activities.
     *
     * @return void
     * @since 1.0.0
     */
    public static function init()
    {
        global $wpdb;
        static::$wpdb = $wpdb;
        static::$activities_table_name = static::$wpdb->prefix . static::$activities_table_suffix;
        static::$activities_link_table_name = static::$wpdb->prefix . static::$activities_link_table_suffix;
    }

    /**
     * Creates the activities database tables with the specified schema.
     * The table includes fields for activity details such as title, date, time, location, and more.
     * It incorporates constraints like unique identification, foreign keys, and default values.
     *
     * @return void
     * @since 1.0.0
     */
    static function createActivityTables()
    {
        $charset_collate = static::$wpdb->get_charset_collate();

        $activities_table = static::$activities_table_name;
        $activities_link_table = static::$activities_link_table_name;
        $posts_table = static::$wpdb->prefix . 'posts';

        $activities_table_sql = "
        CREATE TABLE {$activities_table} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            uid varchar(20) NOT NULL UNIQUE,
            section_id varchar(20) DEFAULT NULL,
            show_on_club_calendar tinyint(1) DEFAULT 0,
            title varchar(255) DEFAULT '',
            day DATE NOT NULL,
            start_time TIME NOT NULL,
            end_time TIME NOT NULL,
            location varchar(255) DEFAULT '',
            description TEXT DEFAULT '',
            calendar_name varchar(255) DEFAULT '',
            type varchar(255) DEFAULT '',
            base_type varchar(255) DEFAULT '',
            meet_up_time int(11) DEFAULT NULL,
            meet_up_place varchar(255) DEFAULT '',
            PRIMARY KEY (id)
        ) {$charset_collate};
    ";

        $activities_link_table_sql = "
        CREATE TABLE {$activities_link_table} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            post_id BIGINT UNSIGNED NOT NULL,
            activity_uid varchar(20) NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY unique_post_activity (post_id, activity_uid),
            FOREIGN KEY (post_id) REFERENCES {$posts_table}(ID) ON DELETE CASCADE,
            FOREIGN KEY (activity_uid) REFERENCES {$activities_table}(uid) ON DELETE CASCADE
        ) {$charset_collate};
    ";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        dbDelta( $activities_table_sql );
        dbDelta( $activities_link_table_sql );
    }

    /**
     * Deletes the activity tables from the database if it exists.
     *
     * @return void
     * @since 1.0.0
     */
    static function deleteActivityTables()
    {
        $link_table_name = static::$activities_link_table_name;
        $table_name = static::$activities_table_name;

        static::$wpdb->query( "DROP TABLE IF EXISTS {$link_table_name}" );
        static::$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );
    }

    /**
     * Adds an activity to a specified post in the database if it does not already exist.
     *
     * @param int $post_id The ID of the post to associate the activity with.
     * @param string $activity_id The unique identifier of the activity to be added.
     * @return bool Returns true if the activity was successfully added, or false if it already exists.
     * @since 1.0.0
     */
    static function addActivityToPost( int $post_id, string $activity_id ): bool
    {
        $table_name = static::$activities_link_table_name;

        $existing_row = static::$wpdb->get_row(
            static::$wpdb->prepare(
                "SELECT id FROM {$table_name} WHERE post_id = %d AND activity_uid = %s",
                $post_id,
                $activity_id
            )
        );

        if ( $existing_row ) {
            return false;
        }

        static::$wpdb->insert( $table_name, [
            'post_id'      => $post_id,
            'activity_uid' => $activity_id
        ] );

        return true;
    }

    /**
     * Creates or updates an activity record in the database. If the activity exists, it updates its fields;
     * otherwise, it creates a new record.
     *
     * @param stdClass $activity Object containing the activity details such as title, day, start time, end time, etc.
     * @param array $calendar_array Optional array containing properties like 'show_on_club_calendar' for visibility settings.
     *
     * @return bool Returns true if a new activity is created or the activity is updated with changes. Returns false if there are no changes and the activity already exists.
     * @since 1.0.0
     */
    static function createOrUpdateActivity( stdClass $activity, array $calendar_array = [] ): bool
    {
        $data = [
            'uid'           => $activity->uid,
            'section_id'    => $activity->section_id,
            'title'         => $activity->title,
            'day'           => $activity->day,
            'start_time'    => $activity->start_time,
            'end_time'      => $activity->end_time,
            'location'      => $activity->location,
            'description'   => htmlspecialchars( str_replace( "\n", "<br", $activity->description ), ENT_QUOTES, 'UTF-8' ),
            'calendar_name' => $activity->calendar_name,
            'type'          => $activity->type,
            'base_type'     => $activity->base_type,
            'meet_up_time'  => $activity->meet_up_time,
            'meet_up_place' => $activity->meet_up_place,
        ];

        if ( array_key_exists( "show_on_club_calendar", $calendar_array ) ) {
            $data[ 'show_on_club_calendar' ] = $calendar_array[ 'show_on_club_calendar' ];
        }

        $activity_row = static::getActivity( $activity->uid );

        if ( !$activity_row ) {
            static::$wpdb->insert( static::$activities_table_name, $data );
            unset( $data );
            $update = true;
        } else {
            $compared_values = array_filter( [
                "section_id",
                "title",
                "description",
                "day",
                "start_time",
                "end_time",
                "location",
                "calendar_name",
                "type",
                "base_type",
                "meet_up_time",
                "meet_up_place",
                "show_on_club_calendar"
            ], function ( $key ) use ( $activity, $activity_row ) {
                return isset( $activity->{$key}, $activity_row->{$key} ) && $activity->{$key} !== $activity_row->{$key};
            } );
            static::$wpdb->update( static::$activities_table_name, $data, [ 'uid' => $activity->uid ] );
            $update = !empty( $compared_values );
            unset( $activity_row, $data, $compared_values );
        }

        if ( property_exists( $activity, 'post_id' ) ) {
            $update = static::addActivityToPost( $activity->post_id, $activity->uid ) || $update;
        }

        return $update;
    }

    /**
     * Deletes an activity from the database based on the provided activity ID.
     *
     * @param string $activity_id The unique identifier of the activity to be deleted.
     * @return void
     * @since 1.0.0
     */
    static function deleteActivity( string $activity_id )
    {
        static::$wpdb->delete( static::$activities_table_name, [ 'uid' => $activity_id ] );
    }

    static function removeActivityFromPost( int $post_id, string $activity_id )
    {
        static::$wpdb->delete( static::$activities_link_table_name, [ 'post_id'      => $post_id,
                                                                      'activity_uid' => $activity_id
        ] );

        if ( count( static::listActivityPostIds( $activity_id ) ) === 0 ) {
            $activity = static::getActivity( $activity_id );
            if ( !$activity->show_on_club_calendar ) {
                static::deleteActivity( $activity_id );
            }
        }
    }

    /**
     * Retrieves an activity from the database based on the provided activity ID.
     *
     * @param string $activity_id The unique identifier of the activity to retrieve.
     * @return object|null An object containing the activity data or null if no activity is found.
     * @since 1.0.0
     */
    static function getActivity( string $activity_id ): ?object
    {
        $table_name = static::$activities_table_name;

        return static::$wpdb->get_row(
            static::$wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE uid = %s",
                $activity_id
            )
        );
    }

    /**
     * Retrieves a list of club activities to display on the club calendar. Each activity is included only if it is marked to be shown on the club calendar.
     *
     * @return array|object|null A list of activities that should be shown on the club calendar, ordered by day, start time, and end time.
     * @since 1.0.0
     */
    static function listClubActivities()
    {
        $table_name = static::$activities_table_name;

        return static::$wpdb->get_results(
            "SELECT * FROM {$table_name} WHERE show_on_club_calendar = 1 ORDER BY day, start_time, end_time",
        );
    }

    /**
     * Retrieves a list of activity IDs that are marked to be shown on the club calendar.
     *
     * @return array|object|null An array of activity IDs that are set to be visible on the club calendar.
     * @since 1.0.0
     */
    static function listClubActivityIds()
    {
        $table_name = static::$activities_table_name;

        return static::$wpdb->get_col( "SELECT uid FROM {$table_name} WHERE show_on_club_calendar = 1" );
    }


    /**
     * Retrieves a list of activities for a specific section. Activities are filtered based on the provided section ID.
     *
     * @param string $sectionId The ID of the section for which activities should be retrieved.
     * @return array|object|null A list of activities for the specified section, ordered by day, start time, and end time.
     * @since 1.0.0
     */
    static function listSectionActivities( string $sectionId )
    {
        $table_name = static::$activities_table_name;

        return static::$wpdb->get_results(
            static::$wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE section_id = %s ORDER BY day, start_time, end_time",
                $sectionId
            )
        );
    }

    /**
     * Retrieves a list of activity IDs associated with the specified section.
     *
     * @param string $sectionId The unique identifier of the section for which to fetch activity IDs.
     * @return array An array of activity IDs corresponding to the given section.
     * @since 1.0.0
     */
    static function listSectionActivityIds( string $sectionId ): array
    {
        $table_name = static::$activities_table_name;

        return static::$wpdb->get_col(
            static::$wpdb->prepare(
                "SELECT uid FROM {$table_name} WHERE section_id = %s",
                $sectionId
            )
        );
    }

    /**
     * Retrieves a list of activities associated with a specific post from the database.
     *
     * @param int $post_id The unique identifier of the post for which activities are to be listed.
     * @return array|object|null A list of activities that are connected to the post, ordered by day, start time, and end time.
     * @since 2.0.0
     */
    static function listPostActivities( int $post_id ): array
    {
        $table_name = static::$activities_table_name;
        $link_table_name = static::$activities_link_table_name;

        return static::$wpdb->get_results(
            static::$wpdb->prepare(
                "SELECT a.* FROM {$table_name} a INNER JOIN {$link_table_name} pa ON a.uid = pa.activity_uid WHERE pa.post_id = %d ORDER BY day, start_time, end_time",
                $post_id
            )
        );
    }

    /**
     * Retrieves a list of activity IDs associated with a specific post.
     *
     * @param int $post_id The unique identifier of the post for which activity IDs are retrieved.
     * @return array|object|null An array of activity IDs related to the specified post.
     * @since 2.0.0
     */
    static function listPostActivityIds( int $post_id )
    {
        $link_table_name = static::$activities_link_table_name;

        return static::$wpdb->get_col(
            static::$wpdb->prepare(
                "SELECT activity_uid FROM {$link_table_name} WHERE post_id = %d",
                $post_id
            )
        );
    }

    /**
     * Retrieves a list of post IDs associated with a specific activity id.
     *
     * @param string $activity_id The unique identifier of the activity for which post ids are retrieved.
     * @return array|object|null An array of post IDs related to the specified post.
     * @since 2.0.0
     */
    static function listActivityPostIds( string $activity_id )
    {
        $link_table_name = static::$activities_link_table_name;

        return static::$wpdb->get_col(
            static::$wpdb->prepare(
                "SELECT post_id FROM {$link_table_name} WHERE activity_uid = %s",
                $activity_id
            )
        );
    }
}