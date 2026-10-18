---
title: Panel Previews
intro: See how your pages look in search results and social shares
---

Meta titles and descriptions can look different in context than they do in a text field. The SEO tab in the Panel has a preview sidebar that shows how the current page may appear when shared or found in search results. It reloads after you save, which helps you catch issues like truncated titles or missing images before you publish.

There are three preview types:

- **Google**: a search result card with your meta title, description, URL and favicon
- **Facebook**: a social sharing card with OG title, description and image
- **Slack**: a link preview card as Slack shows it when someone pastes a URL

The preview picks up all values through the [Meta Cascade](0_getting-started/1_your-first-meta-tags). If you haven't set an OG title, the preview shows the meta title instead, just like a real crawler would see it.

Keep in mind that Google sometimes decides to show a different title or description than what you set, if it thinks something else on the page is more relevant to the search query. The preview shows the metadata Google can use as a candidate snippet. The actual search result may look different.

On the Site SEO tab, the preview uses the homepage data and URL.

## Choosing which previews to show

By default, all three previews are available. If you only care about Google and Facebook, you can remove Slack from the list:

```php
// site/config/config.php
return [
  'tobimori.seo' => [
    'previews' => ['google', 'facebook'],
  ],
];
```
