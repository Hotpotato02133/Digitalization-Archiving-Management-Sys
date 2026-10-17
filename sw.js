// Background job runner for File Scanning and Gradesheets uploads.
//
// Why this exists: DATS is a classic multi-page app (every nav link is a full page load), so
// a plain in-page fetch() for "Add to archive" or a gradesheet save gets silently aborted the
// moment the user clicks away to another page. This worker keeps a durable job queue
// (IndexedDB, since workers have no localStorage) and performs the actual save fetch() itself,
// so it survives navigation. OCR itself still needs a real page (canvas, Tesseract workers),
// so OCR-needing jobs are relayed to whichever DATS page is currently open; see data-sync.js
// for the client-side half of this protocol.
const DB_NAME = 'dats-jobs';
const DB_VERSION = 1;
const STORE = 'jobs';
const STALE_MS = 20000;
const RETENTION_MS = 6 * 60 * 60 * 1000;

const openDb = () => new Promise((resolve, reject) => {
  const request = indexedDB.open(DB_NAME, DB_VERSION);
  request.onupgradeneeded = () => {
    const db = request.result;
    if (!db.objectStoreNames.contains(STORE)) db.createObjectStore(STORE, { keyPath: 'id' });
  };
  request.onsuccess = () => resolve(request.result);
  request.onerror = () => reject(request.error);
});

const putJob = (job) => openDb().then((db) => new Promise((resolve, reject) => {
  const tx = db.transaction(STORE, 'readwrite');
  tx.objectStore(STORE).put(job);
  tx.oncomplete = () => resolve(job);
  tx.onerror = () => reject(tx.error);
}));

const getJob = (id) => openDb().then((db) => new Promise((resolve, reject) => {
  const tx = db.transaction(STORE, 'readonly');
  const request = tx.objectStore(STORE).get(id);
  request.onsuccess = () => resolve(request.result || null);
  request.onerror = () => reject(request.error);
}));

const getAllJobs = () => openDb().then((db) => new Promise((resolve, reject) => {
  const tx = db.transaction(STORE, 'readonly');
  const request = tx.objectStore(STORE).getAll();
  request.onsuccess = () => resolve(request.result || []);
  request.onerror = () => reject(request.error);
}));

const deleteJob = (id) => openDb().then((db) => new Promise((resolve, reject) => {
  const tx = db.transaction(STORE, 'readwrite');
  tx.objectStore(STORE).delete(id);
  tx.oncomplete = () => resolve();
  tx.onerror = () => reject(tx.error);
}));

// Never ship the stored file Blob (or other clients' claim bookkeeping) down to every page.
const toClientJob = ({ file, claimedBy, ...rest }) => ({ ...rest, hasFile: Boolean(file) });

const broadcast = async (message) => {
  const clientList = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
  clientList.forEach((client) => client.postMessage(message));
};

const broadcastJobUpdate = (job) => broadcast({ type: 'JOB_UPDATE', job: toClientJob(job) });

const dispatchOcr = async (job, preferredClientId) => {
  const clientList = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
  const chosen = clientList.find((client) => client.id === preferredClientId) || clientList[0];
  if (!chosen) {
    // Nothing open to run OCR right now; CLIENT_READY on the next page load will retry this.
    job.status = 'queued';
    job.updatedAt = Date.now();
    await putJob(job);
    await broadcastJobUpdate(job);
    return;
  }
  job.status = 'ocr-running';
  job.claimedBy = chosen.id;
  job.heartbeatAt = Date.now();
  job.updatedAt = Date.now();
  await putJob(job);
  await broadcastJobUpdate(job);
  chosen.postMessage({ type: 'RUN_OCR', jobId: job.id, engine: 'gradesheet', file: job.file, meta: job.payload });
};

