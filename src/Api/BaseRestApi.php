<?php

namespace MyClub\Common\Api;

if ( !defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use stdClass;
use WP_Error;

/**
 * Class BaseRestApi
 *
 * Provides methods to interact with the MyClub backend API.
 */
class BaseRestApi
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
     * @param string $pluginName The name of the plugin - required.
     * @param string $pluginVersion The version of the plugin - required.
     * @param string|null $apiKey The API key to be used - required.
     *
     * @return void
     * @since 1.0.0
     */
    public function __construct( string $pluginName, string $pluginVersion, string $apiKey = null )
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
        $check_empty_key = $this->checkApiKey();

        if ( !is_null( $check_empty_key ) ) {
            return $check_empty_key;
        }

        $decoded = $this->get( $service_path, [ 'limit'   => "null",
                                                "version" => "2"
        ] );

        if ( is_wp_error( $decoded ) ) {
            error_log( 'Unable to load club calendar: Error occurred in API call' );
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

                $activities = $this->get( "teams/$groupId/calendar/", [ "limit"   => "null",
                                                                        "version" => "2"
                ] );
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
        $check_empty_key = $this->checkApiKey();

        if ( !is_null( $check_empty_key ) ) {
            return $check_empty_key;
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
     * Retrieves news items from the MyClub backend API.
     *
     * @param string|null $groupId (Optional) The group ID to filter the news items. If not provided, all news items will be fetched.
     * @param string|null $sectionId (Optional) The section ID to filter the news items. If not provided, all news items will be fetched.
     *
     * @return stdClass|bool The news items fetched from the API. If the API key is empty, it returns false.
     *                        If there is an error in the API call or the status code is not 200, it returns the
     *                        decoded JSON or WordPress error. Otherwise, it returns the decoded news items.
     * @since 1.0.0
     */
    public function loadNews( string $groupId = null, string $sectionId = null )
    {
        if ( empty( $this->apiKey ) ) {
            return false;
        }

        $args = [ "limit" => "null" ];
        if ( !is_null( $groupId ) ) {
            $args[ "team" ] = $groupId;
        }

        if ( !is_null( $sectionId ) ) {
            $args[ "section" ] = $sectionId;
        }

        $decoded = $this->get( "news/", $args );
        if ( is_wp_error( $decoded ) || $decoded->status !== 200 ) {
            error_log( 'Unable to load news: Error occurred in API call' );
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
        $check_empty_key = $this->checkApiKey();

        if ( !is_null( $check_empty_key ) ) {
            return $check_empty_key;
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

    /**
     * Retrieves a specific section and its associated activities from the MyClub backend API.
     *
     * @param string $sectionId The unique identifier of the section to retrieve.
     * @return stdClass The section data fetched from the API. If the API key is empty, it returns an error response.
     *                  If there is an error in the API call or the section data cannot be loaded, it logs an error
     *                  and returns a response with an empty array and a status code of 500. If successful, it
     *                  includes associated activities in the section data.
     * @since 1.0.0
     */
    public function loadSection( string $sectionId )
    {
        $check_empty_key = $this->checkApiKey();

        if ( !is_null( $check_empty_key ) ) {
            return $check_empty_key;
        }

        $decoded = $this->get( "sections/$sectionId/" );

        if ( is_wp_error( $decoded ) || $decoded->status !== 200 ) {
            error_log( 'Unable to load section: Error occurred in API call' );
        } else {
            $activities = $this->get( "calendar/", [ "limit"   => "null", "section_id" => $sectionId ] );
            if ( $activities->status === 200 ) {
                $decoded->result->activities = $activities->result->results;
            } else {
                $return_value = new stdClass();
                $return_value->result = [];
                $return_value->status = 500;
                return $return_value;
            }
        }

        return $decoded;
    }

    /**
     * Retrieves sections from the MyClub backend API.
     *
     * @return stdClass The sections fetched from the API. If the API key is empty, it returns an appropriate response.
     *                  If there is an error during the API call, it returns an object containing an empty result array
     *                  with a status code of 500. Otherwise, it returns the decoded sections data.
     * @since 1.0.0
     */
    public function loadSections()
    {
        $service_path = 'sections/';
        $check_empty_key = $this->checkApiKey();

        if ( !is_null( $check_empty_key ) ) {
            return $check_empty_key;
        }

        $decoded = $this->get( $service_path, [ 'limit' => "null" ] );

        if ( is_wp_error( $decoded ) ) {
            error_log( 'Unable to load sections: Error occurred in API call' );
            $return_value = new stdClass();
            $return_value->result = [];
            $return_value->status = 500;
            return $return_value;
        }

        return $decoded;
    }

    /**
     * Retrieves the calendar data for a specific section from the MyClub backend API.
     *
     * @param string $sectionId The identifier of the section for which the calendar data will be fetched.
     * @return stdClass The calendar data fetched from the API. If the API key is empty, it returns an empty array
     *                  with a status code of 401. If there is an error in the API call, it returns an empty array
     *                  with a status code of 500. Otherwise, it returns the decoded calendar data.
     * @since 1.0.0
     */
    public function loadSectionCalendar( string $sectionId )
    {
        $service_path = "calendar/";
        $check_empty_key = $this->checkApiKey();

        if ( !is_null( $check_empty_key ) ) {
            return $check_empty_key;
        }

        $decoded = $this->get( $service_path, [ 'limit'   => "null",
                                                "version" => "2",
                                                "section" => $sectionId
        ] );

        if ( is_wp_error( $decoded ) ) {
            error_log( 'Unable to load section calendar: Error occurred in API call' );
            $return_value = new stdClass();
            $return_value->result = [];
            $return_value->status = 500;
            return $return_value;
        }

        return $decoded;
    }

    /**
     * Fetches the list of bookable items from the MyClub backend API.
     *
     * @return stdClass The bookable items retrieved from the API. If the API key is missing, it returns an object with
     *                   an empty result array and a status code of 401. In case of an API call error, it logs the error,
     *                   and returns an object with an empty result array and a status code of 500. On success, it returns
     *                   the decoded list of bookable items.
     * @since 1.0.0
     */
    public function loadBookables()
    {
        $service_path = 'bookables/';

        $check_empty_key = $this->checkApiKey();

        if ( !is_null( $check_empty_key ) ) {
            return $check_empty_key;
        }

        $decoded = $this->get($service_path, ['limit' => "null"]);

        if (is_wp_error($decoded)) {
            error_log('Unable to load bookable items: Error occurred in API call');
            $return_value = new stdClass();
            $return_value->result = [];
            $return_value->status = 500;
            return $return_value;
        }

        return $decoded;
    }

    /**
     * Loads bookable slots for a given bookable ID within an optional date range.
     *
     * @param string|null $bookableId The ID of the bookable entity to retrieve slots for. If null, the method returns false.
     * @param string|null $start_date Optional start date for filtering the slots. If null, no start date is applied.
     * @param string|null $end_date Optional end date for filtering the slots. If null, no end date is applied.
     * @return mixed The decoded response containing bookable slots if successful. Returns false if the API key is invalid or the bookable ID is null. Logs an error and may return an error response if the API call fails.
     * @since 1.0.0
     */
    public function loadBookableSlots( string $bookableId = null, string $start_date = null, string $end_date = null)
    {
        $check_empty_key = $this->checkApiKey();

        if ( !is_null( $check_empty_key ) ) {
            return false;
        }

        if (is_null($bookableId)) {
            return false;
        }
        $args = array(
            "limit" => "null"
        );
        if (!is_null($start_date)) {
            $args["start_date"] = $start_date;
        }
        if (!is_null($end_date)) {
            $args["end_date"] = $end_date;
        }

        $service_path = sprintf("bookables/%s/slots/", $bookableId);

        $decoded = $this->get($service_path, $args);
        if (is_wp_error($decoded) || $decoded->status !== 200) {
            error_log('Unable to load bookable slots: Error occurred in API call');
        }

        return $decoded;
    }

    /**
     * Retrieves a specific bookable slot based on the provided bookable ID and slot ID.
     *
     * @param string|null $bookableId The ID of the bookable resource. If not provided, the method will return false.
     * @param string|null $slotId The ID of the slot to be retrieved. If not provided, the method will return false.
     * @return stdClass|false The bookable slot details fetched from the API as a decoded object, or false if the API key
     *                        is invalid, input parameters are null, or an error occurs during the API call.
     * @since 1.0.0
     */
    public function loadBookableSlot( string $bookableId = null, string $slotId = null)
    {
        $check_empty_key = $this->checkApiKey();

        if ( !is_null( $check_empty_key ) ) {
            return false;
        }

        if (is_null($bookableId) || is_null($slotId)) {
            return false;
        }

        $service_path = sprintf("bookables/%s/slots/%s/", $bookableId, $slotId);

        $decoded = $this->get($service_path);
        if (is_wp_error($decoded) || $decoded->status !== 200) {
            error_log('Unable to load bookable slots: Error occurred in API call');
        }

        return $decoded;
    }

    /**
     * Books a slot for a specified bookable item in the system.
     *
     * @param string $bookableId The ID of the bookable item.
     * @param string $slotId The ID of the slot to be booked.
     * @param string $startTime The start time of the booking in a valid datetime format.
     * @param string $endTime The end time of the booking in a valid datetime format.
     * @param string $email The email address of the individual booking the slot.
     * @param string|null $firstName The first name of the individual booking the slot (optional).
     * @param string|null $lastName The last name of the individual booking the slot (optional).
     *
     * @return mixed The decoded API response resulting from the booking operation. Returns false if the API key is invalid
     *               or missing. If an error occurs during the API call, logs the error and returns the API's error response.
     * @since 1.0.0
     */
    public function bookSlot( string $bookableId, string $slotId, string $startTime, string $endTime, string $email, string $firstName = null, string $lastName = null)
    {
        $check_empty_key = $this->checkApiKey();

        if ( !is_null( $check_empty_key ) ) {
            return false;
        }
        $args = array(
            "start_time" => $startTime,
            "end_time" => $endTime,
            "email" => $email,
            "first_name" => $firstName,
            "last_name" => $lastName,
            "bookable_zones_taken" => 1,
        );
        $service_path = sprintf("bookables/%s/slots/%s/book/", $bookableId, $slotId);
        $decoded = $this->post($service_path, $args);
        if (is_wp_error($decoded)) {
            error_log('Unable to book slot: Error occurred in API call');
        }
        return $decoded;
    }

    private function post(string $service_path, array $data = [])
    {
        $args = $this->getPostArgs($data);
        $response = wp_remote_post($this->getServerUrl($service_path), $args);

        if (is_wp_error($response)) {
            error_log('Error occurred during API get call, additional info: ' . $response->get_error_message());
            return $response;
        } else {
            $value = new stdClass();
            $value->result = json_decode(wp_remote_retrieve_body($response));
            $value->status = $response['response']['code'];
            return $value;
        }
    }

    /**
     * Prepares the arguments for an HTTP POST request.
     *
     * @param array $data An optional array of data to include in the request body. Defaults to an empty array.
     * @return array The prepared POST request arguments, including timeout, JSON-encoded body, and custom headers.
     * @since 1.0.0
     */
    protected function getPostArgs( array $data = []): array
    {
        return array(
            'timeout' => 5,
            'body' => json_encode($data),
            'headers' => $this->createRequestHeaders()
        );
    }

    /**
     * Validates the presence of the API key.
     *
     * @return stdClass|null Returns a status object with an empty result array and a status code of 401 if the API key is empty.
     *                       Returns null if the API key is present.
     */
    private function checkApiKey(): ?stdClass
    {
        if ( empty( $this->apiKey ) ) {
            $return_value = new stdClass();
            $return_value->result = [];
            $return_value->status = 401;
            return $return_value;
        }

        return null;
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