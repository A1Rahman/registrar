'use strict';

// All data is inserted with textContent / createTextNode, never innerHTML, so it cannot inject HTML (XSS).

const GRADES = ['A', 'A-', 'B+', 'B', 'B-', 'C+', 'C', 'C-', 'D', 'F'];

// Describes each table: the form fields and the columns shown in Manage.
const TABLES = {
  students: {
    label: 'Students', singular: 'Student',
    fields: [
      { name: 'first', label: 'First name', type: 'text' },
      { name: 'last', label: 'Last name', type: 'text' },
      { name: 'email', label: 'Email', type: 'email' },
      { name: 'birthdate', label: 'Birthdate', type: 'date' },
      { name: 'date_of_enrollment', label: 'Date of enrollment', type: 'date' },
    ],
    columns: [
      { key: 'first', label: 'First name' }, { key: 'last', label: 'Last name' },
      { key: 'email', label: 'Email' }, { key: 'birthdate', label: 'Birthdate' },
      { key: 'date_of_enrollment', label: 'Date of enrollment' },
    ],
  },
  courses: {
    label: 'Courses', singular: 'Course',
    fields: [
      { name: 'code', label: 'Code', type: 'text' },
      { name: 'title', label: 'Title', type: 'text' },
      { name: 'credits', label: 'Credits', type: 'number' },
    ],
    columns: [{ key: 'code', label: 'Code' }, { key: 'title', label: 'Title' }, { key: 'credits', label: 'Credits' }],
  },
  prerequisites: {
    label: 'Prerequisites', singular: 'Prerequisite',
    fields: [
      { name: 'course_id', label: 'Course', type: 'select', source: 'courses' },
      { name: 'prereq_id', label: 'Requires', type: 'select', source: 'courses' },
    ],
    columns: [{ key: 'course_code', label: 'Course' }, { key: 'prereq_code', label: 'Requires' }],
  },
  enrollments: {
    label: 'Enrollments', singular: 'Enrollment',
    fields: [
      { name: 'student_id', label: 'Student', type: 'select', source: 'students' },
      { name: 'course_id', label: 'Course', type: 'select', source: 'courses' },
      { name: 'grade', label: 'Grade', type: 'select', source: 'grades' },
    ],
    columns: [
      { key: 'student_name', label: 'Student' }, { key: 'course_code', label: 'Course' }, { key: 'grade', label: 'Grade' },
    ],
  },
};

// Text of each drop-down option for the tables that other records point to.
const OPTION_LABELS = {
  students: (r) => `${r.last}, ${r.first}`,
  courses: (r) => `${r.code} — ${r.title}`,
};

const state = { mode: 'create', table: 'students', editing: null };
const messageEl = document.getElementById('message');
const contentEl = document.getElementById('content');
const subNavEl = document.getElementById('sub-nav');

// ---------- helpers ----------

// Small helper to build an element safely.
function el(tag, props = {}, ...children) {
  const node = document.createElement(tag);
  Object.assign(node, props);
  children.forEach((c) => node.append(c));
  return node;
}

function setMessage(text, type = '') {
  messageEl.textContent = text;
  messageEl.className = type;
}

// Calls the PHP back end. Throws an Error with the server's message when the response is not OK.
async function api(method, resource, { id, body } = {}) {
  const params = new URLSearchParams({ resource });
  if (id) params.set('id', id);
  const res = await fetch('api.php?' + params, {
    method,
    headers: { 'Content-Type': 'application/json' },
    body: body ? JSON.stringify(body) : undefined,
  });
  let data = {};
  try { data = await res.json(); } catch (e) { /* not JSON */ }
  if (!res.ok) throw new Error(data.error || `Request failed (${res.status}).`);
  return data;
}

async function getOptions(source) {
  if (source === 'grades') return GRADES.map((g) => ({ value: g, label: g }));
  const rows = (await api('GET', source)).data;
  return rows.map((r) => ({ value: r.id, label: OPTION_LABELS[source](r) }));
}

// ---------- navigation ----------

function renderNav() {
  document.querySelectorAll('#main-nav button').forEach((b) =>
    b.classList.toggle('active', b.dataset.mode === state.mode));

  subNavEl.replaceChildren();
  if (state.mode === 'transcript') return;
  Object.entries(TABLES).forEach(([key, cfg]) => {
    const b = el('button', { type: 'button', textContent: cfg.label });
    b.classList.toggle('active', key === state.table);
    b.addEventListener('click', () => { state.table = key; state.editing = null; setMessage(''); render(); });
    subNavEl.append(b);
  });
}

document.querySelectorAll('#main-nav button').forEach((b) =>
  b.addEventListener('click', () => { state.mode = b.dataset.mode; state.editing = null; setMessage(''); render(); }));

async function render() {
  renderNav();
  contentEl.replaceChildren();
  setMessage('Loading...');
  try {
    if (state.mode === 'transcript') await renderTranscript();
    else await renderTable(state.table, state.mode === 'manage');
    if (messageEl.textContent === 'Loading...') setMessage('');
  } catch (e) {
    setMessage(e.message, 'error');
  }
}

