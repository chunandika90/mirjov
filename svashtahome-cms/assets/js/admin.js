// Svashta Home CMS Admin — modal + gallery add-on interaksi (vanilla JS)
(function () {
  function openModal(id) {
    var el = document.getElementById(id);
    if (el) el.classList.add('open');
  }
  function closeModal(id) {
    var el = document.getElementById(id);
    if (el) el.classList.remove('open');
  }

  document.querySelectorAll('[data-open-modal]').forEach(function (btn) {
    btn.addEventListener('click', function () { openModal(btn.getAttribute('data-open-modal')); });
  });
  document.querySelectorAll('[data-close-modal]').forEach(function (btn) {
    btn.addEventListener('click', function () { closeModal(btn.getAttribute('data-close-modal')); });
  });
  document.querySelectorAll('.modal-scrim').forEach(function (scrim) {
    scrim.addEventListener('click', function (e) { if (e.target === scrim) scrim.classList.remove('open'); });
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') document.querySelectorAll('.modal-scrim.open').forEach(function (m) { m.classList.remove('open'); });
  });

  // Auto-open modal Edit yang relevan berdasarkan query param setelah reload
  // (mis. ?edit_slide=5 -> buka #slide-modal yang sudah di-prefill server-side)
  var params = new URLSearchParams(window.location.search);
  var autoOpenMap = {
    edit_slide: 'slide-modal',
    edit_collaborator: 'collaborator-modal',
    edit_review: 'review-modal'
  };
  Object.keys(autoOpenMap).forEach(function (param) {
    if (params.has(param)) openModal(autoOpenMap[param]);
  });

  // Image preview di dropzone: begitu file dipilih, tampilkan preview-nya
  document.querySelectorAll('input[type=file][data-preview-target]').forEach(function (input) {
    input.addEventListener('change', function () {
      var targetId = input.getAttribute('data-preview-target');
      var img = document.getElementById(targetId);
      if (img && input.files && input.files[0]) {
        img.src = URL.createObjectURL(input.files[0]);
        img.style.display = 'block';
      }
    });
  });

  // Submit form hapus (foto galeri, dst) lewat DOM murni — tidak nempel string
  // HTML ke dalam atribut onclick, jadi aman dari tanda kutip yang bentrok.
  window.__submitDeleteForm = function (action, fields) {
    var form = document.createElement('form');
    form.method = 'post';
    form.style.display = 'none';
    function addField(name, value) {
      var input = document.createElement('input');
      input.type = 'hidden';
      input.name = name;
      input.value = value;
      form.appendChild(input);
    }
    addField('csrf_token', window.CSRF_TOKEN || '');
    addField('action', action);
    Object.keys(fields || {}).forEach(function (k) { addField(k, fields[k]); });
    document.body.appendChild(form);
    form.submit();
  };

  // Tambah baris Product Highlight baru (dipanggil dari onclick inline)
  window.__addHighlightRow = function () {
    var list = document.getElementById('highlights-list');
    if (!list) return;
    var row = document.createElement('div');
    row.className = 'highlight-block';
    row.style.position = 'relative';
    row.innerHTML =
      '<button type="button" onclick="this.closest(\'.highlight-block\').remove()" style="position:absolute; top:10px; right:10px; background:none; border:none; color:var(--danger); font-size:11px; font-weight:700; cursor:pointer;">✕ Hapus</button>' +
      '<input type="text" name="highlight_label[]" placeholder="Label, mis. MEASUREMENTS" style="margin-bottom:8px; padding-right:70px;">' +
      '<textarea name="highlight_text[]" placeholder="Deskripsi singkat"></textarea>';
    list.appendChild(row);
  };

  // Tambah baris link Instagram baru (dipanggil dari onclick inline)
  window.__addInstagramRow = function () {
    var list = document.getElementById('instagram-list');
    if (!list) return;
    var row = document.createElement('div');
    row.className = 'instagram-block';
    row.style.position = 'relative';
    row.style.marginBottom = '8px';
    row.innerHTML =
      '<button type="button" onclick="this.closest(\'.instagram-block\').remove()" style="position:absolute; top:9px; right:8px; background:none; border:none; color:var(--danger); font-size:11px; font-weight:700; cursor:pointer;">✕ Hapus</button>' +
      '<input type="url" name="instagram_url[]" placeholder="https://www.instagram.com/p/..." style="padding-right:70px;">';
    list.appendChild(row);
  };

  // Rating input (klik bintang isi input number tersembunyi)
  document.querySelectorAll('.rating-input').forEach(function (group) {
    var hidden = group.querySelector('input[type=hidden]');
    var stars = group.querySelectorAll('button[data-val]');
    function paint(value) {
      stars.forEach(function (s) {
        s.textContent = Number(s.getAttribute('data-val')) <= value ? '★' : '☆';
      });
    }
    stars.forEach(function (star) {
      star.addEventListener('click', function () {
        var val = star.getAttribute('data-val');
        hidden.value = val;
        paint(Number(val));
      });
    });
    paint(Number(hidden.value || 5));
  });
})();
