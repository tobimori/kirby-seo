# Migrating to 1.0.0

## Canonical URLs

The option `tobimori.seo.canonicalIncludesWWW` from Meta Knight has been removed. Instead, you should specify a canonical URL base in your config:

```php
// site/config/config.php
return [
  'tobimori.seo.canonicalBase' => 'https://www.example.com',
];
```

## Miscellaneous

- The Twitter preview has been removed. Twitter Meta tags are still being generated, but the preview is no longer available due to some changes on X/Twitter. If you have manually set the `tobimori.seo.previews` option, make sure to remove it from your config.
