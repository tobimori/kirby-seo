---
title: Permissions
intro: Control access to plugin features by user role
---

Kirby SEO registers permissions that you can restrict per [user role](https://getkirby.com/docs/guide/users/permissions). By default, all permissions are granted.

## Available permissions

| Permission        | Controls                                                                         |
| ----------------- | -------------------------------------------------------------------------------- |
| `tobimori.seo.ai` | Access to all AI Assist features: generating, editing, and customizing meta text |

More permissions will be added in future releases.

## Restricting access

Set a permission to `false` in a role's blueprint to deny it:

```yaml
# site/blueprints/users/editor.yml

title: Editor
permissions:
  tobimori.seo:
    ai: false
```

You can also deny all current and future permissions at once using a wildcard:

```yaml
permissions:
  tobimori.seo:
    *: false
```

Users without a permission will not see the corresponding UI elements in the Panel, and API requests will be rejected.
