(() => {
  'use strict';
  const editor = document.getElementById('species-editor');
  if (!editor) return;
  const form = document.getElementById('species-form');
  const confirm = document.getElementById('species-confirm');
  const catalog = JSON.parse(document.getElementById('species-edit-data').textContent);
  const fileInput = document.getElementById('species-image');
  const preview = document.getElementById('species-image-preview');
  const error = document.getElementById('species-form-error');
  let pending = null, busy = false, objectUrl = null;
  function syncPageScrollLock() {
    const modalOpen = editor.open || confirm.open;
    document.documentElement.classList.toggle('species-modal-open', modalOpen);
    document.body.classList.toggle('species-modal-open', modalOpen);
  }
  function closeMenus() {
    document.querySelectorAll('.species-menu').forEach(menu => { menu.hidden = true; });
    document.querySelectorAll('.species-menu-trigger').forEach(button => button.setAttribute('aria-expanded', 'false'));
  }
  function showPreview(url) {
    if (objectUrl) { URL.revokeObjectURL(objectUrl); objectUrl = null; }
    preview.hidden = !url;
    if (url) preview.src = url; else preview.removeAttribute('src');
    document.getElementById('species-upload-icon').hidden = Boolean(url);
  }
  function openEditor(species = null) {
    form.reset(); error.textContent = '';
    form.elements.action.value = species ? 'edit' : 'add';
    form.elements.id.value = species?.id || '';
    for (const key of ['name', 'scientific_name', 'category', 'description', 'importance', 'fun_fact']) form.elements[key].value = species?.[key] || '';
    document.getElementById('species-editor-title').textContent = species ? 'Edit Tree Species' : 'Add Tree Species';
    document.getElementById('species-save').textContent = species ? 'Save changes' : 'Add';
    showPreview(species?.image_url || '');
    editor.showModal();
    syncPageScrollLock();
  }
  document.getElementById('species-add')?.addEventListener('click', () => openEditor());
  document.addEventListener('click', event => {
    const trigger = event.target.closest('.species-menu-trigger');
    if (trigger) {
      const open = trigger.getAttribute('aria-expanded') !== 'true'; closeMenus();
      trigger.setAttribute('aria-expanded', String(open)); trigger.nextElementSibling.hidden = !open; return;
    }
    const action = event.target.closest('[data-species-action]');
    closeMenus();
    if (!action) return;
    const species = catalog.find(item => String(item.id) === action.dataset.id);
    if (!species) return;
    if (action.dataset.speciesAction === 'edit') { openEditor(species); return; }
    pending = { id: species.id, action: action.dataset.speciesAction };
    const restoring = pending.action === 'restore';
    document.getElementById('species-confirm-title').textContent = `${restoring ? 'Unarchive' : 'Archive'} ${species.name}?`;
    document.getElementById('species-confirm-copy').textContent = restoring ? 'This species will be visible in the catalog and AR selection again.' : 'This species will be hidden from the catalog and AR selection. Its details will be preserved, and you can restore it later.';
    document.getElementById('species-confirm-submit').textContent = restoring ? 'Unarchive' : 'Archive';
    document.getElementById('species-confirm-error').textContent = '';
    confirm.showModal();
    syncPageScrollLock();
  });
  document.addEventListener('keydown', event => { if (event.key === 'Escape') closeMenus(); });
  document.querySelectorAll('[data-close-species]').forEach(button => button.addEventListener('click', () => { if (!busy) button.closest('dialog').close(); }));
  [editor, confirm].forEach(dialog => dialog.addEventListener('cancel', event => { if (busy) event.preventDefault(); }));
  [editor, confirm].forEach(dialog => dialog.addEventListener('close', syncPageScrollLock));
  function selectFile(file) {
    error.textContent = '';
    if (!file) return;
    if (!['image/jpeg', 'image/png'].includes(file.type) || file.size > 10 * 1024 * 1024) {
      fileInput.value = ''; error.textContent = 'Choose a PNG or JPG image up to 10 MB.'; return;
    }
    showPreview(''); objectUrl = URL.createObjectURL(file); preview.src = objectUrl; preview.hidden = false;
    document.getElementById('species-upload-icon').hidden = true;
  }
  fileInput.addEventListener('change', () => selectFile(fileInput.files[0]));
  const upload = document.getElementById('species-upload');
  upload.addEventListener('dragover', event => { event.preventDefault(); upload.classList.add('is-dragging'); });
  upload.addEventListener('dragleave', () => upload.classList.remove('is-dragging'));
  upload.addEventListener('drop', event => {
    event.preventDefault(); upload.classList.remove('is-dragging');
    if (event.dataTransfer.files.length !== 1) { error.textContent = 'Please select one image.'; return; }
    fileInput.files = event.dataTransfer.files; selectFile(fileInput.files[0]);
  });
  async function save(data, dialog, messageElement) {
    if (busy) return;
    busy = true; messageElement.textContent = '';
    dialog.querySelectorAll('button').forEach(button => { button.disabled = true; });
    try {
      const response = await fetch('actions/manage_species.php', { method: 'POST', body: data });
      const result = await response.json();
      if (!response.ok || !result.success) throw new Error(result.error || 'Unable to save the species.');
      sessionStorage.setItem('species-notice', result.message);
      location.reload();
    } catch (e) { messageElement.textContent = e.message || 'Connection failed. Please try again.'; }
    finally { busy = false; dialog.querySelectorAll('button').forEach(button => { button.disabled = false; }); }
  }
  form.addEventListener('submit', event => { event.preventDefault(); if (form.reportValidity()) save(new FormData(form), editor, error); });
  document.getElementById('species-confirm-form').addEventListener('submit', event => {
    event.preventDefault(); if (!pending) return;
    const data = new FormData(); data.set('id', pending.id); data.set('action', pending.action);
    data.set('csrf_token', form.elements.csrf_token.value);
    save(data, confirm, document.getElementById('species-confirm-error'));
  });
  const notice = sessionStorage.getItem('species-notice');
  if (notice) { document.getElementById('species-page-status').textContent = notice; sessionStorage.removeItem('species-notice'); }
})();
