<?php

namespace MyClub\Common\Api;

if ( !defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use stdClass;
use WP_Error;

/**
 * Class RestApi
 *
 * Provides methods to interact with the MyClub backend API.
 */
class RestApi
{
    const MYCLUB_SERVER_API_PATH = 'https://member.myclub.se/api/v3/external/';

    protected string $apiKey;

    protected string $apiKeyOptionName = 'myclub_api_key';

    private bool $multiSite;

    private string $site;

    private string $pluginVersion;

    private string $pluginName;

    /**
     * Constructor for the class.
     *
     * Initializes the object with the provided API key or retrieves the API key from the options if not provided.
     *
     * @param string $apiKey The API key to be used - required.
     * @param string $pluginName The name of the plugin - required.
     * @param string $pluginVersion The version of the plugin - required.
     *
     * @return void
     * @since 1.0.0
     */
    public function __construct( string $apiKey, string $pluginName, string $pluginVersion )
    {
        $this->apiKey = !empty( $apiKey ) ? $apiKey : get_option( $this->apiKeyOptionName );
        $this->pluginName = $pluginName;
        $this->pluginVersion = $pluginVersion;
        $this->multiSite = is_multisite();
        $this->site = get_bloginfo( 'url' );
    }

    /**
     * Loads the club calendar by making an API call to the calendar service.
     *
     * If the API key is not set, the method returns a response with an empty result array and a 401 status code.
     * If an error occurs during the API call, it logs the error, and returns a response with an empty result array and a 500 status code.
     * Otherwise, it returns the decoded response from the API call.
     *
     * @return stdClass|WP_Error The response containing the calendar data. Returns a stdClass object with the result and status code if successful.
     *                           Returns a WP_Error object or a stdClass object with an error status if any issue arises.
     * @since 1.0.0
     */
    public function loadClubCalendar()
    {
        $service_path = 'calendar/';

        if ( empty( $this->apiKey ) ) {
            $return_value = new stdClass();
            $return_value->result = [];
            $return_value->status = 401;
            return $return_value;
        }

        $decoded = $this->get( $service_path, [ 'limit'   => "null", "version" => "2" ] );

        if ( is_wp_error( $decoded ) ) {
            error_log( 'Unable to load club calendar: Error occurred in API call' );
            $return_value = new stdClass();
            $return_value->result = [];
            $return_value->status = 500;
            return $return_value;
        }

        return $decoded;
    }

    /**
     * Retrieves the menu items from the MyClub backend API.
     *
     * @return stdClass The menu items fetched from the API. If the API key is empty, it returns an empty array
     *                   with a status code of 401. If there is an error in the API call, it returns an empty array
     *                   with a status code of 500. Otherwise, it returns the decoded menu items.
     * @since 1.0.0
     */
    public function loadMenuItems()
    {
        $service_path = 'team_menu/';

        if ( empty( $this->apiKey ) ) {
            $return_value = new stdClass();
            $return_value->result = [];
            $return_value->status = 401;
            return $return_value;
        }

        $decoded = $this->get( $service_path );

        if ( is_wp_error( $decoded ) ) {
            error_log( 'Unable to load menu items: Error occurred in API call' );
            $return_value = new stdClass();
            $return_value->result = [];
            $return_value->status = 500;
            return $return_value;
        }

        return $decoded;
    }

    /**
     * Retrieves the menu items for other teams from the MyClub backend API.
     *
     * @return stdClass The other teams menu items fetched from the API. If the API key is empty, it returns an empty array
     *                   with a status code of 401. If there is an error in the API call, it returns an empty array
     *                   with a status code of 500. Otherwise, it returns the decoded menu items for other teams.
     * @since 1.0.0
     */
    public function loadOtherTeams()
    {
        $service_path = 'team_menu/other_teams/';

        if ( empty( $this->apiKey ) ) {
            $return_value = new stdClass();
            $return_value->result = [];
            $return_value->status = 401;
            return $return_value;
        }

        $decoded = $this->get( $service_path, [ 'limit' => "null" ] );

        if ( is_wp_error( $decoded ) ) {
            error_log( 'Unable to load other teams: Error occurred in API call' );
            $return_value = new stdClass();
            $return_value->result = [];
            $return_value->status = 500;
            return $return_value;
        }

        return $decoded;
    }

    /*
     * Retrieve a group from the MyClub backend API.
     *
     * @return stdClass|bool The group fetched from the API. If the API key is empty, it returns false.
     *                        If there is an error in the API call or the status code is not 200, it returns the
     *                        decoded JSON or the WordPress error. Otherwise, it returns the decoded group.
     * @since 1.0.0
     */
    public function loadGroup( $groupId )
    {
        if ( empty( $this->apiKey ) ) {
            return false;
        }

        $decoded = $this->get( "teams/$groupId/info/" );
        if ( is_wp_error( $decoded ) || $decoded->status !== 200 ) {
            error_log( 'Unable to load group: Error occurred in API call' );
            return $decoded;
        } else {
            // Load member info
            $members = $this->get( "teams/$groupId/members/", [ "limit" => "null" ] );
            if ( $members->status === 200 ) {
                $decoded->result->members = $members->result->results;

                $activities = $this->get( "teams/$groupId/calendar/", [ "limit"   => "null", "version" => "2" ] );
                if ( $activities->status === 200 ) {
                    $decoded->result->activities = $activities->result->results;
                } else {
                    $return_value = new stdClass();
                    $return_value->result = [];
                    $return_value->status = 500;
                    return $return_value;
                }

                return $decoded;
            } else {
                $return_value = new stdClass();
                $return_value->result = [];
                $return_value->status = 500;
                return $return_value;
            }
        }
    }

    /**
     * Retrieves news items from the MyClub backend API.
     *
     * @param string|null $groupId (Optional) The group ID to filter the news items. If not provided, all news items will be fetched.
     *
     * @return stdClass|bool The news items fetched from the API. If the API key is empty, it returns false.
     *                        If there is an error in the API call or the status code is not 200, it returns the
     *                        decoded JSON or WordPress error. Otherwise, it returns the decoded news items.
     * @since 1.0.0
     */
    public function loadNews( string $groupId = null )
    {
        if ( empty( $this->apiKey ) ) {
            return false;
        }

        $args = [ "limit" => "null" ];
        if ( !is_null( $groupId ) ) {
            $args[ "team" ] = $groupId;
        }

        $decoded = $this->get( "news/", $args );
        if ( is_wp_error( $decoded ) || $decoded->status !== 200 ) {
            error_log( 'Unable to load news: Error occurred in API call' );
        }

        return $decoded;
    }

    /**
     * Sends a GET request to the specified service path with optional parameters.
     *
     * @param string $service_path The path of the service to send the GET request to.
     * @param array $data An optional array of parameters to append to the service path as query parameters.
     * @return stdClass|WP_Error The response from the GET request. If an error occurs during the request, it returns a WP_Error object.
     *                            Otherwise, it returns a stdClass object with the result and status code.
     * @since 1.0.0
     */
    private function get( string $service_path, array $data = [] )
    {
        if ( !empty ( $data ) ) {
            $service_path = $service_path . '?' . http_build_query( $data );
        }
        $response = wp_remote_get( $this->getServerUrl( $service_path ),
            [
                'headers' => $this->createRequestHeaders(),
                'timeout' => 20
            ]
        );

        if ( is_wp_error( $response ) ) {
            error_log( 'Error occurred during API get call, additional info: ' . $response->get_error_message() );
            return $response;
        } else {
            $value = new stdClass();
            $value->result = json_decode( wp_remote_retrieve_body( $response ) );
            $value->status = $response[ 'response' ][ 'code' ];
            return $value;
        }
    }

    /**
     * Retrieves the request headers for an API call.
     *
     * @return array The request headers to be used in an API call. It includes the 'Accept' header set to 'application/json'
     *               and the 'Authorization' header with the value of "Api-Key {API_KEY}". The API key is obtained from the
     *               class property $apiKey.
     * @since 1.0.0
     */
    private function createRequestHeaders(): array
    {
        return [
            'Accept'             => 'application/json',
            'Authorization'      => "Api-Key $this->apiKey",
            'X-MyClub-RestApi'   => $this->pluginName,
            'X-MyClub-MultiSite' => $this->multiSite ? 'true' : 'false',
            'X-MyClub-Site'      => $this->site,
            'X-MyClub-Version'   => $this->pluginVersion,
        ];
    }

    /**
     * Construct the full URL for an API request.
     *
     * @param string $path The path of the API endpoint, which is concatenated to the base server name.
     *
     * @return string The complete URL to be used for the API request.
     * @since 1.0.0
     */
    private function getServerUrl( string $path ): string
    {
        return self::MYCLUB_SERVER_API_PATH . $path;
    }
}