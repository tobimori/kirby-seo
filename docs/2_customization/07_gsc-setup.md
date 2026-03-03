---
title: Setting up Google Search Console
intro: Connect Search Console with your own Google OAuth credentials
---

By default, the Search Console integration uses a proxy to keep setup simple. If you'd rather connect directly, you can set up your own Google OAuth credentials instead. This requires a Google Cloud project with the Search Console API enabled. The API is free to use.

## Create OAuth credentials

Go to the [Google Cloud Console](https://console.cloud.google.com/) and create a new project, or use an existing one.

Navigate to **APIs & Services** → **Credentials** → **Create Credentials** → **OAuth client ID** and configure it:

- **Application type:** Web application
- **Name:** e.g. "Kirby SEO on example.com"
- **Authorized redirect URIs:** your site URL followed by `/__seo/gsc/callback`, e.g. `https://example.com/__seo/gsc/callback`

Download the JSON file when prompted. You'll need it in the next step.

Then go to **APIs & Services** → **Library**, search for "Google Search Console API" and enable it. Without this, the OAuth flow will succeed but the API requests will fail.

## Add credentials to your config

Place the downloaded JSON file in your `site/config` directory (e.g. `site/config/gsc-credentials.json`), then reference it in your config:

```php
<?php
// site/config/config.php

use Kirby\Data\Json;

return [
  'tobimori.seo' => [
    'searchConsole' => [
      'credentials' => Json::read(__DIR__ . '/gsc-credentials.json'),
    ],
  ],
];
```

## Connect in the Panel

Open the Panel and navigate to any page with the SEO tab. The Google Search Console section now shows a **Connect** button. Click it and authorize with your Google account. Make sure the Google account you use has access to the Search Console property for your site.

After authorizing, select which Search Console property to use. The section starts showing data once the property is selected.
