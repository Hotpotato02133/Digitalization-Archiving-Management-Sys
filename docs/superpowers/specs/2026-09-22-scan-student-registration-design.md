# Scan Student Selection and Registration Design

## Purpose

Improve File Scanning so an uploader explicitly identifies whether a document belongs to an existing student or a student who must be registered. Existing records must be selected unambiguously, while a new student and the uploaded document must be created as one consistent operation.

The downstream workflow does not change: every submitted document enters Pending Reviews, approval moves it into the academic-year archive, and the Files page derives its year-folder counts and student documents from approved records.

## Success Criteria

- Record Details starts in Existing student mode and also offers New student registration.
- Existing students are selected through a searchable, keyboard-accessible suggestion list rather than free-text name matching.
- Selecting an existing student fills a read-only academic year from the stored student record.
- New student registration collects required identity fields and useful optional profile fields without leaving the scanning workflow.
- A new student and document are created atomically from the database's perspective; a failed document operation does not leave a new student row behind.
- A document upload triggers OCR category detection and selects the best matching active document category when a match exists.
- Document Type remains a normal dropdown before a file is selected and when OCR cannot identify an active category.
- Existing review, approval, archive folder, file count, and View Files behaviors continue to work without a new status or alternate storage path.

## User Experience

### Student mode selector

Record Details adds a radio group with these choices:

- **Existing student** — selected by default.
- **New student registration** — reveals an inline registration form.

Changing modes clears mode-specific identity values so a hidden student ID or partially entered registration cannot be submitted accidentally. Uploaded files, OCR output, and the detected document category remain intact when modes change.

### Existing student mode

The student field is an accessible combobox backed by the existing `GET api.php?resource=students` response. Suggestions match student number, first name, middle initial, last name, college, course, and academic year. Each result displays enough metadata to distinguish students with similar names:

`<student number> · <full name>`

`Academic year <year> · <college/course when available>`

Keyboard behavior supports Arrow Up, Arrow Down, Enter, Escape, and Tab. A selection stores the numeric student ID; submitting arbitrary typed text is invalid. The Academic Year input is populated from the selected student and is read-only. Students without a valid stored academic year cannot be used for document submission until their profile is corrected or the user switches to New student registration.

### New student registration mode

The inline form collects:

Required:

- Student number
- First name
- Last name
- Academic year, normalized to a single year from 2000 through 2100

Optional:

- Middle initial
- Nationality
- Gender
- Birthdate
- Address
- Civil status
- Contact number
- College
- Course
- Major
- Year level

College, course, and major follow the same dependency rules as the Students Information form: selecting a college limits courses; selecting a course limits majors. Gender, civil status, and year level use the database's existing enum values. Nationality is plain text, trimmed, and limited to 80 characters.

The form uses explicit first and last name fields rather than trying to split a full name. Duplicate student numbers are rejected with a specific message and retain the user's entered form values.

### Document type behavior

Before upload, Document Type is an enabled dropdown of active categories. On file selection, the current OCR process runs and attempts to match extracted text to an active category. A successful match selects that category automatically and labels the field as detected from the uploaded document. The field remains editable so the uploader can correct an inaccurate match.

When OCR fails, returns no readable text, or names a category that is not active, the dropdown remains enabled and no unsupported category value is submitted. Removing or replacing the selected file clears file-derived category state and permits detection from the replacement file.

## Data Model

Add `students.nationality VARCHAR(80) NULL` after `address` in `dats.sql`. Add an idempotent runtime schema guard in `api.php`, consistent with the project's current self-healing columns, so an existing installation gains the column without requiring a destructive re-import.

The students API includes `nationality` automatically through its existing `s.*` selection. Student create and update operations accept, trim, length-check, store, and return the field. The Students Information add/edit form also exposes Nationality so records created during scanning remain maintainable elsewhere in the application.

No new document, review, or enrollment-year tables are needed.

## Backend Contract

### Existing student document submission

`POST api.php?resource=documents` accepts:

- `student_mode=existing`
- `student_id=<positive integer>`
- Existing document, OCR, and upload fields

