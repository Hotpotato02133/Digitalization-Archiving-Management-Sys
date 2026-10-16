# SDD ledger — plan: docs/superpowers/plans/2026-09-22-scan-student-registration.md

Setup: Ruling: The workspace is not a Git repository, so native/manual worktrees, task-start/task-done commit ranges, and commits are unavailable — execute in place with per-task test evidence recorded here — cost if wrong: changes lack isolated rollback and Git history.
Pre-flight: Task 1 produces scan-flow helpers consumed by Tasks 4 and 5 — names and payload shapes agree.
Pre-flight: Task 2 produces validation/lookup/insert helpers consumed by Task 3 — transaction ownership remains in Task 3 as required by the spec.
Pre-flight: Task 3 produces explicit multipart mode handling consumed by Task 4 — field names agree with the backend contract.
Pre-flight: Task 4 emits student fields maintained by Task 5 — nationality and stable ID naming agree.
Pre-flight: Task 6 consumes every prior task's verification commands — no interface conflict found.

Task 1: complete (no Git commits available; tests: node --test tests\js\scan-flow.test.cjs → 5/5 pass)

Task 2: complete (no Git commits available; tests: PHP intake test PASS; lib/scan-intake.php and api.php lint clean)

Task 3: complete (no Git commits available; tests: PHP intake transaction/mode/cleanup test PASS; backend lint clean)

Task 4: complete (no Git commits available; tests: Node scan-flow 8/8 pass; admin/script.js and data-sync.js syntax clean; admin/scan.php lint clean)

Task 5: complete (no Git commits available; tests: Node scan-flow 9/9 pass; Students Information PHP/JS syntax clean)
Final: fixed failed-upload record creation — explicit-mode upload error tests RED→GREEN, PHP suite PASS.
Final: fixed file-derived category leakage — categoryForReplacement test RED→GREEN, Node suite green.
Final: fixed stale asynchronous OCR overwrite — isCurrentOcrResult test RED→GREEN, Node suite green.
Final: fixed missing newly registered student in local search — mergeStudentReferences test RED→GREEN, Node suite green.
Final: fixed inclusive 2100 academic-year contract — JS and PHP boundary tests RED→GREEN, suites green.
Final: minor (deferred): positive legacy category_id can bypass the active-category lookup; the revised scanner sends category_code and is protected.
Final: minor (deferred): Arrow keys after Escape can update a hidden suggestions list until focus/input reopens it.
Final: Ruling: live MariaDB transaction/commit and physical approval-to-archive movement were not browser-tested — existing review/archive code was left unchanged and isolated SQLite tests cover transaction semantics — cost if wrong: a deployment-specific integration failure could remain.
Final: Ruling: screen-reader and responsive layout were not fully browser-tested because the available browser has no authenticated session and credentials were not changed or bypassed — semantic ARIA markup, keyboard handlers, and responsive CSS were source-checked — cost if wrong: an accessibility or breakpoint defect could remain.
Final: Ruling: combined create_document rollback and filesystem compensation are covered at helper boundaries rather than through a live multipart integration test — code uses the tested helpers around one transaction — cost if wrong: an interaction defect between upload handling and MariaDB could leave inconsistent state.
Final: Ruling: deleted/inactive/yearless student variants are enforced by the lookup query and year normalizer but do not each have a dedicated regression assertion — cost if wrong: future changes could weaken those branches without a targeted test failure.
Final review: fresh-context reviewer; four Important findings and the 2100 spec mismatch fixed in one RED→GREEN pass.
Task 6: complete with recorded browser limitations (tests: Node 12/12 pass; PHP intake PASS; JS syntax clean; PHP lint 19/19; localhost scan route redirects 302 to login; scan-flow asset 200; ambiguous scan name submission absent)
