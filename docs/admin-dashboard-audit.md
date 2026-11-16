# Admin dashboard UI/UX audit (admin/index.php)

Audited 2026-11-16. Sources: the live page screenshot supplied with the empty database,
a static copy of the same markup rendered with sample data at 1440px, 900px and 375px,
computed styles and contrast ratios read from the browser, and the stylesheets that
build the page: `admin/styles.css`, `dashboard-buttons.css`, and the render code in
`data-sync.js` and `admin/script.js`.

Scope: visual design, layout, typography, hierarchy, responsiveness, and interaction
affordances only. No feature, data source, endpoint, or behaviour is in question.
Every `data-dashboard*`, `data-attention`, `data-greeting`, `data-current-datetime`
hook and every element id the scripts use must survive the makeover unchanged.

## Summary

The dashboard has a sound structure: four KPI tiles, two rows of analytic panels,
an activity feed, quick actions. The institutional maroon and gold palette is
appropriate and consistent with the rest of the product. What holds it back is
execution detail rather than concept:

1. The type scale is too small for an operational screen. Most body text is 8 to 10px
   and the chart axis labels are 7px. Nothing except headings is comfortable to read.
2. The page is flat. Every surface is a 1px-bordered white box with square corners
   and no elevation, so the KPI row, the panels and the quick actions all carry equal
   visual weight and nothing guides the eye.
3. The empty state (the state the Registrar sees today) reads as a broken dashboard:
   eight zero-height bars, four "0 of n" rows, a scrolling list of nine "0 archived
   records" rows, and a 0.0% tile painted in the strongest colour on the page.
4. Phone layout is functional but unrefined: a 168px-tall tile for a single number,
   a drawer with no scrim or close affordance, a bar chart that scrolls sideways
   inside its card, and a hamburger that is not aligned with the title row.

## Measurements (desktop, 1440px)

| Element | Size | Weight | Contrast | Note |
| --- | --- | --- | --- | --- |
| Sidebar links | 11px | 600 | 1.0 on hover tint, fine on rest | Below the 12px floor for navigation |
| KPI tile label | 10px | 400 | 4.9:1 | Passes only because it is grey on white |
| KPI supporting line | 9px | 400 | 4.9:1 | |
| Panel kickers, dates | 9px mono | 400 | 4.5 to 11:1 | Uppercase mono at 9px is hard to scan |
| List row title | 10px | 700 | 17:1 | |
| List row value | 8px mono | 400 | 4.9:1 | Primary data of the panel at 8px |
| Chart axis labels | 7px mono | 400 | 4.9:1 | Smallest text on the page |
| Attention labels | 8px mono | 400 | 4.5:1 | |
| Activity meta / status pill | 8px mono | 400 | 4.9 to 10:1 | |
| Footer | 8px mono | 400 | 4.5:1 | |
| Quick action title | 10px | 700 | 17:1 | |
| Body line-height | normal | | | No set leading anywhere |

Colour contrast passes WCAG AA everywhere measured. Readability fails on size,
not colour. Focus rings exist for `.gold-button` and the other button classes in
`dashboard-buttons.css`, but sidebar links, panel links and quick-action cards show
no focus indicator at all (outline none, no box-shadow).

## Findings

### High

1. **Type scale.** Twelve distinct font sizes are used between 7px and 29px, nine of
   them under 11px. The root stylesheet already defines a sensible scale
   (`--text-xs` 13px, `--text-sm` 14.4px, `--text-md` 16px) that the admin sheet
   ignores. Recommendation: rebuild on a 12 / 13 / 14 / 16 / 20 / 28 / 36 scale with
   explicit line-heights (1.3 headings, 1.5 body). Mono stays for timestamps,
   counts and kickers but never below 11px.

2. **No hierarchy between surfaces.** KPI tiles, analytic panels, quick actions and
   the attention sub-tiles are all `1px solid var(--line)` on white with no radius
   and no shadow. The root stylesheet defines `--radius`, `--radius-lg`,
   `--shadow-card` and `--shadow-float`; none are used here. Recommendation: one
   card recipe (12px radius, hairline border, soft card shadow, 24px padding) for
   panels; KPI tiles slightly denser; sub-tiles inside panels as tinted fills with
   no border; quick actions as lighter, borderless rows or ghost cards so they sit
   below the analytics in weight.

