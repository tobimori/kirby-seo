---
title: Heading Structure
intro: Check your heading hierarchy while editing
---

Search engines and screen readers rely on headings (H1, H2, H3, ...) to understand the structure of a page. A well-structured page starts with a single H1 and uses the other levels in order, without skipping any.

When headings skip levels (e.g. H2 followed by H4) or when there are multiple H1s, search engines have a harder time figuring out what the page is about. Screen readers use the heading tree to let users jump between sections, so broken hierarchy also affects accessibility.

Most Kirby sites tie heading levels to visual styles: H1 is the largest text, H2 is smaller, and so on. Editors often pick a heading level based on how big they want the text to look, not based on what it means semantically. An H3 after an H1 might look fine on the page, but it tells search engines and screen readers that something is missing.

Kirby SEO has a Panel section that extracts all headings from the current page and displays them as a nested tree. You see the full hierarchy at a glance, and headings that break the structure are highlighted. The section updates as the page content changes, so editors can fix issues while they write.

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
