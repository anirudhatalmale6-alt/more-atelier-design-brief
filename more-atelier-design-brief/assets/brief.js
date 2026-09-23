/**
 * More Atelier — Design Brief
 * Sticky film panel, cross-fading sections, real submission.
 */
(function () {
  'use strict';

  var form = document.getElementById('ma-brief');
  if (!form) { return; }

  var panels  = [].slice.call(document.querySelectorAll('.ma-panel'));
  var slots   = [].slice.call(document.querySelectorAll('.ma-mk'));
  var video   = document.getElementById('ma-video');
  var err     = document.getElementById('ma-err');
  var btn     = document.getElementById('ma-submit');
  var reduced = matchMedia('(prefers-reduced-motion: reduce)').matches;
  var opened  = Math.floor(Date.now() / 1000);

  function stacked() { return matchMedia('(max-width: 900px)').matches; }
  function say(m) {
    if (!err) { return; }
    err.textContent = m || '';
    err.classList.toggle('is-on', !!m);
  }

  /* ---- the film: only reveal once it can actually paint a frame ---- */
  if (video) {
    video.addEventListener('loadeddata', function () {
      video.classList.add('is-ready');
      var p = video.play();
      if (p && p.catch) { p.catch(function () { video.classList.remove('is-ready'); }); }
    });
    video.addEventListener('error', function () { video.classList.remove('is-ready'); });
  }

  /* ---- marker cross-fade, two slots alternating ---- */
  var front = 0, currentKey = null;
  function setMarker(num, title) {
    var key = (num || '') + '|' + (title || '');
    if (key === currentKey) { return; }
    currentKey = key;
    var incoming = slots[front ^ 1], outgoing = slots[front];
    if (!incoming || !outgoing) { return; }
    if (!num && !title) { outgoing.classList.remove('is-on'); return; }
    incoming.querySelector('.ma-mk-n').textContent = num || '';
    incoming.querySelector('.ma-mk-t').textContent = title || '';
    incoming.classList.add('is-on');
    outgoing.classList.remove('is-on');
    front ^= 1;
  }

  /* ---- scroll: fade each panel by its distance from the centre line ---- */
  var ticking = false;
  function frame() {
    ticking = false;
    var vh = innerHeight, mid = vh / 2;
    var best = null, bestD = Infinity;

    for (var i = 0; i < panels.length; i++) {
      var el = panels[i];
      if (el.id === 'ma-thanks' && !el.classList.contains('is-live')) { continue; }
      var r = el.getBoundingClientRect();
      var d = Math.abs(r.top + r.height / 2 - mid);
      if (d < bestD) { bestD = d; best = el; }

      if (!stacked() && !reduced) {
        var t = (d - vh * 0.26) / (vh * 0.30);
        var o = t <= 0 ? 1 : (t >= 1 ? 0 : 1 - t);
        el.style.opacity = o.toFixed(3);
        el.classList.toggle('is-on', o > 0.02);
      } else {
        el.style.opacity = '';
        el.classList.add('is-on');
      }
    }
    if (best) { setMarker(best.getAttribute('data-num'), best.getAttribute('data-title')); }
  }
  function onScroll() { if (!ticking) { ticking = true; requestAnimationFrame(frame); } }
  addEventListener('scroll', onScroll, { passive: true });
  addEventListener('resize', onScroll);

  /* ---- textareas grow with the answer, so nothing is ever a box ---- */
  [].forEach.call(form.querySelectorAll('.ma-ta'), function (ta) {
    function grow() { ta.style.height = 'auto'; ta.style.height = ta.scrollHeight + 'px'; }
    ta.addEventListener('input', grow);
    grow();
  });

  /* ---- uploads ---- */
  // wp_localize_script hands these over as strings — make them numbers here
  // so later arithmetic can't silently concatenate.
  var MAX_FILES = Number(window.MADB && MADB.maxFiles) || 5;
  var MAX_BYTES = Number(window.MADB && MADB.maxBytes) || 24 * 1024 * 1024;

  function human(b) {
    if (b < 1024) { return b + ' B'; }
    if (b < 1048576) { return Math.round(b / 1024) + ' KB'; }
    return (b / 1048576).toFixed(1) + ' MB';
  }

  [].forEach.call(form.querySelectorAll('input[type=file]'), function (inp) {
    var list = form.querySelector('.ma-files[data-for="' + inp.id + '"]');
    inp.addEventListener('change', function () {
      list.innerHTML = '';
      var files = [].slice.call(inp.files), bad = [];
      if (files.length > MAX_FILES) {
        bad.push('Please choose no more than ' + MAX_FILES + ' files.');
        files = files.slice(0, MAX_FILES);
      }
      files.forEach(function (f) {
        var li = document.createElement('li');
        var nm = document.createElement('span');
        var sz = document.createElement('span');
        nm.textContent = f.name;
        sz.className = 'ma-sz';
        if (f.size > MAX_BYTES) {
          sz.textContent = human(f.size) + ' — too large';
          bad.push('“' + f.name + '” is over ' + Math.round(MAX_BYTES / 1048576) + 'MB.');
        } else {
          sz.textContent = human(f.size);
        }
        li.appendChild(nm); li.appendChild(sz);
        list.appendChild(li);
      });
      say(bad.length ? bad[0] : '');
    });
  });

  /* ---- "unsure" and a typed size are mutually exclusive ---- */
  var size = document.getElementById('f-project_size');
  var unsure = form.querySelector('input[name="size_unsure[]"]');
  if (size && unsure) {
    unsure.addEventListener('change', function () { if (unsure.checked) { size.value = ''; } });
    size.addEventListener('input', function () { if (size.value) { unsure.checked = false; } });
  }

  /* ---- submit ---- */
  function focusField(el, message) {
    say(message);
    el.scrollIntoView({ behavior: 'smooth', block: 'center' });
    setTimeout(function () { el.focus({ preventScroll: true }); }, 400);
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    if (btn.disabled) { return; }

    var name  = document.getElementById('f-name');
    var email = document.getElementById('f-email');

    if (!name.value.trim()) {
      return focusField(name, 'Please add your name.');
    }
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(email.value.trim())) {
      return focusField(email, 'Please check your email address — that’s where we’ll reply.');
    }

    var oversize = [].slice.call(form.querySelectorAll('input[type=file]')).some(function (inp) {
      return [].slice.call(inp.files).some(function (f) { return f.size > MAX_BYTES; });
    });
    if (oversize) {
      return say('One of your files is too large — please remove it and try again.');
    }

    say('');
    btn.disabled = true;
    btn.textContent = 'Sending';

    var data = new FormData(form);
    data.append('ma_elapsed', String(Math.floor(Date.now() / 1000) - opened));

    fetch(MADB.endpoint, { method: 'POST', body: data, credentials: 'same-origin' })
      .then(function (res) {
        return res.json().then(function (body) { return { ok: res.ok, body: body }; });
      })
      .then(function (r) {
        if (!r.ok) {
          throw new Error((r.body && r.body.message) || 'Something went wrong. Please try again.');
        }
        var thanks = document.getElementById('ma-thanks');
        thanks.classList.add('is-live');
        thanks.scrollIntoView({ behavior: 'smooth', block: 'center' });
        setTimeout(onScroll, 100);
      })
      .catch(function (e) {
        btn.disabled = false;
        btn.textContent = 'Submit';
        say(e.message || 'Something went wrong. Please try again.');
      });
  });

  frame();
})();
