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

        // User profile hooks
        add_action('show_user_profile', [$this, 'renderSigmaUserFields']);
        add_action('edit_user_profile', [$this, 'renderSigmaUserFields']);
        add_action('personal_options_update', [$this, 'saveSigmaUserFields']);
        add_action('edit_user_profile_update', [$this, 'saveSigmaUserFields']);
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

        add_settings_field(
            'ip_auth_enabled',
            __('IP Authentication', 'sigma-signet'),
            [$this, 'renderIpAuthField'],
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
     * Render IP Auth field
     */
    public function renderIpAuthField(): void
    {
        $value = $this->settings->get('ip_auth_enabled');
    ?>
        <label>
            <input type="checkbox"
                name="sigma_signet_settings[ip_auth_enabled]"
                value="1"
                <?php checked($value, true); ?> />
            <?php _e('Enable automatic IP-based authentication', 'sigma-signet'); ?>
        </label>
        <p class="description">
            <?php _e('When enabled, visitors from authorized IP addresses will be automatically authenticated. Disable for testing individual logins.', 'sigma-signet'); ?>
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
        $sanitized['ip_auth_enabled'] = !empty($input['ip_auth_enabled']);

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

    /**
     * Render SIGMA user fields on user profile page
     *
     * @param \WP_User $user The user being edited
     */
    public function renderSigmaUserFields(\WP_User $user): void
    {
        // Only show for SIGMA users
        $isSigmaUser = get_user_meta($user->ID, 'sigma_user', true);
        if (!$isSigmaUser) {
            return;
        }

        // Get SIGMA meta
        $profileId = get_user_meta($user->ID, 'sigma_profile_id', true);
        $authType = get_user_meta($user->ID, 'sigma_auth_type', true);
        $identifierType = get_user_meta($user->ID, 'sigma_identifier_type', true);
        $openUrlResolver = get_user_meta($user->ID, 'sigma_openurl_resolver', true);
        $openUrlIcon = get_user_meta($user->ID, 'sigma_openurl_icon', true);

        wp_nonce_field('sigma_signet_user_meta', 'sigma_signet_user_nonce');
    ?>
        <h2><?php _e('SIGMA Authentication', 'sigma-signet'); ?></h2>
        <p class="description" style="margin-bottom: 1em;">
            <?php _e('These values are managed by SIGMA and will be overwritten on next login.', 'sigma-signet'); ?>
        </p>
        <table class="form-table" role="presentation">
            <tr>
                <th><label for="sigma_profile_id"><?php _e('Profile ID', 'sigma-signet'); ?></label></th>
                <td>
                    <input type="text" name="sigma_profile_id" id="sigma_profile_id"
                        value="<?php echo esc_attr($profileId); ?>" class="regular-text" />
                </td>
            </tr>
            <tr>
                <th><label for="sigma_auth_type"><?php _e('Authentication Type', 'sigma-signet'); ?></label></th>
                <td>
                    <select name="sigma_auth_type" id="sigma_auth_type">
                        <option value="" <?php selected($authType, ''); ?>>—</option>
                        <option value="named" <?php selected($authType, 'named'); ?>>named</option>
                        <option value="anonymous" <?php selected($authType, 'anonymous'); ?>>anonymous</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="sigma_identifier_type"><?php _e('Identifier Type', 'sigma-signet'); ?></label></th>
                <td>
                    <select name="sigma_identifier_type" id="sigma_identifier_type">
                        <option value="" <?php selected($identifierType, ''); ?>>—</option>
                        <option value="user_pass" <?php selected($identifierType, 'user_pass'); ?>>user_pass</option>
                        <option value="ip_range" <?php selected($identifierType, 'ip_range'); ?>>ip_range</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="sigma_openurl_resolver"><?php _e('OpenURL Resolver', 'sigma-signet'); ?></label></th>
                <td>
                    <input type="url" name="sigma_openurl_resolver" id="sigma_openurl_resolver"
                        value="<?php echo esc_attr($openUrlResolver); ?>" class="regular-text" />
                </td>
            </tr>
            <tr>
                <th><label for="sigma_openurl_icon"><?php _e('OpenURL Icon', 'sigma-signet'); ?></label></th>
                <td>
                    <input type="url" name="sigma_openurl_icon" id="sigma_openurl_icon"
                        value="<?php echo esc_attr($openUrlIcon); ?>" class="regular-text" />
                    <?php if ($openUrlIcon) : ?>
                        <p><img src="<?php echo esc_url($openUrlIcon); ?>" alt="OpenURL Icon" style="max-height: 32px; margin-top: 8px;" /></p>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
<?php
    }

    /**
     * Save SIGMA user fields from user profile page
     *
     * @param int $userId The user ID being saved
     */
    public function saveSigmaUserFields(int $userId): void
    {
        // Verify nonce
        if (
            !isset($_POST['sigma_signet_user_nonce']) ||
            !wp_verify_nonce($_POST['sigma_signet_user_nonce'], 'sigma_signet_user_meta')
        ) {
            return;
        }

        // Check permissions
        if (!current_user_can('edit_user', $userId)) {
            return;
        }

        // Only save for SIGMA users
        $isSigmaUser = get_user_meta($userId, 'sigma_user', true);
        if (!$isSigmaUser) {
            return;
        }

        // Save fields
        if (isset($_POST['sigma_profile_id'])) {
            update_user_meta($userId, 'sigma_profile_id', sanitize_text_field($_POST['sigma_profile_id']));
        }

        if (isset($_POST['sigma_auth_type'])) {
            update_user_meta($userId, 'sigma_auth_type', sanitize_text_field($_POST['sigma_auth_type']));
        }

        if (isset($_POST['sigma_identifier_type'])) {
            update_user_meta($userId, 'sigma_identifier_type', sanitize_text_field($_POST['sigma_identifier_type']));
        }

        if (isset($_POST['sigma_openurl_resolver'])) {
            $resolver = esc_url_raw($_POST['sigma_openurl_resolver']);
            if ($resolver) {
                update_user_meta($userId, 'sigma_openurl_resolver', $resolver);
            } else {
                delete_user_meta($userId, 'sigma_openurl_resolver');
            }
        }

        if (isset($_POST['sigma_openurl_icon'])) {
            $icon = esc_url_raw($_POST['sigma_openurl_icon']);
            if ($icon) {
                update_user_meta($userId, 'sigma_openurl_icon', $icon);
            } else {
                delete_user_meta($userId, 'sigma_openurl_icon');
            }
        }
    }

    /**
     * Show display name for SIGMA users in admin user list
     */
    public function showSigmaDisplayName(string $display_name, int $user_id, object $user): string
    {
        // Only modify in admin user list context
        if (!is_admin() || !function_exists('get_current_screen')) {
            return $display_name;
        }

        $screen = get_current_screen();
        if (!$screen || $screen->id !== 'users') {
            return $display_name;
        }

        // Check if this is a SIGMA user
        $isSigmaUser = get_user_meta($user_id, 'sigma_user', true);

        if ($isSigmaUser && !empty($user->display_name)) {
            return $user->display_name;
        }

        return $display_name;
    }
}
