<?php

namespace SigmaSignet;

/**
 * Main Plugin Class
 */
class Plugin
{
    private static ?Plugin $instance = null;
    private string $version = '0.1.0';
    private Settings $settings;
    private ?WordPressIntegration $wpIntegration = null;
    private ?Admin $admin = null;

    public static function getInstance(): Plugin
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct()
    {
        $this->settings = new Settings();
        $this->init();
    }

    private function init(): void
    {
        $isConfigured = $this->settings->isConfigured() ? 'configured' : 'not configured';
        error_log("SigmaSignet\Plugin initialized ($isConfigured)");

        // Initialize WordPress integration if we're in WordPress context
        if (function_exists('add_action')) {
            // Initialize admin interface
            $this->admin = new Admin($this->settings);
            $this->admin->init();

            // Initialize frontend integration
            $tokenGenerator = new TokenGenerator($this->settings);
            $oidcClient = new OidcClient($this->settings, $tokenGenerator);
            $tokenExchange = new TokenExchange($this->settings);
            $userManager = new UserManager();
            $this->wpIntegration = new WordPressIntegration($this->settings, $oidcClient, $tokenExchange, $userManager);
            $this->wpIntegration->init();
        }
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function getSettings(): Settings
    {
        return $this->settings;
    }

    public function getWpIntegration(): ?WordPressIntegration
    {
        return $this->wpIntegration;
    }

    public function getAdmin(): ?Admin
    {
        return $this->admin;
    }
}
