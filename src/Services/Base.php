<?php

namespace MyClub\Common\Services;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Class Base
 *
 * Represents a base class with plugin path and URL properties.
 */
class Base
{
    protected string $plugin_path;
    protected string $plugin_url;

    /**
     * Class constructor.
     *
     * Initializes the class by setting the plugin path and URL.
     *
     * @return void
     * @since 1.0.0
     */
    public function __construct()
    {
        $this->plugin_path = plugin_dir_path( dirname( __FILE__, 2 ) );
        $this->plugin_url = plugin_dir_url( dirname( __FILE__, 2 ) );
    }
}