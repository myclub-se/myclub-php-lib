<?php

namespace MyClub\Common\Api;

if ( !defined( 'ABSPATH' ) ) exit; // Exit if accessed directly


use stdClass;
use WP_Error;

/**
 * Class Request
 *
 * Provides methods to interact with the MyClub backend API.
 */
class Request
{
    const MYCLUB_SERVER_API_PATH = 'https://member.myclub.se/api/v3/external/';

    private string $apiKey;

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
        $this->apiKey = $apiKey;
        $this->pluginName = $pluginName;
        $this->pluginVersion = $pluginVersion;
        $this->multiSite = is_multisite();
        $this->site = get_bloginfo( 'url' );
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
    public function get( string $service_path, array $data = [] )
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
            'X-MyClub-Request'   => $this->pluginName,
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