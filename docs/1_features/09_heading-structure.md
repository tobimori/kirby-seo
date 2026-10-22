---
title: Heading Structure
intro: Check your heading hierarchy while editing
---

Search engines and screen readers rely on headings (H1, H2, H3, ...) to understand the structure of a page. Kirby SEO recommends starting with a single H1 and using the other levels in order, without skipping any.

Skipped heading levels can make the page structure harder to follow. The plugin also flags multiple H1s to keep one clear top-level heading. Screen readers use the heading tree to let users jump between sections. A broken hierarchy also affects accessibility.

Most Kirby sites tie heading levels to visual styles: H1 is the largest text, H2 is smaller, and so on. Editors often pick a heading level based on how big they want the text to look, not based on what it means semantically. An H3 after an H1 might look fine on the page, but it tells search engines and screen readers that something is missing.

Kirby SEO has a Panel section that extracts all headings from the current page and displays them as a nested tree. You see the full hierarchy at a glance, and headings that break the structure are highlighted. The section reloads after the page content is saved, and editors can then fix any highlighted issues.

## Adding the section to your blueprint

Place the section next to your content editor, for example in a sidebar column beside your blocks or layout field:

```yaml
# site/blueprints/pages/default.yml
tabs:
  content:
    columns:
      - width: 2/3
        fields:
          blocks:
            type: blocks
      - width: 1/3
        sections:
          headingStructure:
            type: heading-structure
```
