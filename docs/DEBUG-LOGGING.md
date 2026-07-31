# Debug Logging

Fuerte-WP logs are **off by default**. The plugin never writes to your PHP error log unless you explicitly enable it. This keeps production installs clean and silent.

## How logging is gated

The logger switches on when **either** of two constants is truthy:

| Constant | Scope | Defined in |
|----------|-------|------------|
| `WP_DEBUG` | WordPress global debug mode | `wp-config.php` |
| `FUERTEWP_DEBUG` | Fuerte-WP only | `wp-config.php` |

If both are false (or undefined), no Fuerte-WP lines are written. Logging is a strict OR of the two.

## Enable logging

Add **one** of the following to `wp-config.php`, above the `/* That's all, stop editing! */` line.

**Option A — plugin-only logging** (recommended for targeted debugging; does not enable WordPress's full debug mode):

```php
define( 'FUERTEWP_DEBUG', true );
```

**Option B — full WordPress debug mode** (also enables Fuerte-WP logging as a side effect):

```php
define( 'WP_DEBUG', true );
```

## Where logs go

Fuerte-WP logs via PHP's `error_log()`. The destination depends on your server:

- The PHP error log configured by your host (typical on managed hosting), or
- `wp-content/debug.log` when `WP_DEBUG_LOG` is also `true`.

Every line is prefixed `[Fuerte-WP]`, for example:

```
[Fuerte-WP] [2026-08-04 20:38:07] INFO: Fuerte-WP Enforcer initialized
```

## Disable logging

Remove the line, or set the constant to false:

```php
define( 'FUERTEWP_DEBUG', false );
```

If you enabled `WP_DEBUG` instead, set it back to `false`. Logging stops on the next request.

## Truthiness

Truthiness follows PHP rules for the value you define. Use boolean `true` / `false`. A string such as `'false'` is truthy and will enable logging; `'0'` is falsy and will disable it. To avoid surprises, always use `true` or `false`.

## Related constant

`FUERTEWP_LOG_PREFIX` — overrides the `[Fuerte-WP]` log prefix. Optional; rarely needed.

## History

The opt-in constant was named `FUERTEWP_DEBUG_LOGGING` before version 1.11.1. It was renamed to `FUERTEWP_DEBUG` to match the `WP_DEBUG` naming pattern. If you have the old constant in `wp-config.php`, rename it to `FUERTEWP_DEBUG`.