const runSaveJob = async (job) => {
  job.status = 'saving';
  job.updatedAt = Date.now();
  await putJob(job);
  await broadcastJobUpdate(job);
  try {
    const getBasePath = () => {
      try {
        const scopePath = new URL(self.registration?.scope || '/DATS/').pathname;
        return scopePath.replace(/\/$/, '');
      } catch {
        return '/DATS';
      }
    };
    const basePath = getBasePath();
    let response;
    if (job.kind === 'scan-upload') {
      const formData = new FormData();
      Object.entries(job.payload || {}).forEach(([key, value]) => formData.append(key, value));
      if (job.file) formData.append('file', job.file, job.payload?.original_filename || 'document');
      response = await fetch(`${basePath}/api.php?resource=documents`, { method: 'POST', credentials: 'same-origin', body: formData });
    } else if (job.kind === 'gradesheet-upload') {
      const formData = new FormData();
      formData.append('file', job.file, job.payload?.fileName || 'gradesheet');
      formData.append('ocr_text', job.result?.ocrText || '');
      formData.append('ocr_status', job.result?.ocrStatus || '');
      formData.append('ocr_error', job.result?.ocrError || '');
      response = await fetch(`${basePath}/api.php?resource=gradesheet-batches`, { method: 'POST', credentials: 'same-origin', body: formData });
    } else if (job.kind === 'gradesheet-rescan') {
      response = await fetch(`${basePath}/api.php?resource=gradesheet-batches`, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action: 'rescan',
          batch_id: job.payload?.batchId,
          ocr_text: job.result?.ocrText || '',
          ocr_status: job.result?.ocrStatus || '',
          ocr_error: job.result?.ocrError || '',
        }),
      });
    } else {
      throw new Error(`Unknown job kind: ${job.kind}`);
    }
    const result = await response.json().catch(() => ({}));
    if (!response.ok) {
      if (response.status === 409 && result.duplicate) {
        job.status = 'duplicate';
        job.duplicate = result.duplicate;
        job.error = result.error || 'This document already exists for this student.';
      } else {
        job.status = 'failed';
        job.error = result.error || 'The server could not complete the request.';
      }
    } else {
      job.status = 'done';
      job.resultSummary = (result.data && result.data.summary) ? { ...result.data.summary, ...result.data } : result.data;
      job.file = null; // free the stored blob now that the upload has succeeded
    }
  } catch (error) {
    job.status = 'failed';
    job.error = 'Unable to reach the server. Check that Apache and MariaDB are running.';
  }
  job.updatedAt = Date.now();
  await putJob(job);
  await broadcastJobUpdate(job);
};

const handleEnqueue = async (job, sourceClient) => {
  await putJob(job);
  await broadcastJobUpdate(job);
  if (job.kind === 'scan-upload' || job.kind === 'gradesheet-upload' || job.kind === 'gradesheet-rescan') {
    await runSaveJob(job);
  } else {
    await dispatchOcr(job, sourceClient?.id);
  }
};

const handleOcrResult = async (data) => {
  const job = await getJob(data.jobId);
  if (!job) return;
  if (!data.ok) {
    job.status = 'failed';
    job.error = data.ocrError || 'OCR could not read this file.';
    job.updatedAt = Date.now();
    await putJob(job);
    await broadcastJobUpdate(job);
    return;
  }
  job.result = { ocrText: data.ocrText || '', ocrStatus: data.ocrStatus || 'Failed', ocrError: data.ocrError || '', truncated: Boolean(data.truncated) };
  job.updatedAt = Date.now();
  await putJob(job);
  await runSaveJob(job);
};

const handleOcrProgress = async (data) => {
  const job = await getJob(data.jobId);
  if (!job || job.status !== 'ocr-running') return;
  job.heartbeatAt = Date.now();
  job.progress = { value: data.value, label: data.label, detail: data.detail };
  job.updatedAt = Date.now();
  await putJob(job);
  await broadcastJobUpdate(job);
};

// Fires on every page load. Doubles as: (a) the rehydrate signal for a freshly-opened page's
// status banner, (b) the recovery path for a stuck relay (the client that was running OCR
// navigated away before reporting back — this is exactly the bug this file exists to fix, and
// "a page just loaded" is also exactly the right moment to notice and retry it), and (c) the
// wake-up for a job that was queued while zero DATS pages were open.
const handleClientReady = async (client) => {
  const jobs = await getAllJobs();
  const now = Date.now();
  await Promise.all(jobs
    .filter((job) => ['done', 'failed', 'duplicate'].includes(job.status) && now - (job.updatedAt || job.createdAt || 0) > RETENTION_MS)
    .map((job) => deleteJob(job.id)));
  const remaining = await getAllJobs();
  if (client) client.postMessage({ type: 'JOBS_SNAPSHOT', jobs: remaining.map(toClientJob) });
  if (!client) return;
  await Promise.all(remaining
    .filter((job) => job.kind !== 'scan-upload' && job.kind !== 'gradesheet-upload' && job.kind !== 'gradesheet-rescan' && !job.result
      && (job.status === 'queued' || (job.status === 'ocr-running' && (!job.heartbeatAt || now - job.heartbeatAt > STALE_MS))))
    .map((job) => dispatchOcr(job, client.id)));
};

self.addEventListener('install', () => { self.skipWaiting(); });
self.addEventListener('activate', (event) => {
  event.waitUntil(Promise.all([
    self.clients.claim(),
    navigator.storage?.persist ? navigator.storage.persist().catch(() => {}) : Promise.resolve(),
  ]));
});

self.addEventListener('message', (event) => {
  const data = event.data || {};
  switch (data.type) {
    case 'CLIENT_READY':
      event.waitUntil(handleClientReady(event.source));
      break;
    case 'ENQUEUE_JOB':
      event.waitUntil(handleEnqueue({ ...data.job, status: 'queued', createdAt: Date.now(), updatedAt: Date.now() }, event.source));
      break;
    case 'OCR_RESULT':
      event.waitUntil(handleOcrResult(data));
      break;
    case 'OCR_PROGRESS':
      event.waitUntil(handleOcrProgress(data));
      break;
    case 'ACK_JOB':
      event.waitUntil(deleteJob(data.jobId));
      break;
    default:
      break;
  }
});
