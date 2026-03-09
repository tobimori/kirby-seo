---
title: Panel Previews
intro: See how your pages look in search results and social shares
---

Meta titles and descriptions can look different in context than they do in a text field. The SEO tab in the Panel has a live preview sidebar that shows how the current page will appear when shared or found in search results. It updates as you type, so you can catch issues like truncated titles or missing images before you publish.

There are three preview types:

- **Google**: a search result card with your meta title, description, URL and favicon
- **Facebook**: a social sharing card with OG title, description and image
- **Slack**: a link preview card as Slack shows it when someone pastes a URL

The preview picks up all values through the [Meta Cascade](0_getting-started/1_your-first-meta-tags). If you haven't set an OG title, the preview shows the meta title instead, just like a real crawler would see it.

Keep in mind that Google sometimes decides to show a different title or description than what you set, if it thinks something else on the page is more relevant to the search query. The preview shows what you _tell_ Google to display, but the actual search result may look different. This is normal and not something we can control.

On the Site SEO tab, the preview shows data for the homepage since the site itself doesn't have a URL.

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
