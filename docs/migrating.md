# Migrating to 1.0.0

## Canonical URLs

The option `tobimori.seo.canonicalIncludesWWW` from Meta Knight has been removed. Instead, you should specify a canonical URL base in your config:

```php
// site/config/config.php
return [
  'tobimori.seo.canonicalBase' => 'https://www.example.com',
];
```

You can also use a function that returns the canonical URL. This is helpful for specialized setups that run explicitly on multiple domains.

## Miscellaneous

- The Twitter preview has been removed. Twitter Meta tags are still being generated, but the preview is no longer available, because I don't want to take on the updating burden. If you have manually set the `tobimori.seo.previews` option, make sure to remove it from your config.
