# Landing page UI/UX audit (index.php, styles.css, script.js)

Audited 2026-11-15 against the live page at desktop (1440), tablet and 375px phone widths.

## Purpose check
The landing page serves two audiences: Registrar staff who need to sign in, and visitors
(evaluators, other offices) who need to understand what DATS does. The previous page treated
it as a magazine feature; signing in took two clicks (nav button, then modal) and the
explanation was written for developers rather than Registrar staff.

## Findings

### Critical
1. **Public data leak in "Recently indexed records".** The section printed real document
   IDs, categories, academic years and statuses to unauthenticated visitors. On the current
   database the first three entries shown were "Rejected" documents, which is both a privacy
   problem and a bad first impression. Removed: the public page now shows aggregate counts only.
2. **Sign-in hidden behind a modal.** The primary task of the page needed an extra click and a
   context switch. The form now lives in a native dialog that every Sign in button opens with one click, focus moves straight to the email field, and Escape or the backdrop closes it.
3. **Hero figures undermined trust.** "1 archived document, 100% completion" and "5 student
   profiles" were presented as headline proof points. Small numbers are now shown as a quiet
   stats band with an honest "archive is being populated" state when the totals are low.

### High
4. **Developer jargon on a staff-facing page.** "SQL synced", "Live MySQL", "Powered by
   vw_archive_analytics", "MIME signature", "SHA-256", "PDO prepared statements with
   non-emulated bindings", "FULLTEXT". Rewritten in plain language for Registrar staff.
5. **Legibility of hero copy.** White serif text over a busy bookshelf photo with a light
   scrim; on phones the whole first screen was photo with two buttons and no information.
6. **Image weight.** Four stock JPEGs (about 2 MB) plus a 700 KB PNG seal rendered at 44px.
   Two hero images loaded eagerly for a decorative crossfade. Reduced to two photos, both
   lazy-loaded, and a 192px seal (img/ZPPSU-192.png) for the brand marks.
7. **Stock photos of other institutions dominated the layout** (Beinecke, ICRC, Czech national
   library) and the Beinecke image was used twice. Photos are now supporting, not structural.

### Medium
8. **Navigation state was static.** "Overview" stayed highlighted regardless of scroll position.
   Added scroll-spy and a border on the sticky bar once the page scrolls.
9. **Dead control.** "Remember me" was never read by login.php. Removed.
10. **Errors only in a toast.** Failed sign-in showed a transient toast at the bottom-right,
    away from the form. Errors now render inline under the form with role="alert".
11. **Signed-in users saw the marketing page again.** The page now recognises an active session
    and offers "Continue to dashboard" instead of the form.
12. **Activity feed colours were arbitrary** (assigned by row index, not by status).
13. **Type mismatch with the product.** Landing used Young Serif and Source Serif 4; every
    authenticated page uses Manrope and DM Mono. The new page uses Manrope and DM Mono for
    UI with Fraunces for display headings only, so the hand-off into the dashboard feels
    continuous.
14. **No help path.** Nothing told a new staff member how to get an account or what students
    should do. Added a short Help section with contact details.

### Low
15. The live clock ("Protected session access · Nov 15, 2026, 8:48 PM") carried no meaning for
    a visitor. Removed.
16. Footer image credits ran as one long paragraph. Kept, trimmed to the photos actually used.

## What was kept
- Maroon and gold institutional palette, the ZPPSU seal, Lucide icons, Google Fonts.
- login.php contract (JSON email/password, redirect on success) is unchanged.
- Skip link, reduced-motion handling, mobile drawer navigation, Escape-to-close.
- Creative Commons attribution for the Wikimedia photos still in use.