3. **Empty state.** With no archived records the page shows an eight-column chart
   of zero-height bars with a "0" over each, a coverage list of "0 of 2 students ·
   0.0%", a nine-row category list of "0 archived records", a "+0 vs last week"
   trend and a 0.0% KPI in the solid maroon tile. Recommendation (presentation
   only, same data): show a quiet empty message inside the chart and the two lists
   when every value is zero ("No records archived in the last eight weeks yet"),
   render zero bars as a faint baseline instead of an empty track, and tone the
   trend line to neutral when the change is zero. The JS already writes a text
   fallback when arrays are empty; the zero-filled case needs the same treatment
   via CSS classes the existing renderer can add without touching data logic.

4. **The strongest colour on the page is spent on the weakest metric.** The solid
   maroon tile ("Search-ready records") draws the eye first. Against a 0.0% value it
   reads as an alarm. Recommendation: make the four tiles equal in weight and use
   colour only on the value or progress bar, or move the emphasis to "Archived
   records", which is the headline figure of an archive.

5. **Phone drawer has no scrim, no close control and no scroll lock.** The sidebar
   slides over the content at 640px and below but the page behind stays bright,
   remains scrollable, and the only way to close is the hamburger that the drawer
   now partly covers. Recommendation: add a backdrop that closes on tap, an inline
   close button in the drawer head, lock body scroll while open, and close on
   Escape. (All UI chrome; the existing toggle handler stays.)

### Medium

6. **KPI tiles are padded for a value they do not have.** `min-height: 168px` leaves
   two of the four tiles with ~50px of dead space under the caption; on a phone each
   tile is a 168px block for one number. Recommendation: drop the fixed minimum and
   let the grid row align them; reserve the progress-bar slot with a consistent
   spacer instead.

7. **Lists scroll inside cards.** Category list, coverage list and activity feed are
   capped at 340px with an inner scrollbar. At 900px the category list shows a
   scrollbar beside nine short rows; on a phone it is scroll-within-scroll.
   Recommendation: remove the inner scroll on narrow screens, and on desktop raise
   the cap or let the two side-by-side panels stretch to match height.

8. **Bar chart.** Bars are 58% of a 38px column, labels are 7px, values float 20px
   above the track with no baseline, and the grid overflows sideways on phones
   (367px of content in 284px). Recommendation: full-width bars with a visible
   baseline, 11px labels, values inside or directly on top of bars, and a two-row
   or abbreviated label layout under 480px instead of horizontal scrolling.

9. **Progress rows use two different fills for the same component.** Coverage rows
   fill gold, category rows fill maroon, KPI progress fills gold. Recommendation:
   one fill colour for "share of a whole" (coverage) and a neutral ink fill for
   "relative size" (categories), applied consistently.

10. **Header composition.** The eyebrow "Administrator workspace" sits above a 24px
    greeting with 29px of top padding, then the content starts with another kicker
    (the live clock) and a 29px "Archive overview". Two stacked kicker + heading
    pairs within 180px is one too many. Recommendation: keep the greeting in the top
    bar but drop its eyebrow (the sidebar brand already says whose workspace it
    is), and let "Archive overview" carry the page heading alone with the clock
    beside the export button.

11. **The notification dot is always on.** The badge `<span>` is rendered in the
    markup unconditionally, so a gold dot appears on the bell even with no
    notifications. The script only upgrades it to a counted badge. Recommendation:
    hide the dot by default in CSS and show it only when the script adds the
    `notification-count` class (a CSS change, not a logic change).

12. **Focus visibility.** Sidebar links, the panel "View files" link, quick-action
    cards and the user chip have no visible focus state. Recommendation: a shared
    `:focus-visible` ring (2px gold outline with offset) on every interactive element
    in the shell.

13. **Quick actions sit below the fold with the same weight as analytics.** They are
    the only navigation-to-task elements on the page but appear last, styled like
    data cards. Recommendation: lighter, more button-like treatment with an arrow
    affordance; consider placing them beside the page heading on wide screens.

### Low

14. Sidebar: active state is a 2px gold left border inside a 3px-radius pill, so the
    border is clipped at the corners. Nav items are 48px tall with 11px labels; the
    label should grow to 13px and the row shrink to 40 to 44px.
15. "Archived records" tile icon (`archive`) and "Students Information" nav icon
    render at 16px; the Lucide set is designed for 18 to 20px at these weights.
16. Footer is 8px uppercase mono; it is the least legible text on the page and
    carries the product name. 11px, sentence case.
17. Toast is anchored bottom-right with a 3px gold edge and 11px text; fine on
    desktop, but on phones it overlaps the last quick action. Bottom-centre with a
    radius matches the rest of the system.
18. The `.page-intro` sentence duplicates the panel titles beneath it. Shorter or
    removed.
19. `html { scroll-behavior: smooth }` with no reduced-motion guard in the admin
    sheet; the root sheet has one, the admin sheet does not.
