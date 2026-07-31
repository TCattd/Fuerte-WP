# Admin Menu Visibility: Discovery-Driven Hide & Block

**Status:** Implemented in 1.11.0 (Phases 1-6 complete). The five defects from the source review (D1-D5) were folded in during implementation; see [Implementation Record](#implementation-record).
**Type:** Feature plan (also ships a regression fix)
**Target version:** 1.11.0
**Related files:** `includes/class-fuerte-wp-enforcer.php`, `includes/class-fuerte-wp-helper.php`, `includes/class-fuerte-wp-config.php`, `admin/class-fuerte-wp-admin.php`, `includes/class-fuerte-wp-hook-manager.php`

---

## Table of Contents

- [Summary](#summary)
- [Motivation](#motivation)
- [Current State (corrected)](#current-state-corrected)
- [Research Findings](#research-findings)
- [Goals and Non-Goals](#goals-and-non-goals)
- [Architecture (three layers plus a wiring fix)](#architecture-three-layers-plus-a-wiring-fix)
- [Detailed Implementation](#detailed-implementation)
- [Two Complexities and Their Solutions](#two-complexities-and-their-solutions)
- [Configuration Shape](#configuration-shape)
- [Testing Plan](#testing-plan)
- [Backwards Compatibility and Migration](#backwards-compatibility-and-migration)
- [Risks](#risks)
- [Rollout Phasing](#rollout-phasing)
- [Decisions](#decisions)

---

## Summary

Let the Fuerte-WP admin (a super user) hide and block admin menus, submenus, and admin-bar nodes **without typing slugs**, and make one selection both hide AND block direct-URL access.

This plan also fixes a **pre-existing regression**: since the 1.7.0 HyperFields migration, the five Advanced Restrictions settings (`removed_menus`, `removed_submenus`, `removed_adminbar_menus`, `restricted_scripts`, `restricted_pages`) have been silently dead on every install without legacy Carbon Fields data. The fix is Phase 1 of this refactor and ships with its own changelog line.

The feature adds:

1. **Auto-discovery** of every registered admin menu, submenu, and admin-bar node (core, plugins, themes), tagged Core vs foreign.
2. A searchable **multiselect UI** replacing the manual textareas.
3. **Unified hide + block** (narrowed to avoid over-blocking, see below).
4. A manual fallback textarea, now framed as a precision escape hatch.

---

## Motivation

Three concrete problems:

1. **UX problem.** Non-developer admins cannot use the feature. They do not know that a plugin's settings menu has slug `wprocket` or that a submenu needs the `parent|child` pipe format. Every new plugin equals guesswork.
2. **Security problem.** `remove_menu_page()` only removes the link. It does not revoke access. A non-super-admin can still hit `admin.php?page=plugin-settings` directly. Full protection today requires entering the slug in BOTH `removed_menus` (hide) AND `restricted_pages` (block), which are decoupled fields.
3. **Regression (newly found).** The Advanced Restrictions feature is already dead on 1.7.0+ installs without legacy data. The admin's saved values never reach the enforcer. Detail in [Current State](#current-state-corrected).

The user request: make this user-friendly for non-devs, auto-discover menus added by foreign plugins and themes, and also let the admin hide and block default WordPress items.

---

## Current State (corrected)

This section replaces the earlier (incorrect) claim that the feature works today. A trace of the data path shows it is broken.

### The five config keys

Stored as textarea strings, parsed by `Config::parse_textarea()`:

| Key | Intended effect | Intended enforcer |
|---|---|---|
| `restricted_scripts` | Block direct access by `$pagenow` | `apply_page_restrictions()` |
| `restricted_pages` | Block direct access by `$_REQUEST['page']` | `apply_page_restrictions()` |
| `removed_menus` | Hide top-level menu via `remove_menu_page()` | `remove_menus()` |
| `removed_submenus` | Hide submenu via `remove_submenu_page()` | `remove_menus()` |
| `removed_adminbar_menus` | Remove toolbar node via `remove_node()` | `remove_adminbar_menus()` |

### The wiring bug (regression since 1.7.0)

The admin UI saves textarea values into `fuertewp_settings['fuertewp_removed_menus']`. Then:

- `Fuerte_Wp_Config::normalize_settings()` reads that correctly, but writes the output **nested** under `'advanced_restrictions' => ['removed_menus' => ...]`.
- `load_from_legacy_database()` emits the same keys **flat** at top level (`$config['removed_menus'] = self::load_multiselect_field('fuertewp_removed_menus')`), which reads old `_fuertewp_removed_menus|||N|value` Carbon Fields rows. On any install that never had those rows (anything set up after the 1.7.0 migration), that is an empty array.
- `load_from_database()` merges with `array_replace_recursive($legacy_settings, $normalized_hf)`. This does NOT unify `$array['removed_menus']` with `$array['advanced_restrictions']['removed_menus']`; they are different keys. The final config has both: a populated nested value and an always-empty flat value.
- The enforcer reads only the flat key: `remove_menus()` (`isset($fuertewp['removed_menus'])`), `apply_page_restrictions()` (`$fuertewp['restricted_scripts']`, `$fuertewp['restricted_pages']`), and `Fuerte_Wp_Hook_Manager::should_register_restriction_hooks()`. None read `$fuertewp['advanced_restrictions'][...]`.

**Net effect:** on a clean 1.7.0+ install, typing anything into the five Advanced Restrictions textareas and saving does nothing. `remove_menu_page()` / `remove_submenu_page()` / `remove_node()` are never called with the admin's values.

### Why it shipped unnoticed

No test in `tests/unit/` exercises `get_config()` through to the enforcer for these five keys. `AccessControlTest` and `HyperFieldsStorageTest` exercise `Fuerte_Wp_Config::get_field()`, a separate correctly-wired method that bypasses `normalize_settings()` entirely. That coverage gap let the regression ship.

### Enforcement chain (correct parts, unchanged by this plan)

- `Fuerte_Wp_Enforcer::enforcer()` is the entry point. Early-exit #7 short-circuits the engine for super users: `if ($is_super_user && !$is_forced) return;`. This protects `apply_page_restrictions()` and the core `disable_*` toggles.
- `remove_menus()` runs on `admin_menu` priority **999** (registered in `class-fuerte-wp-hook-manager.php`). It opens with `Fuerte_Wp_Helper::bypasses_restrictions()`.
- `remove_adminbar_menus()` runs on `admin_bar_menu` with the same gate.
- `apply_page_restrictions()` calls `$this->access_denied()` when `$pagenow` is in `restricted_scripts`, or when `$_REQUEST['page']` is in `restricted_pages`.

### Super-user gate

`Fuerte_Wp_Helper::is_super_user()` matches by email against `$fuertewp['super_users']`. `bypasses_restrictions()` returns false when `FUERTEWP_FORCE` is true. Unchanged by this plan.

### The hide-not-block gap (separate from the regression)

Even when the wiring is fixed, `removed_menus` (hide) and `restricted_pages` (block) are separate fields. Hiding does not block. This plan unifies them.

---

## Research Findings

These findings are why the feature is feasible without new dependencies or core hacks. All citations were verified against source.

### Finding 1: WordPress populates `$GLOBALS['menu']` and `$GLOBALS['submenu']` reliably

Source: `src/wp-core/wp-admin/includes/plugin.php:1391` (`add_menu_page`).

The `$new_menu` array has a fixed index layout:

```
$menu[(string) $position] = array(
    [0] menu_title    // "WP Rocket"        <- human label
    [1] capability    // "manage_options"
    [2] menu_slug     // "wprocket"         <- the key Fuerte strips on
    [3] page_title
    [4] classes
    [5] hookname      // hook suffix
    [6] icon_url
);
```

`$GLOBALS['submenu'][$parent_slug][]` mirrors this: `[0] menu_title, [1] capability, [2] menu_slug, [3] page_title`.

### Finding 2: Timing is safe

Source: `src/wp-core/wp-admin/includes/menu.php`.

WordPress fires `admin_menu`, then walks `$menu` and `$submenu` to strip items the current user lacks capability for. The submenu strip runs before `admin_menu` fires; the top-level `$menu` strip runs after `admin_menu` but before the requested admin page file is included (the whole file runs during `wp-admin/admin.php` bootstrap). So by the time any admin page body renders, both globals are fully built and capability-filtered for the current user.

On the Fuerte settings page, the viewing user is a super user with `manage_options`. Therefore `$GLOBALS['menu']` at settings render time is the complete universe: core plus every plugin/theme menu the super user can see. Read it live during field rendering. No caching or snapshots needed.

### Finding 3: HyperFields multiselect supports dynamic options and a searchable UI

Source: `vendor/estebanforge/hyperfields/src/Field.php` and `vendor/estebanforge/hyperfields/src/templates/field-multiselect.php`.

- `Field::setOptions(array $options)` (`Field.php:186`) accepts an associative `[value => label]` array.
- The `field-multiselect.php` template iterates `foreach ($options as $option_value => $option_label)`, rendering each as an `<option>` and as a clickable chip.
- `Field::setEnhanced(bool)` (`Field.php:295`) flips on the search + chip UI. Note: `enhanced` defaults to `true` in the template when unset, so calling `setEnhanced()` is redundant but harmless and self-documenting.
- **No optgroup support exists** in the vendored HyperFields tree (zero `optgroup` matches). Grouping must be expressed in the label string.

### Finding 4: Fuerte's enforcement hooks already fire late enough

`remove_menus()` runs at `admin_menu` priority 999, after all plugins register. `apply_page_restrictions()` already handles the `?page=` direct-access vector. Both are already super-user-gated. This plan extends these methods; it does not rebuild them.

### Finding 5: `$pagenow` is always a bare filename (the over-blocking trap)

`$pagenow` never reflects `post_type` or other query args. This is why the original Step 5 routing was unsound: routing the core slug `edit.php` (Posts) into the `$pagenow` block vector would block every `edit.php` request, including Pages (`edit.php?post_type=page`) and any custom post type list table. Hiding uses exact-string slug matching (safe); blocking by `$pagenow` does not. See [Complexity B](#two-complexities-and-their-solutions).

### Finding 6: Hide + block stops page rendering only

`apply_page_restrictions()` gates both checks on `!wp_doing_ajax()` (`enforcer.php`). Combined with the enforcer early-exit #5 (AJAX only exits when the user lacks `manage_options`), this means: hiding and blocking a plugin's settings page stops direct GET navigation, but a plugin's own `wp_ajax_*` or `admin-post.php` handlers for the same feature are untouched. This is a pre-existing limitation of `restricted_pages` / `restricted_scripts`, now documented as a non-goal.

---

## Goals and Non-Goals

### Goals

- Fix the 1.7.0 regression: the five keys reach the enforcer on all installs.
- Admin picks menus from a searchable list of real, registered items. No slug typing required.
- One selection both hides and blocks, narrowed to avoid over-blocking (see [Complexity B](#two-complexities-and-their-solutions)).
- Discovery distinguishes WordPress Core items from plugin/theme items; both are selectable.
- Selections survive plugin activation/deactivation (no silent loss).
- Manual fallback preserved and reframed as a precision escape hatch.
- Super-user bypass behavior unchanged.

### Non-Goals (explicitly out of scope for v1)

- **Options-level granularity.** Hiding a single checkbox or tab inside a plugin's settings page. Each plugin builds its own UI; no stable hook to target. Hide/block the whole page is the reliable surface.
- **AJAX and admin-post endpoint blocking.** Hide + block stops page rendering. A target plugin's own `wp_ajax_*` / `admin-post.php` handlers are not blocked by this mechanism (Finding 6). Documented in field help text.
- **Capability revocation for custom plugins.** Fuerte uses `DISALLOW_FILE_EDIT` / `DISALLOW_FILE_MODS` for core. For third-party plugins, revoke-cap risks lockouts. Hide + block is the right risk level.
- **Multisite network admin.** `network_admin_menu` and `user_admin_menu` are separate globals. Fuerte today hooks `admin_menu` only. Discovery, hide, and block have zero effect in Network Admin; this must be stated in the UI help text. Follow-up possible.
- **REST API menu hiding.** The REST layer has its own restrictions module.

---

## Architecture (three layers plus a wiring fix)

### Phase 0: Fix the config wiring (regression)

Make `normalize_settings()` emit the five keys as flat top-level keys matching what the enforcer reads. Delete the dead `'advanced_restrictions'` nested block (no consumer reads it). Add the regression test. Bust the config transient on update. This phase makes the existing textareas functional again before any UI work.

### Layer 1: Discovery

New helper methods on `Fuerte_Wp_Helper` that read the live globals and return `[slug => "Label (capability)"]` arrays, each entry tagged Core or foreign.

- `discover_admin_menus()` reads `$GLOBALS['menu']`.
- `discover_admin_submenus()` reads `$GLOBALS['submenu]`, returns `[parent|child => "Parent > Child (cap)"]`.
- `discover_adminbar_nodes()` reads `$wp_admin_bar->get_nodes()` during `admin_bar_menu`.
- `is_core_menu_slug(string $slug): bool` diffs against a known core-slug set.

### Layer 2: UI

Swap the three textareas for `multiselect` fields populated by discovery, with the stale-selection merge. Keep one collapsed manual textarea per field as the precision escape hatch. Grouping uses label-string prefix (`[Core]` / `[Plugin]`) since HyperFields has no optgroups (Finding 3).

### Layer 3: Enforcement (unified hide + block, narrowed)

`remove_menus()` stays as the hide mechanism. A new derivation step feeds hide selections into the block vectors, routed by slug type and excluding the `edit.php` family (Finding 5, Complexity B).

---

## Detailed Implementation

### Step 0 (Phase 0): Fix config wiring (`includes/class-fuerte-wp-config.php`)

In `normalize_settings()`, the five keys currently live nested under `'advanced_restrictions'`. Move them to flat top-level keys in the return array, matching the enforcer's reads:

```php
'removed_menus'         => self::merge_manual(
    $settings['fuertewp_removed_menus'] ?? [],
    self::parse_textarea($settings['fuertewp_removed_menus_manual'] ?? null)
),
'removed_submenus'      => self::merge_manual(
    $settings['fuertewp_removed_submenus'] ?? [],
    self::parse_textarea($settings['fuertewp_removed_submenus_manual'] ?? null)
),
'removed_adminbar_menus'=> self::merge_manual(
    $settings['fuertewp_removed_adminbar_menus'] ?? [],
    self::parse_textarea($settings['fuertewp_removed_adminbar_menus_manual'] ?? null)
),
'restricted_scripts'    => self::parse_textarea($settings['fuertewp_restricted_scripts'] ?? null),
'restricted_pages'      => self::parse_textarea($settings['fuertewp_restricted_pages'] ?? null),
```

Delete the `'advanced_restrictions' => [...]` block. No consumer reads it.

`merge_manual()` is a small private helper: array-merge, trim, drop empties, de-duplicate, preserve `//comment` lines for the scripts field.

The activator/migrator must call `Fuerte_Wp_Config::invalidate_cache()` on the 1.11.0 version bump so stale transients do not mask the fix.

### Step 1: Discovery helpers (`includes/class-fuerte-wp-helper.php`)

Add a constant for the core-slug set and discovery methods.

```php
const CORE_MENU_SLUGS = [
    'index.php', 'edit.php', 'upload.php', 'edit.php?post_type=page',
    'edit-comments.php', 'themes.php', 'plugins.php', 'users.php',
    'tools.php', 'options-general.php', 'profile.php', 'link-manager.php',
];

const AUTO_BLOCKABLE_CORE_SLUGS = [
    // Single-purpose core scripts safe to block by $pagenow.
    // edit.php family is EXCLUDED (Posts/Pages/CPTs share the script).
    'plugins.php', 'users.php', 'themes.php', 'tools.php',
    'edit-comments.php', 'options-general.php', 'upload.php',
    'profile.php', 'link-manager.php', 'index.php',
];

public static function is_core_menu_slug(string $slug): bool
{
    $base = strtok($slug, '?');
    return in_array($slug, self::CORE_MENU_SLUGS, true)
        || in_array($base, self::CORE_MENU_SLUGS, true);
}

public static function is_auto_blockable_core_slug(string $slug): bool
{
    $base = strtok($slug, '?');
    return in_array($slug, self::AUTO_BLOCKABLE_CORE_SLUGS, true)
        || in_array($base, self::AUTO_BLOCKABLE_CORE_SLUGS, true);
}
```

`discover_admin_menus()` reads `$GLOBALS['menu]` and returns `[slug => "[Core|Plugin] Title (cap)"]`. `discover_admin_submenus()` and `discover_adminbar_nodes()` follow the same pattern. Submenu key is `parent|child`. Admin-bar discovery must run during `admin_bar_menu` (see Step 6).

### Step 2: Stale-selection merge

HyperFields renders only the options it is given, so a saved value no longer discovered (plugin uninstalled) would silently vanish on next save. Fix at field-build time:

```php
$discovered = Fuerte_Wp_Helper::discover_admin_menus();
$saved      = Fuerte_Wp_Config::get('removed_menus', []);
foreach ($saved as $slug) {
    if (!isset($discovered[$slug])) {
        $discovered[$slug] = sprintf('[Missing] %s', $slug);
    }
}
```

This guarantees nothing is silently dropped. The admin sees the missing item and can deselect it.

### Step 3: Field swap (`admin/class-fuerte-wp-admin.php`)

Replace the three `textarea` field definitions (Advanced Restrictions tab) with `multiselect` fields using the merged options. Keep the existing field names so existing config and tests are unaffected. Use label-string prefix for grouping (`[Core] Settings` vs `[Plugin] WP Rocket`).

Add three new `textarea` fields immediately after, named `fuertewp_removed_menus_manual`, `fuertewp_removed_submenus_manual`, `fuertewp_removed_adminbar_menus_manual`, under an "Advanced: manual slugs (precision escape hatch)" heading. State in the help text that manual entries are required to block the `edit.php` family (Posts/Pages/CPTs) because the multiselect block is hide-only for those.

### Step 4: Config merge (covered in Step 0)

The `merge_manual()` helper in Step 0 unifies multiselect array + manual textarea lines into one de-duplicated array at load time. No separate config step.

### Step 5: Enforcement unification, narrowed (`includes/class-fuerte-wp-enforcer.php`)

Extend `apply_page_restrictions()` (or add a sibling method called from `apply_immediate_restrictions()`) to derive block vectors from hide selections. Routing rules, fixed per Finding 5:

```php
$block_pages    = $fuertewp['restricted_pages']   ?? []; // explicit ?page= blocks
$block_scripts  = $fuertewp['restricted_scripts'] ?? []; // explicit $pagenow blocks

foreach ($fuertewp['removed_menus'] ?? [] as $slug) {
    if (Fuerte_Wp_Helper::is_auto_blockable_core_slug($slug)) {
        // Single-purpose core script: block by bare $pagenow.
        $block_scripts[] = strtok($slug, '?');
    } elseif (Fuerte_Wp_Helper::is_core_menu_slug($slug)) {
        // edit.php family: HIDE ONLY. Do not auto-block (would hit Pages/CPTs).
        // Admin must add a manual restricted_* entry to block.
        continue;
    } else {
        // Foreign plugin/theme page: block by ?page=.
        $block_pages[] = $slug;
    }
}
foreach ($fuertewp['removed_submenus'] ?? [] as $item) {
    $parts = explode('|', $item);
    if (isset($parts[1])) {
        $block_pages[] = $parts[1]; // submenu children load as ?page=child
    }
}
```

The existing `$pagenow` and `$_REQUEST['page']` checks then use `$block_scripts` and `$block_pages` instead of the raw config keys. The existing `!wp_doing_ajax()` guard stays.

### Step 6: Admin-bar capture (`includes/class-fuerte-wp-hook-manager.php`)

Register a discovery callback on `admin_bar_menu` at priority **9999**, before Fuerte's own `remove_adminbar_menus` (which runs at 999) so blocked nodes stay findable for re-adding. Store discovered node IDs into a transient scoped to the settings-page load for the super user, invalidated on every settings-page render. Reuse the Step 2 stale-selection merge for admin-bar nodes too.

No new hooks are required for hide or block. The block logic runs inside the existing super-user-gated `apply_immediate_restrictions()`.

### Step 7: Index and docs

Run `codegraph index && codegraph sync` and `composer cs:fix`. Update `INDEX.md`, `FAQ.md`, and `README.md` feature list.

---

## Two Complexities and Their Solutions

### Complexity A: Stale selections

**Problem.** Admin hides "WP Rocket", later uninstalls it. The slug `wprocket` is no longer discovered. HyperFields renders only the options it is given, so on the next save the value silently disappears.

**Solution.** Merge discovered options with currently-saved values before building the field (Step 2). Missing items render as `[Missing] wprocket`. The admin sees them and can deselect. No data is ever silently lost. The same pattern applies to admin-bar nodes.

### Complexity B: Slug-to-script routing must not over-block (revised)

**Problem.** `$pagenow` is always a bare filename and never reflects `post_type` (Finding 5). The original routing sent every core slug to the `$pagenow` vector. For `edit.php` this is unsound: WordPress's Posts menu is slug `edit.php`, Pages is a separate menu with slug `edit.php?post_type=page`, and many CPT admin UIs hang off `edit.php?post_type=xyz`. Hiding "Posts" and blocking by `$pagenow == 'edit.php'` would block Pages and every CPT list table. The admin picked one menu; the block silently takes out others.

**Solution.** Narrow the auto-block allowlist to single-purpose core scripts (`AUTO_BLOCKABLE_CORE_SLUGS`). The `edit.php` family (and any slug with a `post_type` query arg) is **hide-only**: `remove_menu_page()` still removes that one nav entry safely (slugs are exact strings), but no automatic block is derived. To block Posts/Pages/CPT access, the admin adds a manual `restricted_scripts` / `restricted_pages` entry. This trades a small UX cost for correctness, and the manual textarea (now the precision escape hatch) covers it. This is a scope reduction worth calling out to users in the field help text.

---

## Configuration Shape

Normalized config after this change (flat keys, matching enforcer reads):

```php
'removed_menus'          => [...],   // multiselect + manual, merged, de-duplicated
'removed_submenus'       => [...],   // multiselect + manual, merged
'removed_adminbar_menus' => [...],   // multiselect + manual, merged
'restricted_scripts'     => [...],   // manual textarea
'restricted_pages'       => [...],   // manual textarea
```

The nested `'advanced_restrictions'` block is removed. File config (`wp-config-fuerte.php`) keeps writing the same keys as plain arrays. No file-config format change.

---

## Testing Plan

Existing tests that must still pass:

- `tests/unit/SuperUserRestrictionBypassTest.php` (bypass gate unchanged).
- `tests/unit/CoreRestrictionsTest.php`.
- `tests/unit/EnforcerMethodsTest.php`.

New tests:

- **`AdvancedRestrictionsWiringTest.php` (regression, Phase 0).** The test the bug hid behind. Exercises `get_config()` through to the values the enforcer reads: set the HyperFields-stored textarea values, call `get_config()`, assert `removed_menus` / `removed_submenus` / `removed_adminbar_menus` / `restricted_scripts` / `restricted_pages` are populated at the FLAT top-level key the enforcer reads. This would have caught the original regression.
- **`MenuDiscoveryTest.php`.** `discover_admin_menus()` returns `[slug => label]` from a mocked `$GLOBALS['menu]`. Core slugs tagged `[Core]`, foreign tagged `[Plugin]`. Capability appears in the label.
- **`MenuBlockRoutingTest.php`.** A foreign slug routes to the `?page=` block vector. A single-purpose core slug (`themes.php`) routes to the `$pagenow` vector. An `edit.php` slug and an `edit.php?post_type=page` slug do NOT produce an auto-block (hide-only). `strtok` normalization is correct.
- **`StaleSelectionMergeTest.php`.** A saved slug not present in discovered options is preserved and labeled `[Missing]`. Merge de-duplicates and preserves comment lines.
- **`MenuManualMergeTest.php`.** Multiselect array + manual textarea lines merge and de-duplicate.

Mocking note: tests use Brain Monkey. `$GLOBALS['menu]` must be set in test setup and cleared in teardown. `wordpress-mocks.php` may need a small addition for `$wp_admin_bar` if admin-bar discovery is unit-tested.

Integration check: in the Docker env, visit the Fuerte settings page as a super user and confirm the multiselects show real plugin/theme menus. Then, as a non-super-admin, confirm a hidden foreign menu is both invisible in the sidebar AND denied on direct URL access. Confirm hiding the Posts menu hides only Posts, and that Pages still loads.

---

## Backwards Compatibility and Migration

- Field names are unchanged. Existing `removed_menus` / `removed_submenus` / `removed_adminbar_menus` values (string or array) keep working.
- `Config::parse_textarea()` already accepts both strings and arrays. The multiselect stores an array; the manual textarea stores a string. Both parse to the same shape.
- The config transient (`fuertewp_config`) must be invalidated on plugin update. The activator/migrator calls `Fuerte_Wp_Config::invalidate_cache()` on the 1.11.0 version bump.
- Sites using file config (`wp-config-fuerte.php`) are unaffected; their arrays flow through unchanged.
- The Phase 0 fix changes behavior: installs that had silently-nonfunctional Advanced Restrictions settings will now apply them. For most admins this is the intended outcome; for any admin who configured the textareas expecting them to do nothing, the menus they listed will now actually hide/block. This is the correct behavior and should be announced in the changelog.

---

## Risks

- **Risk: applying previously-dead settings surprises an admin.** Mitigation: changelog entry is explicit. The Phase 0 fix is its own line ("fix: Advanced Restrictions settings were not applied since 1.7.0").
- **Risk: a menu registers only on a specific sub-screen.** Discovery runs at settings render, so such menus may not appear. Mitigation: the manual textarea fallback covers these. Documented in field help text.
- **Risk: capability-gated menus not visible to the super user.** Rare (super users normally have `manage_options`). Mitigation: manual fallback.
- **Risk: blocking a core menu too aggressively.** Mitigation: super-user bypass means the configuring user is never locked out. The narrowed allowlist prevents the `edit.php` over-block. Document `FUERTEWP_FORCE` implications.
- **Risk: admin-bar node IDs unstable across plugins.** Mitigation: the `[Missing]` merge keeps stale node IDs visible and removable.
- **Pre-existing limitation (not a regression of this plan):** hide + block does not stop a plugin's AJAX or admin-post endpoints (Finding 6). Stated as a non-goal and in field help text.

---

## Rollout Phasing

Single 1.11.0 release, internally staged for safe review. The regression fix is Phase 1 and gets its own changelog line for visibility.

1. **Phase 1 (regression fix):** flatten config keys, delete dead `advanced_restrictions` block, add `AdvancedRestrictionsWiringTest.php`, bust transient. Verifiable in isolation; makes existing textareas functional. Own changelog entry.
2. **Phase 2:** discovery helpers + unit tests. No UI change yet.
3. **Phase 3:** config merge logic + unit tests. No UI change yet.
4. **Phase 4:** UI field swap + manual fallback. Now admin-facing.
5. **Phase 5:** enforcement unification (narrowed routing) + routing tests. Now the security improvement.
6. **Phase 6:** docs (this file, FAQ, README) and version bump with transient invalidation.

Each phase is independently verifiable against the test suite.

---

## Decisions

These were open questions in the first draft and are now resolved (source review + research).

1. **Optgroups: none.** Vendored HyperFields has zero optgroup support. Use label-string prefix `[Core]` / `[Plugin]` from day one.
2. **Admin-bar discovery timing.** Capture at `admin_bar_menu` priority 9999, before Fuerte's own removal at 999, so blocked nodes stay findable. Invalidate the transient on every settings-page render for the super user. Reuse the stale-selection merge pattern.
3. **Manual textareas: keep visible.** Reframed as a precision escape hatch. Given the `edit.php` over-block fix, they are the only way to scope a block to a specific `post_type` variant, so they are functional, not decorative.
4. **Version target: 1.11.0.** Touches enforcement and security semantics and needs a transient invalidation and migration note. A patch release is wrong. The Phase 1 regression fix gets its own changelog line inside 1.11.0.
5. **`edit.php` family: hide-only in v1.** Posts / Pages / CPT hiding removes the nav entry but does not auto-block (would over-block siblings). Blocking requires a manual entry. Traded for correctness; reversible if a per-`post_type` block primitive is added later.

## Implementation Record

Implemented in 1.11.0 across Phases 1-6. Five defects surfaced during source review were folded in rather than shipped verbatim:

- **D1 (admin-bar capture priority) — fixed.** The plan specified capture at `admin_bar_menu` priority 9999 "before" removal at 999. WordPress fires ascending, so 9999 runs AFTER 999 and would capture an already-stripped bar. Capture is registered at priority **900** (`Fuerte_Wp_Enforcer::capture_adminbar_nodes`), before removal at 999. The callback self-gates to the Fuerte settings page for super users so no transient is written on every admin load.
- **D2 (submenu routing) — fixed.** The plan routed every submenu child to the `?page=` vector. Core submenu children (`.php` files under a core parent, e.g. `tools.php|export.php`) load directly via `$pagenow`, never `?page=`, so the unified hide+block would have silently no-op'd for them (the shipped default IS `tools.php|export.php`). `Fuerte_Wp_Helper::derive_block_vectors()` now splits: core child filename -> `$pagenow`, foreign child -> `?page=`.
- **D3 (over-block allowlist) — fixed.** The plan's `AUTO_BLOCKABLE_CORE_SLUGS` included `index.php` (the `/wp-admin` redirect target; blocking strands non-super users) and `profile.php` (every user needs their own profile). Both are kept in `CORE_MENU_SLUGS` (so they tag `[Core]`) but excluded from the blockable list: hide-only.
- **D4 (upgrade defaults) — documented.** Phase 0 activates not only admin-entered values but the hardcoded field defaults (`restricted_scripts` defaults block `export.php`, `update.php`, `update-core.php`). The 1.11.0 changelog carries an explicit note.
- **D5 (dual read of `restricted_scripts`) — handled.** `remove_menus()` already loops `restricted_scripts` flat into `remove_menu_page()` (documented in the field help text). The derivation keeps `restricted_scripts` as an explicit block input and does not re-derive from it, so hide sources are not double-counted.

Test coverage added: `AdvancedRestrictionsWiringTest` (regression), `MenuDiscoveryTest`, `MenuManualMergeTest`, `StaleSelectionMergeTest`, `MenuBlockRoutingTest`. Suite: 169 passed / 437 assertions.