The backend loads the active, non-deleted student by ID and derives the document academic year from that student's stored academic year. It rejects missing, deleted, or invalid students and ignores a client-supplied academic year for this mode. This prevents stale UI data or request tampering from filing a document under the wrong year.

### New student document submission

The same endpoint accepts:

- `student_mode=new`
- `student_number`
- `first_name`
- `middle_initial`
- `last_name`
- `nationality`
- `gender`
- `birthdate`
- `address`
- `civil_status`
- `contact_number`
- `college_id`
- `course_id`
- `major_id`
- `academic_year`
- `year_level`
- Existing document, OCR, and upload fields

The server validates the new-student payload using the same rules as the Students Information endpoint, including college/course/major consistency and unique student number. Student insertion, document insertion, enrollment-year upsert, and pending review insertion occur in one database transaction.

File movement cannot participate in the database transaction. The upload is validated and moved to `uploads/` before database writes. If validation, insertion, or commit fails after the move, the new upload is deleted as compensation. Existing stored files are never deleted by this compensation path.

The endpoint continues to return the created `vw_recent_uploads` row, so existing success handling and live upload refresh behavior remain compatible.

### Compatibility

The backend may continue accepting the legacy student lookup fields only for callers that omit `student_mode`, but the revised scanning page always sends an explicit mode and student ID or new-student payload. This limits regression risk for any older page or integration while eliminating ambiguous name lookup from the new flow.

## Frontend Structure

- `admin/scan.php` owns the radio group, existing-student combobox markup, new-student form, status/help text, and scan-page styles.
- `data-sync.js` loads students, categories, colleges, courses, and majors needed by the scan form, then dispatches a scan-data-ready event containing normalized data. It no longer populates a plain student-name datalist.
- `admin/script.js` owns mode switching, combobox filtering and keyboard behavior, dependent registration selects, OCR-derived category state, validation, FormData construction, reset behavior, and submission feedback.
- `admin/students.php` and its existing JavaScript form include Nationality for later maintenance of the full student profile.
- Shared pure scan-flow functions are placed in a small standalone JavaScript module that works in the browser and can be executed by the repository's lightweight test harness without introducing npm or a build system.

The visual treatment follows the existing maroon/gold palette, label sizes, inputs, focus states, and responsive breakpoints. On narrow screens, registration fields collapse to one column.

## Validation and Error Handling

- Existing mode requires a selected numeric student ID, not merely non-empty search text.
- Existing mode requires a valid academic year stored on that student.
- New mode requires a unique student number, first name, last name, and valid academic year.
- Names, nationality, address, and contact values are trimmed; field lengths are checked server-side against schema limits.
- Birthdate must use an accepted date value when supplied.
- College, course, and major IDs must form a valid active hierarchy when supplied.
- Category code must resolve to an active category.
- File MIME and size continue using the existing server-side validation.
- Submission errors preserve entered fields and show the server's safe validation message.
- The archive button is disabled only during the active request and is restored after success or failure.

## Testing

Because the project currently has no package manager or test suite, add focused, dependency-free tests rather than introducing a framework solely for this change.

PHP tests exercise extracted validation and transaction helpers with controlled inputs and a test database boundary where required:

- Existing mode resolves the selected student and uses its stored academic year.
- Existing mode rejects typed text without a selected ID and rejects a student with no valid year.
- New mode validates required fields and rejects duplicate student numbers.
- New mode rejects inconsistent college/course/major selections.
- New-student and document records roll back together on a document insertion failure.
- A moved upload is removed when the database operation fails.

JavaScript tests exercise pure scan-flow behavior:

- Search matches student number, name, school metadata, and academic year.
- Selecting a suggestion stores an ID and derives the year.
- Switching modes clears only mode-specific data.
- A recognized OCR category selects an active option.
- Missing or unknown OCR categories preserve manual dropdown behavior.
- FormData construction includes exactly the fields required by the selected student mode.

Verification also includes PHP syntax checks and a browser walkthrough of both modes through Pending Review and approval into the correct Files year folder.

## Out of Scope

- Changing the Pending Reviews decision model.
- Automatically approving uploads.
- Replacing keyword-based OCR classification with an external AI service.
- Creating new permission levels.
- Redesigning the Students Information page beyond adding and maintaining Nationality.