// ---------- create / edit form ----------

async function buildForm(key, record) {
  const cfg = TABLES[key];
  const form = el('form', { className: 'card', noValidate: true }); // novalidate: the server does the validation and its messages are shown
  const title = el('h2', {}, record ? `Editing record #${record.id} ` : `New ${cfg.singular}`);
  if (record) {
    const cancel = el('a', { href: '#', textContent: 'Cancel' });
    cancel.addEventListener('click', (e) => { e.preventDefault(); state.editing = null; setMessage(''); render(); });
    title.append(cancel);
  }
  form.append(title);

  for (const f of cfg.fields) {
    const label = el('label', { textContent: f.label, htmlFor: 'f-' + f.name });
    let input;
    if (f.type === 'select') {
      input = el('select');
      input.append(el('option', { value: '', textContent: 'Select...' }));
      (await getOptions(f.source)).forEach((o) => input.append(el('option', { value: o.value, textContent: o.label })));
    } else {
      input = el('input', { type: f.type });
    }
    input.id = 'f-' + f.name;
    input.name = f.name;
    if (record) input.value = record[f.name];
    form.append(label, input);
  }
  form.append(el('button', { type: 'submit', textContent: 'Save' }));
  form.addEventListener('submit', (e) => { e.preventDefault(); save(key, form, record); });
  return form;
}

async function save(key, form, record) {
  const body = Object.fromEntries(new FormData(form));
  setMessage('Saving...');
  try {
    const res = await api(record ? 'PUT' : 'POST', key, { id: record && record.id, body });
    if (record) {
      state.editing = null;
      await render();
    } else {
      form.reset();
    }
    setMessage(res.message, 'success');
  } catch (e) {
    setMessage(e.message, 'error');
  }
}

// ---------- manage table ----------

async function renderTable(key, manage) {
  const cfg = TABLES[key];
  contentEl.append(await buildForm(key, manage ? state.editing : null));
  if (!manage) return;

  const rows = (await api('GET', key)).data;
  const table = el('table');
  const head = el('tr');
  cfg.columns.forEach((c) => head.append(el('th', { textContent: c.label })));
  head.append(el('th'));
  table.append(el('thead', {}, head));

  const tbody = el('tbody');
  rows.forEach((row) => {
    const tr = el('tr');
    cfg.columns.forEach((c) => tr.append(el('td', { textContent: row[c.key] })));

    const edit = el('a', { href: '#', textContent: 'Edit' });
    edit.addEventListener('click', (e) => { e.preventDefault(); state.editing = row; setMessage(''); render(); });
    const del = el('a', { href: '#', textContent: 'Delete', className: 'delete' });
    del.addEventListener('click', (e) => { e.preventDefault(); removeRow(key, row); });
    tr.append(el('td', {}, edit, ' · ', del));
    tbody.append(tr);
  });
  table.append(tbody);
  contentEl.append(el('div', { className: 'card' }, rows.length ? table : 'No records yet.'));
}

async function removeRow(key, row) {
  if (!confirm('Delete this record?')) return;
  setMessage('Deleting...');
  try {
    const res = await api('DELETE', key, { id: row.id });
    await render();
    setMessage(res.message, 'success');
  } catch (e) {
    setMessage(e.message, 'error'); // e.g. "This course is part of a prerequisite chain..."
  }
}

// ---------- transcript ----------

async function renderTranscript() {
  const students = (await api('GET', 'students')).data;
  const select = el('select', { id: 'transcript-student' });
  select.append(el('option', { value: '', textContent: 'Select...' }));
  students.forEach((s) => select.append(el('option', { value: s.id, textContent: OPTION_LABELS.students(s) })));

  const result = el('div');
  select.addEventListener('change', () => showTranscript(select.value, result));
  contentEl.append(
    el('div', { className: 'card' }, el('label', { textContent: 'Student', htmlFor: 'transcript-student' }), select),
    result
  );
}

async function showTranscript(studentId, result) {
  result.replaceChildren();
  if (!studentId) return setMessage('');
  setMessage('Loading...');
  try {
    const t = (await api('GET', 'transcript', { id: studentId })).data;
    const table = el('table');
    table.append(el('thead', {}, el('tr', {},
      el('th', { textContent: 'Code' }), el('th', { textContent: 'Title' }),
      el('th', { textContent: 'Credits' }), el('th', { textContent: 'Grade' }))));
    const tbody = el('tbody');
    t.courses.forEach((c) => tbody.append(el('tr', {},
      el('td', { textContent: c.code }), el('td', { textContent: c.title }),
      el('td', { textContent: c.credits }), el('td', { textContent: c.grade }))));
    table.append(tbody);
    result.append(el('div', { className: 'card' },
      t.courses.length ? table : 'This student has not taken any courses.',
      el('p', {}, el('strong', { textContent: `Total credits: ${t.total_credits}` }))));
    setMessage('');
  } catch (e) {
    setMessage(e.message, 'error');
  }
}

render();
