# Frequently Asked Questions

## Nginx security rules

```
# BEGIN Fuerte-WP
location ~ wp-admin/install(-helper)?\.php {
    deny all;
}

location ~* /(?:uploads|files)/.*.php$ {
	deny all;
	access_log off;
	log_not_found off;
}
# END Fuerte-WP
```

