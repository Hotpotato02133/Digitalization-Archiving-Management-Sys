const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');

const projectRoot = path.resolve(__dirname, '..');

const readProjectFile = (relativePath) =>
  fs.readFileSync(path.join(projectRoot, relativePath), 'utf8');

test('document category cards render names without codes or descriptions', () => {
  const initialCardRenderer = readProjectFile('data-sync.js');
  const addedCardRenderer = readProjectFile(path.join('admin', 'categories.js'));

  for (const renderer of [initialCardRenderer, addedCardRenderer]) {
    assert.doesNotMatch(renderer, /class="category-code"/);
    assert.doesNotMatch(renderer, /card\.querySelector\('\.category-code'\)/);
    assert.doesNotMatch(renderer, /<h4><\/h4><p><\/p>/);
    assert.doesNotMatch(renderer, /card\.querySelector\('p'\)\.textContent = category\.description/);
  }
});

test('file scanning submits the selected document category id', () => {
  const dataSync = readProjectFile('data-sync.js');
  const adminScript = readProjectFile(path.join('admin', 'script.js'));

  assert.match(dataSync, /new Option\(category\.name, String\(category\.id\)\)/);
  assert.match(adminScript, /uploadData\.append\('category_id', scanCategory\.value\)/);
});

test('the document categories page cache-busts each card asset when it changes', () => {
  const categoriesPage = readProjectFile(path.join('admin', 'categories.php'));

  assert.match(categoriesPage, /categories\.css\?v=<\?= filemtime\(__DIR__ \. '\/categories\.css'\) \?>/);
  assert.match(categoriesPage, /data-sync\.js\?v=<\?= filemtime\(__DIR__ \. '\/\.\.\/data-sync\.js'\) \?>/);
  assert.match(categoriesPage, /categories\.js\?v=<\?= filemtime\(__DIR__ \. '\/categories\.js'\) \?>/);
});

test('all-student files view includes documents from every school year', () => {
  const filesPage = readProjectFile(path.join('admin', 'files.php'));

  assert.match(filesPage, /const isAllYears = String\(year\) === 'ALL';/);
  assert.match(filesPage, /\.filter\(\(file\) => isAllYears \|\| String\(file\.academic_year \|\| ''\)\.trim\(\) === String\(year\)\)/);
  assert.match(filesPage, /studentFilesYear\.textContent = isAllYears \? 'All school years'/);
});

test('student files defaults to all enrollment years on a fresh load', () => {
  const filesPage = readProjectFile(path.join('admin', 'files.php'));

  assert.match(filesPage, /const year = canKeepYear \? previousYear : previousYear \? String\(availableYears\[0\]\?\.year \|\| 'ALL'\) : 'ALL';/);
});

test('student file deletion moves documents to a restorable recycle bin', () => {
  const api = readProjectFile('api.php');
  const filesPage = readProjectFile(path.join('admin', 'files.php'));

  assert.match(api, /'deleted-documents' => get_deleted_documents\(\$pdo\)/);
  assert.match(api, /'delete' => delete_document\(\$pdo, \$input\)/);
  assert.match(api, /'restore' => restore_document\(\$pdo, \$input\)/);
  assert.match(api, /UPDATE documents SET deleted_at = NOW\(\), updated_at = NOW\(\)/);
  assert.match(api, /UPDATE documents SET deleted_at = NULL, updated_at = NOW\(\)/);
  assert.match(filesPage, /data-delete-document=/);
  assert.match(filesPage, /This document will be moved to the Recycle Bin/);
  assert.match(filesPage, /data-restore-document-id=/);

  assert.match(filesPage, />View<\/a>[\s\S]*?>Download<\/a>[\s\S]*data-delete-document=/);
});

test('recycle bin documents can be permanently deleted after confirmation', () => {
  const api = readProjectFile('api.php');
  const filesPage = readProjectFile(path.join('admin', 'files.php'));

  assert.match(api, /'purge' => purge_document\(\$pdo, \$input\)/);
  assert.match(api, /function purge_document\(PDO \$pdo, array \$input\)/);
  assert.match(api, /WHERE id = :id AND deleted_at IS NOT NULL LIMIT 1/);
  assert.match(api, /safe_document_path\(\$filePath\)/);
  assert.match(filesPage, /data-restore-document-id=/);
  assert.match(filesPage, /data-purge-document-id=/);
  assert.match(filesPage, /JSON\.stringify\(\{ action: 'purge', document_id: document\.id \}\)/);
  assert.match(filesPage, /purgeDocumentModal/);
});
