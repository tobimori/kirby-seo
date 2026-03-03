---
title: Opting Out of AI Training
intro: Signal to AI crawlers that your content should not be used for training
---

The `noai` and `noimageai` robot directives tell AI crawlers not to use your content or images for training. These are not an official standard, but were introduced by [DeviantArt and Spawning](https://www.deviantart.com/team/journal/UPDATE-All-Deviations-Are-Opted-Out-of-AI-Datasets-934500371) and are respected by some AI providers. Like all robot directives, they are signals, not hard blocks.

Kirby SEO has a `types` option that controls which robot directives are available. Add `ai` and `imageai` to the list:

```php
<?php
// site/config/config.php

return [
  'tobimori.seo' => [
    'robots' => [
      'types' => ['index', 'follow', 'archive', 'imageindex', 'snippet', 'ai', 'imageai'],
    ],
  ],
];
```

The new fields show up in the robots section of the SEO tab. If you previously disabled `robots.pageSettings`, you need to re-enable it for the fields to appear.

By default, all directives are set to "Yes" (allowed). To opt out of AI training, an editor needs to set the AI Training and AI Image Training fields to "No". The plugin then outputs `noai` and `noimageai` in the robots meta tag.

If you want to opt out for all pages at once, set it on the Site level instead of per page. Translations for the field labels are included in the plugin.