20. Dates mix two formats on one screen: "Monday, November 16, 2026 at 6:40:54 AM" in
    the kicker, "10/13/2026, 5:48:37 PM" in the feed, "Sep 21" in the chart. Seconds
    in the kicker tick every second next to a static dashboard. Presentation
    formatting only; recommend one medium date format and no seconds.

## What is already good and should be kept

- Palette, seal, Manrope + DM Mono pairing, Lucide icon set.
- Information architecture: KPI row, coverage + throughput, composition + quality,
  activity, actions. The order is right.
- Accessible roles already in the markup: `role="progressbar"` with values,
  `aria-label` on charts, `role="alert"` on the error line, `aria-expanded` on the
  mobile menu, `aria-current="page"`.
- Responsive breakpoints at 850px and 640px exist and work; they need refinement,
  not replacement.
- Button system in `dashboard-buttons.css` (fixed heights, focus rings, hover).

## Makeover plan (presentation only)

Files: `admin/styles.css` (dashboard rules only), `admin/index.php` (class names and
small wrappers only, every hook attribute and id untouched). No change to
`data-sync.js`, `api.php`, `admin/script.js` logic, or any other admin page.

1. Introduce a dashboard token block: type scale, line-heights, radius, shadows,
   spacing; reuse the root sheet's names where they exist.
2. Rebuild the shell: sidebar label sizes, active state, focus rings; top bar with a
   single heading row; phone drawer with scrim, close control and scroll lock
   (CSS plus a few lines of UI-only JS that reuse the existing `open` class).
3. Card recipe applied to KPI tiles, panels, attention sub-tiles, quick actions.
4. Type pass: every text node to the new scale, no text under 11px.
5. Chart and list pass: bars, baselines, labels, progress fills, inner-scroll
   removal on narrow screens, equal-height panel pairs on desktop.
6. Empty and zero states styled via classes (chart, lists, trend line, KPI tint).
7. Phone pass: tile heights, chart label layout, toast position, quick-action rows.
8. Re-verify at 1440 / 1024 / 768 / 375 and with the empty database.

Because other admin pages share `admin/styles.css`, dashboard-specific rules should
be scoped under `[data-dashboard-root]` or new class names so the makeover does not
leak into scan, files, students or users pages.

## Applied on 2026-11-16

The makeover was implemented as a dashboard-only layer so no other admin page changes:

- `admin/dashboard.css` (new): scoped under `body.dashboard-page`; tokens, type scale
  (nothing under 11px), card recipe, sidebar, top bar, KPI tiles, panels, lists,
  chart, attention tiles, activity feed, quick actions, footer, toast, drawer chrome,
  breakpoints at 1024 / 850 / 640 / 400, reduced-motion guard.
- `admin/dashboard-ui.js` (new): presentation helpers only. Adds `is-empty`,
  `is-zero`, `has-issues`, `is-clear` and `is-neutral` classes by reading the values
  the existing renderer writes, and gives the phone drawer a scrim, close button,
  Escape key and body scroll lock by reusing the existing hamburger toggle.
- `admin/index.php`: `class="dashboard-page"` on `<body>`, the new stylesheet and
  script links, a close button inside the sidebar and a scrim element after it.
  Every `data-*` hook, id and link target is unchanged.

Covered findings: 1 to 19. Not changed: finding 20 (date formats), because the clock
and feed timestamps are formatted in `data-sync.js`, which 17 pages share.

Follow-up candidates, outside this page: apply the same sidebar and top-bar treatment
to the other admin pages so navigation looks the same everywhere, and move the
notification-dot rule and focus rings into the shared sheet.

## File Scanning page (admin/scan.php), applied 2026-11-16

Same approach as the dashboard: `admin/scan.css` (new) is loaded last and scoped
under `body.scan-page`; `admin/scan.php` gained that body class and the link. The
shared shell (sidebar, top bar, drawer) comes from `admin/shell.css`. No script, id,
input name, or rendered markup changed.

What the audit of the page found and what the sheet changes:
- Two different content widths (760px upload column over a 1180px form) made the
  page look off-centre. All three blocks now share one 1180px column and one card
  recipe, with step markers 1 and 2 on the upload and record-details cards.
- Status pills in "Recently archived" were unstyled (the `.pill` class had no rule on
  this page) and rendered as plain text. Styled as success and pending pills.
- Form controls were 10px text in 1px boxes with no focus ring beyond a border
  colour. Now 14px text, 44px tall, rounded, with a maroon focus ring, hover border,
  custom select chevron, and a tinted read-only state.
- The student mode radios are presented as a segmented control (native inputs kept,
  visually hidden, focus ring via `:has`).
