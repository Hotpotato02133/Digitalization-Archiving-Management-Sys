const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '../..');
const read = (relativePath) => fs.readFileSync(path.join(root, relativePath), 'utf8');

test('pending reviews page and sidebar links are removed', () => {
  assert.equal(fs.existsSync(path.join(root, 'admin/pending.php')), false);
  for (const filename of fs.readdirSync(path.join(root, 'admin')).filter((name) => name.endsWith('.php'))) {
    assert.doesNotMatch(read(`admin/${filename}`), /pending\.php|Pending Reviews/i, filename);
  }
});

test('document intake is forced into the archive with an approved audit record', () => {
  const api = read('api.php');
  assert.match(api, /VALUES \([^\n]+\\?'Archived\\?'/);
  assert.match(api, /Automatically archived on upload/);
  assert.match(api, /migrate_pending_documents_to_archive/);
  assert.doesNotMatch(api, /'reviews'\s*=>\s*update_review/);
});

test('scan completion messaging describes direct archiving', () => {
  const script = read('admin/script.js');
  assert.match(script, /Document added directly to the archive/);
  assert.match(script, /This document already exists for this student/);
  assert.match(script, /student-record-view\.php\?student_id/);
  assert.doesNotMatch(script, /queued for review|resource=reviews/);
});
