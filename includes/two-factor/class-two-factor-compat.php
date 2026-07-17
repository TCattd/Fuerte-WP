<?php

/**
 * A compatibility layer for some of the most popular plugins.
 */

/**
 * A compatibility layer for some of the most popular plugins.
 *
 * Should be used with care because ideally we wouldn't need
 * any integration specific code for this plugin. Everything should
 * be handled through clever use of hooks and best practices.
 *
 * @since 0.5.0
 */
class Two_Factor_Compat
{
    /**
     * Initialize all the custom hooks as necessary.
     *
     * @since 0.5.0
     */
    public function init()
    {
        /**
         * Jetpack.
         *
         * @see https://wordpress.org/plugins/jetpack/
         */
        add_filter('two_factor_rememberme', [$this, 'jetpack_rememberme']);
    }

    /**
     * Jetpack single sign-on wants long-lived sessions for users.
     *
     * @since 0.5.0
     *
     * @param bool $rememberme Current state of the "remember me" toggle.
     *
     * @return bool
     */
    public function jetpack_rememberme($rememberme)
    {
        $action = filter_input(INPUT_GET, 'action', FILTER_CALLBACK, ['options' => 'sanitize_key']);

        if ('jetpack-sso' === $action && $this->jetpack_is_sso_active()) {
            return true;
        }

        return $rememberme;
    }

    /**
     * Helper to detect the presence of the active SSO module.
     *
     * @since 0.5.0
     *
     * @return bool
     */
    public function jetpack_is_sso_active()
    {
        return  class_exists('Jetpack') && method_exists('Jetpack', 'is_module_active') && Jetpack::is_module_active('sso');
    }
}
