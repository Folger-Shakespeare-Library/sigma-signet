<?php

/**
 * Public helper functions for Sigma Signet
 */

if (!function_exists('sigma_get_user_type')) {
    /**
     * Get the current user's authentication type
     *
     * @return string One of: 'anonymous', 'wordpress', 'user_pass', 'ip_range'
     */
    function sigma_signet_get_user_type(): string
    {
        if (!is_user_logged_in()) {
            return 'anonymous';
        }

        $sigma_id_type = get_user_meta(get_current_user_id(), 'sigma_identifier_type', true);

        return $sigma_id_type ?: 'wordpress';
    }
}