- Drop zone, preview cover, toolbar, thumbnails, OCR banner, suggestions dropdown
  and the upload summary line were re-scaled to the 11 to 16px scale; the summary
  turns green once a file is ready (via the icon the script already swaps in).
- Phone: single-column form, full-width primary button, segmented control in a row,
  stacked recent rows with the pill under the title.

## Gradesheets page (admin/gradesheets.php), applied 2026-11-16

Same approach: `admin/gradesheets-ui.css` (new) is loaded after `gradesheets.css` and
`shell.css`, scoped under `body.gradesheets-page`; `admin/gradesheets.php` gained the
body class and the link. No script, id, input name, toggled class or rendered markup
changed. The page already had a coherent `gs-*` component set, so the work was scale
and finish rather than structure:

- Type moved to the 11 to 16px scale: table cells 13px with 11px mono headers, grade
  values 14px, pills and flags 11px, form labels 13px, inputs 14px in 44px fields.
- The primary "Upload gradesheets" action is now a gold button (it rendered as a grey
  outlined box because this page does not load `dashboard-buttons.css`); ghost
  buttons, icon buttons and the modal close button got consistent heights, radii,
  hover and focus states.
- Panels, the empty state, student summary, term cards, table wrappers, queue list,
  progress box and all four dialogs use the same card recipe as the other pages, with
  sticky table headers inside scrolling wrappers.
- Search boxes are 44px with a focus ring; selects get a custom chevron; form error
  and pending notes are tinted rounded rows.
- Hidden rows inside the edit and add-student forms are forced hidden; the shared
  `display: grid` on form labels previously overrode the `hidden` attribute.
- Phone: stacked heading with full-width upload button, two equal history action
  buttons, stacked summary, tables scroll inside their wrapper, modal actions stretch.

## Student Files page (admin/files.php), applied 2026-11-16

Same approach: `admin/files-ui.css` (new) is loaded after the page's inline styles,
`dashboard-tables.css`, `dashboard-buttons.css` and `shell.css`, scoped under
`body.files-page`; `admin/files.php` gained the body class and the link. No script,
id, data hook or rendered markup changed.

- Heading, year filter and panel share the other pages' scale: 32px title, 14px
  intro, 42px action buttons, a 44px year select with a custom chevron, a 44px search
  box with a focus ring, and a rounded "Total Students" chip.
- Student table: 11px mono sortable headers, 13px cells, 36px avatars, 14px names,
  36px "View Files" buttons, square delete buttons, a maroon active page number, and
  a rounded, bordered table wrapper.
- Dialogs (add folder, delete folder, final confirmation, remove student, delete
  document, purge, recycle bin, student files) get the shared rounded recipe, 22px
  titles, 13px copy, 42px footer buttons, a larger year stepper and 32px year chips.
- Student files dialog: rounded meta boxes, a bulk toolbar, 44px file icons, 14px file
  names, 36px View / Download / Delete actions, and a gold ring on the selected file.
- Phone: the shared stacked-card table layout labels cells from `data-label`, which
  this page's rows do not carry, so labels are supplied per column in CSS; the table
  is forced to block layout so the name cell gets the full width; filters, heading
  actions, meta boxes and file actions stack to full width.

## Students Information page (admin/students.php), applied 2026-11-16

Same approach: `admin/students-ui.css` (new) is loaded after the page's own sheets,
the shared table and button sheets and `shell.css`, scoped under
`body.students-page`; `admin/students.php` gained the body class and the link. No
script, id, data hook or rendered markup changed.

- Heading, summary tiles, filter toolbar and directory panel share the other pages'
  card recipe and scale: 32px title, 32px tile figures with 12px mono captions, a 44px
  search box and 44px filter selects with a custom chevron, 11px mono table headers,
  13px cells, 36px avatars, 14px student names, a 12px mono document-count line, 11px
  status badges and a proper "Actions" button (it previously stacked its icon above
  its label); the row action menu is a rounded, shadowed list with 13px items.
- Add / edit student dialog: 22px title, 13px copy, 13px labels, 44px fields with a
  focus ring, the injected academic fields separated by a rule, 42px save button.
- Import dialog: 13px copy, dashed file picker with a styled button, 44px year field,
  rounded summary tiles with 26px figures, 13px result table with 11px mono headers,
  10px mono tags, and a styled Close button (the ghost style only existed for the
  heading on this page).
- Gradesheet dialog: radius, chip, stat caption, table and button sizes brought to
  the same scale; its structure is unchanged.
- Phone: labelled stacked cards for the fourteen-column table, with the age kept on
  the birthdate row; three compact summary tiles in one row; stacked toolbar; full-
  width heading buttons; single-column dialog forms.
