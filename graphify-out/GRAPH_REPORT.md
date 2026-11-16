# Graph Report - Digitalization-Archiving-Management-System  (2026-11-15)

## Corpus Check
- 147 files · ~162,367 words
- Verdict: corpus is large enough that graph structure adds value.
- Unclassified: 27 file(s) not represented in the graph (top: .css 17, (none) 9, .example 1)

## Summary
- 479 nodes · 891 edges · 64 communities (29 shown, 35 thin omitted)
- Extraction: 91% EXTRACTED · 9% INFERRED · 0% AMBIGUOUS · INFERRED: 84 edges (avg confidence: 0.85)
- Token cost: 0 input · 0 output

## Community Hubs (Navigation)
- api.php, gradesheet-names.php, gradesheet-parser.php
- api.php, scan-intake.php, scan_intake_test.php
- api.php
- api.php
- api.php, student-import.php
- auth.php, database.php
- gradesheet-names.php, gradesheet_names_test.php
- sw.js
- api.php, scan-intake.php
- dats.sql
- student-actions.test.cjs
- ai-service.php, api.php
- api.php, database_backup_test.php
- README.md, ZPPSU.png, dats.sql
- api.php, archive-workflow.php
- api.php
- api.php
- dashboard-analytics.php
- database-backup.php
- document-duplicate.php
- direct-archive-flow.test.cjs
- dashboard-consolidation.test.cjs
- gradesheet-flow.js, gradesheet-view.test.cjs
- scan-flow.js, scan-flow.test.cjs
- document-category-cards.test.js
- student-import.test.cjs
- student-gradesheet-view.php
- api.php
- api.php
- env.php
- index.php, login.php, script.js
- dashboard_analytics_test.php

## God Nodes (most connected - your core abstractions)
1. `dats_validate_new_student_payload()` - 15 edges
2. `associate_gradesheet_records()` - 12 edges
3. `safe_document_path()` - 11 edges
4. `create_document()` - 10 edges
5. `dats_current_user()` - 10 edges
6. `dats_resolve_intake_student()` - 10 edges
7. `dats_record_gradesheet_batch()` - 9 edges
8. `dats_resolve_gradesheet_ocr()` - 9 edges
9. `{closure#26}()` - 9 edges
10. `dats_gradesheet_rows_in_line()` - 9 edges

## Surprising Connections (you probably didn't know these)
- `handle_gradesheet_batch_file()` --calls--> `dats_json()`  [INFERRED]
  api.php → config/database.php
- `{closure#11}()` --calls--> `dats_run_transaction()`  [INFERRED]
  tests/php/scan_intake_test.php → lib/scan-intake.php
- `index.php (Public Landing & Login Modal)` --references--> `img/ZPPSU.png (ZPPSU Institutional Seal)`  [EXTRACTED]
  index.php → img/ZPPSU.png
- `get_analytics()` --calls--> `dats_get_dashboard_analytics()`  [INFERRED]
  api.php → lib/dashboard-analytics.php
- `associate_gradesheet_records()` --calls--> `dats_gn_reasons()`  [INFERRED]
  api.php → lib/gradesheet-names.php

## Import Cycles
- None detected.

## Communities (64 total, 35 thin omitted)

