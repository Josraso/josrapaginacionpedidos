/**
 * Checkout con muchas direcciones: buscador encima de cada lista y facturación plegada.
 * Funciona con cualquier tema: se engancha a los campos estándar id_address_delivery / id_address_invoice.
 */
(function () {
  'use strict';
  var cfg = window.josrapp;
  if (!cfg) { return; }

  function post(data) {
    var fd = new FormData();
    fd.append('ajax', '1');
    fd.append('token', cfg.token);
    Object.keys(data).forEach(function (k) { fd.append(k, data[k]); });
    return fetch(cfg.ajax, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
      .then(function (r) { return r.json(); });
  }

  /** Contenedor común de las tarjetas de dirección y elemento "tarjeta" de cada radio */
  function group(name) {
    var radios = Array.prototype.slice.call(document.querySelectorAll('input[type="radio"][name="' + name + '"]'));
    if (!radios.length) { return null; }
    var container = radios[0].parentNode;
    while (container && !radios.every(function (r) { return container.contains(r); })) { container = container.parentNode; }
    if (!container) { return null; }
    if (radios.length === 1) {
      // con una sola tarjeta subimos hasta un bloque razonable
      var c = radios[0].closest('.address-selector, .js-address-selector, [id$="-addresses"], .address-item') || radios[0].parentNode;
      container = c.parentNode || c;
    }
    var items = radios.map(function (r) {
      var el = r;
      while (el.parentNode && el.parentNode !== container) { el = el.parentNode; }
      return { radio: r, item: el };
    });
    return { container: container, items: items };
  }

  function el(tag, cls, text) {
    var e = document.createElement(tag);
    if (cls) { e.className = cls; }
    if (text) { e.textContent = text; }
    return e;
  }

  function addSearch(name, type, placeholder) {
    var g = group(name);
    if (!g || g.container.parentNode.querySelector('.josrapp-search[data-type="' + type + '"]')) { return g; }
    var box = el('div', 'josrapp-search');
    box.setAttribute('data-type', type);
    var input = el('input', 'form-control');
    input.type = 'search';
    input.placeholder = placeholder;
    input.setAttribute('autocomplete', 'off');
    var results = el('div', 'josrapp-results josrapp-hidden');
    box.appendChild(input);
    box.appendChild(results);
    if (type === 'delivery') { box.appendChild(el('p', 'josrapp-search-info', cfg.t.showing)); }
    g.container.parentNode.insertBefore(box, g.container);

    var timer = null, last = '';
    input.addEventListener('input', function () {
      var q = input.value.trim();
      clearTimeout(timer);
      if (q.length < 2) { results.classList.add('josrapp-hidden'); return; }
      timer = setTimeout(function () {
        last = q;
        results.innerHTML = '';
        results.appendChild(el('div', 'josrapp-empty', cfg.t.searching));
        results.classList.remove('josrapp-hidden');
        post({ action: 'search', q: q }).then(function (r) {
          if (q !== last) { return; }
          results.innerHTML = '';
          if (!r.ok || !r.results.length) { results.appendChild(el('div', 'josrapp-empty', cfg.t.noResults)); return; }
          r.results.forEach(function (a) {
            var b = el('button');
            b.type = 'button';
            b.appendChild(el('strong', '', a.alias));
            b.appendChild(el('small', '', a.line));
            b.addEventListener('click', function () {
              results.innerHTML = '';
              results.appendChild(el('div', 'josrapp-empty', cfg.t.saving));
              post({ action: 'select', type: type, id_address: a.id }).then(function (s) {
                if (s.ok) { window.location.href = cfg.orderUrl; } else { results.innerHTML = ''; results.appendChild(el('div', 'josrapp-empty', s.error || 'Error')); }
              });
            });
            results.appendChild(b);
          });
        }).catch(function () { results.innerHTML = ''; results.appendChild(el('div', 'josrapp-empty', 'Error')); });
      }, 250);
    });
    document.addEventListener('click', function (e) { if (!box.contains(e.target)) { results.classList.add('josrapp-hidden'); } });
    input.addEventListener('focus', function () { if (results.children.length && input.value.trim().length >= 2) { results.classList.remove('josrapp-hidden'); } });
    return g;
  }

  /** Botón para volver a facturar en la misma dirección de envío */
  function sameButton(g) {
    if (!g || g.container.parentNode.querySelector('.josrapp-same')) { return null; }
    var b = el('button', 'btn btn-link josrapp-same', cfg.t.same);
    b.type = 'button';
    b.addEventListener('click', function (e) {
      e.preventDefault();
      e.stopPropagation();
      b.disabled = true;
      b.textContent = cfg.t.saving;
      post({ action: 'same' }).then(function (r) {
        if (r.ok) { window.location.href = cfg.orderUrl; } else { b.disabled = false; b.textContent = r.error || 'Error'; }
      }).catch(function () { b.disabled = false; b.textContent = cfg.t.same; });
    });
    g.container.parentNode.insertBefore(b, g.container.nextSibling);
    return b;
  }

  function foldInvoice(g) {
    if (!cfg.fold || !g || g.items.length < 2 || g.container.getAttribute('data-josrapp-folded')) { return; }
    var checked = g.items.filter(function (i) { return i.radio.checked; });
    if (!checked.length) { return; }
    g.container.setAttribute('data-josrapp-folded', '1');
    g.items.forEach(function (i) { if (!i.radio.checked) { i.item.classList.add('josrapp-hidden'); } });
    var search = g.container.parentNode.querySelector('.josrapp-search[data-type="invoice"]');
    if (search) { search.classList.add('josrapp-hidden'); }
    var a = el('button', 'btn btn-link josrapp-change', cfg.t.change);
    a.type = 'button';
    a.addEventListener('click', function (e) {
      e.preventDefault();
      e.stopPropagation(); // que el tema no lo tome como clic en el paso (lo plegaría)
      g.items.forEach(function (i) { i.item.classList.remove('josrapp-hidden'); });
      if (search) { search.classList.remove('josrapp-hidden'); }
      a.parentNode.removeChild(a);
    });
    g.container.parentNode.insertBefore(a, g.container.nextSibling);
  }

  function init() {
    addSearch('id_address_delivery', 'delivery', cfg.t.searchDelivery);
    var inv = addSearch('id_address_invoice', 'invoice', cfg.t.searchInvoice);
    if (inv) {
      sameButton(inv);   // siempre se puede volver a "la misma dirección"
      foldInvoice(inv);
    }
  }

  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); } else { init(); }
  if (window.prestashop && window.prestashop.on) {
    window.prestashop.on('updatedAddressForm', function () { setTimeout(init, 50); });
    window.prestashop.on('changedCheckoutStep', function () { setTimeout(init, 50); });
  }
})();
