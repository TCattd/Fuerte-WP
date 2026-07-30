<?php

/**
 * File config format tests.
 *
 * Locks in the 2026-07-17 migration of the hard-coded file configs off the
 * legacy `rest_api` namespace. File config is consumed raw (no normalization,
 * no migration), so the on-disk keys must already match what the plugin reads:
 * `restrictions.restapi_loggedin_only` and `restrictions.restapi_disable_app_passwords`.
 *
 * These are string-content assertions (the files define constants like
 * FUERTEWP_DISABLE, so they cannot be safely re-executed inside the test
 * process without fatalling on redefinition).
 *
 * @since 1.10.0
 */

test('file configs use the restrictions namespace, not the legacy rest_api block', function () {
    $files = glob(FUERTEWP_PATH . '.config-tcattd/wp-config-fuerte-*.php');
    expect($files)->not->toBeEmpty('expected at least one hard-coded file config');

    foreach ($files as $file) {
        $contents = file_get_contents($file);

        expect($contents)
            ->toContain("'restapi_loggedin_only'")
            ->toContain("'restapi_disable_app_passwords'")
            ->not->toContain("'rest_api' =>");
    }
});
