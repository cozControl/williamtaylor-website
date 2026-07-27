# Page Publication Permission Matrix

| Capability | Permission |
| --- | --- |
| Submit current draft | `pages.edit` |
| View Review queue and request changes | `pages.review` |
| Approve candidate | `pages.approve` |
| Publish approved candidate | `pages.publish` |
| Schedule approved candidate | `pages.schedule` and `pages.publish` |
| Cancel schedule | `pages.schedule` |
| Unpublish | `pages.unpublish` |

CMS Manager receives all five publishing permissions. Super Administrator continues to use the monitored registered-permission bypass. No new role was added.

Existing installations must preview and then apply `php artisan rbac:align-foundation-registry --apply`, followed by `php artisan rbac:audit`.