### Community 0 - "api.php, gradesheet-names.php, gradesheet-parser.php"
Cohesion: 0.08
Nodes (42): associate_gradesheet_records(), associate_gradesheet_student(), {closure#5}(), {closure#8}(), dats_gradesheet_student_page_numbers(), dats_validate_add_gradesheet_criteria(), dats_gradesheet_repair_name(), dats_extract_gradesheet_records() (+34 more)

### Community 1 - "api.php, scan-intake.php, scan_intake_test.php"
Cohesion: 0.12
Nodes (31): {closure#19}(), {closure#20}(), dats_find_existing_student_for_intake(), dats_find_or_create_intake_academic_record(), dats_find_or_create_intake_major(), dats_insert_intake_student(), dats_intake_string(), dats_intake_year_levels() (+23 more)

### Community 2 - "api.php"
Cohesion: 0.06
Nodes (34): {closure#27}(), create_major(), delete_course(), delete_document(), delete_major(), delete_student(), ensure_document_deleted_column(), ensure_document_ocr_columns() (+26 more)

### Community 3 - "api.php"
Cohesion: 0.07
Nodes (10): create_category(), create_college(), create_course(), delete_college(), ensure_document_content_hash_column(), ensure_document_search_index(), get_deleted_documents(), store_college_logo() (+2 more)

### Community 4 - "api.php, student-import.php"
Cohesion: 0.12
Nodes (19): dats_student_import_default_year(), dats_student_import_upload_path(), import_students(), preview_student_import(), {closure#2}(), dats_student_import_academic_year(), dats_student_import_birthdate(), dats_student_import_civil_status() (+11 more)

### Community 5 - "auth.php, database.php"
Cohesion: 0.17
Nodes (10): dats_can_access_admin_page(), dats_current_user(), dats_ensure_user_role_column(), dats_login(), dats_require_login(), dats_require_page_access(), dats_require_role(), dats_db() (+2 more)

### Community 6 - "gradesheet-names.php, gradesheet_names_test.php"
Cohesion: 0.27
Nodes (14): {closure#2}(), dats_gn_distance(), dats_gn_key(), dats_gn_key_parts(), dats_gn_reasons(), dats_gn_same_person(), dats_gn_set_reasons(), dats_gn_variant_score() (+6 more)

### Community 7 - "sw.js"
Cohesion: 0.35
Nodes (16): broadcast(), broadcastJobUpdate(), deleteJob(), dispatchOcr(), getAllJobs(), getBasePath(), getJob(), handleClientReady() (+8 more)

### Community 8 - "api.php, scan-intake.php"
Cohesion: 0.14
Nodes (16): add_gradesheet_student(), {closure#24}(), create_document(), create_student(), dats_gradesheet_clip(), dats_gradesheet_student_matcher(), dats_record_gradesheet_batch(), handle_document_ocr() (+8 more)

### Community 9 - "dats.sql"
Cohesion: 0.32
Nodes (15): `activity_logs`, `colleges`, `course_majors`, `courses`, `document_categories`, `document_reviews`, `documents`, `enrollment_years` (+7 more)

### Community 10 - "student-actions.test.cjs"
Cohesion: 0.13
Nodes (12): api, assert, dashboardPage, dataSync, filesPage, fs, path, script (+4 more)

### Community 11 - "ai-service.php, api.php"
Cohesion: 0.29
Nodes (12): dats_resolve_gradesheet_ocr(), dats_gemini_api_key(), dats_gemini_call_generate(), dats_gemini_fallback_model(), dats_gemini_is_available(), dats_gemini_model(), dats_gemini_rate_limit_check(), dats_gemini_rate_limit_per_day() (+4 more)

### Community 12 - "api.php, database_backup_test.php"
Cohesion: 0.24
Nodes (7): detect_server_ghostscript_binary(), detect_server_ocr_binary(), run_server_ocr_if_available(), run_server_ocr_on_pdf(), run_shell_command_with_timeout(), run_tesseract_on_image(), DatabaseBackupTestPdo

### Community 13 - "README.md, ZPPSU.png, dats.sql"
Cohesion: 0.20
Nodes (7): admin/styles.css (Admin Console Styles), ZPPSU Registrar Digital Archive Domain, documents (Academic Records Table), img/ZPPSU.png (ZPPSU Institutional Seal), styles.css (Landing Page Styles), index.php (Public Landing & Login Modal), vw_archive_analytics (SQL Analytics View)

### Community 14 - "api.php, archive-workflow.php"
Cohesion: 0.24
Nodes (10): {closure#26}(), dats_apply_gradesheet_name_to_batch(), dats_refresh_gradesheet_batch_status(), delete_gradesheet_entry(), migrate_pending_documents_to_archive(), normalize_academic_year(), save_gradesheet_batch(), update_gradesheet_entry() (+2 more)

### Community 15 - "api.php"
Cohesion: 0.29
Nodes (10): dats_find_gradesheet_batch(), delete_gradesheet_batch(), handle_document_download(), handle_document_download_batch(), handle_document_view(), handle_gradesheet_batch_file(), purge_document(), rescan_gradesheet_batch() (+2 more)

### Community 16 - "api.php"
Cohesion: 0.36
Nodes (9): dats_best_gradesheet_name(), dats_format_student_name(), dats_fuzzy_gradesheet_name_matches(), dats_gradesheet_name_key(), dats_gradesheet_people(), dats_gradesheet_roster(), dats_unlinked_gradesheet_names(), get_student_gradesheet() (+1 more)

### Community 17 - "dashboard-analytics.php"
Cohesion: 0.31
Nodes (4): {closure#1}(), dats_get_dashboard_activity(), dats_get_dashboard_analytics(), dats_metric_rate()

### Community 18 - "database-backup.php"
Cohesion: 0.36
Nodes (4): dats_database_backup_file(), dats_database_quote_identifier(), dats_database_split_sql(), dats_restore_database_backup()

### Community 19 - "document-duplicate.php"
Cohesion: 0.32
Nodes (4): dats_document_content_fingerprint(), dats_find_duplicate_document(), dats_normalize_document_content(), DatsDuplicateDocumentException

### Community 20 - "direct-archive-flow.test.cjs"
Cohesion: 0.25
Nodes (5): assert, fs, path, root, test

### Community 21 - "dashboard-consolidation.test.cjs"
Cohesion: 0.25
Nodes (5): assert, fs, path, root, test

### Community 22 - "gradesheet-flow.js, gradesheet-view.test.cjs"
Cohesion: 0.29
Nodes (4): assert, fs, path, test

### Community 24 - "scan-flow.js, scan-flow.test.cjs"
Cohesion: 0.29
Nodes (4): assert, colleges, students, test

### Community 25 - "document-category-cards.test.js"
Cohesion: 0.29
Nodes (5): assert, fs, path, projectRoot, test

### Community 26 - "student-import.test.cjs"
Cohesion: 0.29
Nodes (5): assert, fs, path, root, test

### Community 28 - "api.php"
Cohesion: 0.50
Nodes (5): create_enrollment_year(), delete_enrollment_year(), ensure_enrollment_years_table(), get_enrollment_years(), sync_enrollment_years()

### Community 29 - "api.php"
Cohesion: 0.50
Nodes (4): dats_get_gradesheet_entry_source(), get_gradesheet_entry_source(), handle_gradesheet_entry_file(), index_gradesheet_entry_pages()

### Community 31 - "index.php, login.php, script.js"
Cohesion: 0.67
Nodes (3): admin/index.php (Authenticated Dashboard), script.js (Landing Modal, Nav, Live Clock), login.php (JSON Session Auth Endpoint)

## Knowledge Gaps
- **6 isolated node(s):** `script.js (Landing Modal, Nav, Live Clock)`, `img/ZPPSU.png (ZPPSU Institutional Seal)`, `admin/index.php (Authenticated Dashboard)`, `admin/styles.css (Admin Console Styles)`, `vw_archive_analytics (SQL Analytics View)` (+1 more)
  These have ≤1 connection - possible missing edges or undocumented components. (Counts symbols only; 156 node(s) total have ≤1 connection when file, concept and rationale nodes are included.)
- **35 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `dats_record_gradesheet_batch()` connect `api.php, scan-intake.php` to `api.php, gradesheet-names.php, gradesheet-parser.php`, `api.php`, `api.php`, `gradesheet-names.php, gradesheet_names_test.php`, `api.php`, `api.php`?**
  _High betweenness centrality (0.045) - this node is a cross-community bridge._
- **Are the 9 inferred relationships involving `dats_validate_new_student_payload()` (e.g. with `{closure#19}()` and `{closure#20}()`) actually correct?**
  _`dats_validate_new_student_payload()` has 9 INFERRED edges - model-reasoned connections that need verification._
- **What connects `script.js (Landing Modal, Nav, Live Clock)`, `img/ZPPSU.png (ZPPSU Institutional Seal)`, `admin/index.php (Authenticated Dashboard)` to the rest of the system?**
  _6 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `api.php, gradesheet-names.php, gradesheet-parser.php` be split into smaller, more focused modules?**
  _Cohesion score 0.0784313725490196 - nodes in this community are weakly interconnected._
- **Why does `dats_resolve_gradesheet_ocr()` connect `ai-service.php, api.php` to `api.php`, `auth.php, database.php`, `api.php, scan-intake.php`, `api.php, database_backup_test.php`, `api.php`?**
  _High betweenness centrality (0.044) - this node is a cross-community bridge._
- **Are the 4 inferred relationships involving `associate_gradesheet_records()` (e.g. with `dats_gn_reasons()` and `dats_extract_gradesheet_records()`) actually correct?**
  _`associate_gradesheet_records()` has 4 INFERRED edges - model-reasoned connections that need verification._
- **Should `api.php, scan-intake.php, scan_intake_test.php` be split into smaller, more focused modules?**
  _Cohesion score 0.12222222222222222 - nodes in this community are weakly interconnected._