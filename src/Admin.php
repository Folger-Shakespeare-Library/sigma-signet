<?php

namespace SigmaSignet;

/**
 * Admin interface for SIGMA OIDC settings
 */
class Admin
{
    private Settings $settings;

    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
    }

    /**
     * Initialize admin hooks
     */
    public function init(): void
    {
        add_action('admin_menu', [$this, 'addAdminMenu']);
        add_action('admin_init', [$this, 'registerSettings']);
    }

    /**
     * Add admin menu page
     */
    public function addAdminMenu(): void
    {
        add_options_page(
            __('SIGMA OIDC Settings', 'sigma-signet'),
            __('SIGMA OIDC', 'sigma-signet'),
            'manage_options',
            'sigma-oidc',
            [$this, 'renderSettingsPage']
        );
    }

    /**
     * Register settings
     */
    public function registerSettings(): void
    {
        register_setting(
            'sigma_oidc_settings',
            'sigma_signet_settings',
            [
                'type' => 'array',
                'sanitize_callback' => [$this, 'sanitizeSettings'],
                'default' => []
            ]
        );

        add_settings_section(
            'sigma_oidc_main',
            __('SIGMA OIDC Configuration', 'sigma-signet'),
            [$this, 'renderSectionDescription'],
            'sigma_oidc_settings'
        );

        add_settings_field(
            'idp_url',
            __('Identity Provider URL', 'sigma-signet'),
            [$this, 'renderIdpUrlField'],
            'sigma_oidc_settings',
            'sigma_oidc_main'
        );

        add_settings_field(
            'client_id',
            __('Client ID', 'sigma-signet'),
            [$this, 'renderClientIdField'],
            'sigma_oidc_settings',
            'sigma_oidc_main'
        );

        add_settings_field(
            'client_secret',
            __('Client Secret', 'sigma-signet'),
            [$this, 'renderClientSecretField'],
            'sigma_oidc_settings',
            'sigma_oidc_main'
        );

        add_settings_field(
            'redirect_uri',
            __('Redirect URI', 'sigma-signet'),
            [$this, 'renderRedirectUriField'],
            'sigma_oidc_settings',
            'sigma_oidc_main'
        );

        add_settings_field(
            'debug_enabled',
            __('Debug Logging', 'sigma-signet'),
            [$this, 'renderDebugField'],
            'sigma_oidc_settings',
            'sigma_oidc_main'
        );
    }

    /**
     * Render settings page
     */
    public function renderSettingsPage(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        settings_errors('sigma_oidc_messages');
?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <form action="options.php" method="post">
                <?php
                settings_fields('sigma_oidc_settings');
                do_settings_sections('sigma_oidc_settings');
                submit_button(__('Save Settings', 'sigma-signet'));
                ?>
            </form>

            <div class="notice notice-info">
                <p><strong><?php _e('Configuration Status:', 'sigma-signet'); ?></strong></p>
                <p>
                    <?php if ($this->settings->isConfigured()) : ?>
                        <span style="color: green;">✓ <?php _e('SIGMA OIDC is configured and ready', 'sigma-signet'); ?></span>
                    <?php else : ?>
                        <span style="color: red;">✗ <?php _e('SIGMA OIDC is not fully configured', 'sigma-signet'); ?></span>
                    <?php endif; ?>
                </p>
            </div>

            <div class="notice notice-warning">
                <p><strong><?php _e('Test Login:', 'sigma-signet'); ?></strong></p>
                <p>
                    <?php if ($this->settings->isConfigured()) : ?>
                        <a href="<?php echo esc_url(add_query_arg('sigma_login', '1', home_url())); ?>"
                            class="button button-secondary" target="_blank">
                            <?php _e('Test SIGMA Login', 'sigma-signet'); ?>
                        </a>
                    <?php else : ?>
                        <?php _e('Complete configuration above to enable test login', 'sigma-signet'); ?>
                    <?php endif; ?>
                </p>
            </div>
        </div>
    <?php
    }

    /**
     * Render section description
     */
    public function renderSectionDescription(): void
    {
        echo '<p>' . __('Configure your SIGMA OIDC settings below. All fields are required.', 'sigma-signet') . '</p>';
    }

    /**
     * Render IDP URL field
     */
    public function renderIdpUrlField(): void
    {
        $value = $this->settings->get('idp_url');
    ?>
        <input type="url"
            name="sigma_signet_settings[idp_url]"
            value="<?php echo esc_attr($value); ?>"
            class="regular-text"
            placeholder="https://idp.sams-sigma.com"
            required />
        <p class="description">
            <?php _e('The SIGMA Identity Provider URL (e.g., https://uat-idp.sams-sigma.com for testing)', 'sigma-signet'); ?>
        </p>
    <?php
    }

    /**
     * Render Client ID field
     */
    public function renderClientIdField(): void
    {
        $value = $this->settings->get('client_id');
    ?>
        <input type="text"
            name="sigma_signet_settings[client_id]"
            value="<?php echo esc_attr($value); ?>"
            class="regular-text"
            required />
        <p class="description">
            <?php _e('Your SIGMA client ID (licensed website identifier)', 'sigma-signet'); ?>
        </p>
    <?php
    }

    /**
     * Render Client Secret field
     */
    public function renderClientSecretField(): void
    {
        $value = $this->settings->get('client_secret');
    ?>
        <input type="password"
            name="sigma_signet_settings[client_secret]"
            value="<?php echo esc_attr($value); ?>"
            class="regular-text"
            required />
        <p class="description">
            <?php _e('Your SIGMA client secret', 'sigma-signet'); ?>
        </p>
    <?php
    }

    /**
     * Render Redirect URI field
     */
    public function renderRedirectUriField(): void
    {
        $value = $this->settings->get('redirect_uri');
        $defaultUri = home_url('/sigma-callback');
    ?>
        <input type="url"
            name="sigma_signet_settings[redirect_uri]"
            value="<?php echo esc_attr($value ?: $defaultUri); ?>"
            class="regular-text"
            required />
        <p class="description">
            <?php printf(
                __('The callback URL for SIGMA to redirect to. Default: %s', 'sigma-signet'),
                '<code>' . esc_html($defaultUri) . '</code>'
            ); ?>
        </p>
    <?php
    }

    /**
     * Render Debug field
     */
    public function renderDebugField(): void
    {
        $value = $this->settings->get('debug_enabled');
    ?>
        <label>
            <input type="checkbox"
                name="sigma_signet_settings[debug_enabled]"
                value="1"
                <?php checked($value, true); ?> />
            <?php _e('Enable detailed debug logging', 'sigma-signet'); ?>
        </label>
        <p class="description">
            <?php _e('Enable this during development to see detailed logs in your WordPress debug log. Disable for production.', 'sigma-signet'); ?>
        </p>
<?php
    }

    /**
     * Sanitize settings
     */
    public function sanitizeSettings(array $input): array
    {
        $sanitized = [];

        $sanitized['idp_url'] = esc_url_raw($input['idp_url'] ?? '');
        $sanitized['client_id'] = sanitize_text_field($input['client_id'] ?? '');
        $sanitized['client_secret'] = sanitize_text_field($input['client_secret'] ?? '');
        $sanitized['redirect_uri'] = esc_url_raw($input['redirect_uri'] ?? '');
        $sanitized['debug_enabled'] = !empty($input['debug_enabled']);

        // Validate required fields
        if (empty($sanitized['idp_url'])) {
            add_settings_error(
                'sigma_oidc_messages',
                'idp_url_empty',
                __('Identity Provider URL is required.', 'sigma-signet')
            );
        }

        if (empty($sanitized['client_id'])) {
            add_settings_error(
                'sigma_oidc_messages',
                'client_id_empty',
                __('Client ID is required.', 'sigma-signet')
            );
        }

        if (empty($sanitized['client_secret'])) {
            add_settings_error(
                'sigma_oidc_messages',
                'client_secret_empty',
                __('Client Secret is required.', 'sigma-signet')
            );
        }

        if (empty($sanitized['redirect_uri'])) {
            add_settings_error(
                'sigma_oidc_messages',
                'redirect_uri_empty',
                __('Redirect URI is required.', 'sigma-signet')
            );
        }

        return $sanitized;
    }
}
