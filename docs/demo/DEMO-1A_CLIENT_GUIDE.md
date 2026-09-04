# Client Demo guide

The Client Demo workflow is available only in an isolated environment configured by the project team.

1. Log in with the authorized demo administrator account and open **Admin**.
2. Confirm the banner: **Demo Environment — Content changes affect the client testing website only.** If the banner reports that the global publication switch is off, preview may still be used but publication remains disabled.
3. Open the **Client Demo** dashboard section. Review the overall readiness result and its actionable failures before editing.
4. Use **Global content workspace** for contact details, WhatsApp, social links, footer copy, brand details, and supported global Media. Use the existing **Navigation**, **Announcements**, and **Media** links for their dedicated editors.
5. For supported Media fields, search the ready-image cards and select the intended thumbnail. Only ready, confirmed, active images with safe effective alt text are offered.
6. Save an immutable draft with a concise change summary.
7. Open **Secure preview**. The preview is temporary, signed, bound to the exact revision, excluded from public caching, and marked `noindex,nofollow` and `no-store`.
8. Confirm the public demo is unchanged before publication.
9. Submit for review, approve with an authorized reviewer where separation of duties applies, and publish only when readiness passes.
10. Confirm the isolated demo storefront reflects the approved revision and that its footer **Admin** link still leads to protected `/admin`.
11. Use **Unpublish** with a high-impact reason to restore the static fallback. Emergency unpublish remains permission-controlled.
12. Log out and confirm `/admin` redirects to login.

Never use production credentials or production content. The banner is informational and never grants authorization; route middleware, permissions, policies, and server-side action checks remain authoritative.
