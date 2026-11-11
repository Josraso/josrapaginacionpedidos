/**
 * josrapaginacionpedidos - back-office.
 * Pagina en el navegador las listas largas que PrestaShop pinta enteras:
 *  - Pedido manual: carritos y pedidos del cliente elegido.
 *  - Ficha de cliente: mensajes, correos enviados, conexiones y cualquier otra tabla sin paginación propia.
 * No cambia datos ni peticiones: solo oculta filas y añade los botones de página.
 */
(function () {
  'use strict';

  var cfg = window.josrappAdmin || {};
  var t = cfg.t || {};

  function text(key, a, b, c) {
    return String(t[key] || '').replace('%1', a).replace('%2', b).replace('%3', c);
  }

  function button(label, title, onClick) {
    var b = document.createElement('button');
    b.type = 'button';
    b.className = 'btn btn-sm btn-outline-secondary';
    b.textContent = label;
    b.title = title;
    b.addEventListener('click', function (e) {
      e.preventDefault();
      onClick();
    });
    return b;
  }

  function paginate(table, perPage) {
    if (!table || table.josrappDone) {
      return;
    }
    var tbody = table.tBodies && table.tBodies[0];
    if (!tbody) {
      return;
    }
    table.josrappDone = true;

    var page = 1;
    var lastCount = -1;
    var pager = document.createElement('div');
    pager.className = 'josrapp-pager';
    var info = document.createElement('span');
    info.className = 'josrapp-pager-info';
    var pos = document.createElement('span');
    pos.className = 'josrapp-pager-pos';
    var first = button('«', t.first || '', function () { page = 1; render(); });
    var prev = button('‹', t.prev || '', function () { page -= 1; render(); });
    var next = button('›', t.next || '', function () { page += 1; render(); });
    var last = button('»', t.last || '', function () { page = 1e9; render(); });
    var nav = document.createElement('span');
    nav.className = 'josrapp-pager-nav';
    [first, prev, pos, next, last].forEach(function (el) { nav.appendChild(el); });
    pager.appendChild(info);
    pager.appendChild(nav);
    var anchor = table.parentNode && /table-responsive/.test(table.parentNode.className) ? table.parentNode : table;
    anchor.parentNode.insertBefore(pager, anchor.nextSibling);

    function rows() {
      var out = [];
      for (var i = 0; i < tbody.children.length; i += 1) {
        if (tbody.children[i].tagName === 'TR') {
          out.push(tbody.children[i]);
        }
      }
      return out;
    }

    function render() {
      var list = rows();
      var total = list.length;
      var pages = Math.max(1, Math.ceil(total / perPage));
      if (total !== lastCount) {
        // Lista nueva (otro cliente, recarga): se vuelve al principio
        page = 1;
        lastCount = total;
      }
      page = Math.min(Math.max(1, page), pages);
      var from = (page - 1) * perPage;
      var to = Math.min(total, from + perPage);
      for (var i = 0; i < total; i += 1) {
        list[i].style.display = i >= from && i < to ? '' : 'none';
      }
      pager.style.display = total > perPage ? '' : 'none';
      info.textContent = text('showing', from + 1, to, total);
      pos.textContent = text('page', page, pages);
      first.disabled = prev.disabled = page <= 1;
      next.disabled = last.disabled = page >= pages;
    }

    var timer = null;
    if (window.MutationObserver) {
      new MutationObserver(function () {
        clearTimeout(timer);
        timer = setTimeout(render, 30);
      }).observe(tbody, { childList: true });
    }
    render();
  }

  /* Pedido manual: carritos y pedidos del cliente */
  function orderCreate() {
    var carts = document.getElementById('customer-carts-table');
    var orders = document.getElementById('customer-orders-table');
    if (!carts && !orders) {
      return false;
    }
    if (!cfg.order) {
      return true;
    }
    var perPage = parseInt(cfg.orderPerPage, 10) || 5;
    paginate(carts, perPage);
    paginate(orders, perPage);

    if (cfg.orderFold) {
      var box = document.getElementById('customer-tab-content-container');
      var toggler = document.getElementById('customer-tab-content-container-toggler');
      var wrap = document.getElementById('customer-checkout-history');
      if (box && wrap && window.MutationObserver) {
        var wasHidden = /(^|\s)d-none(\s|$)/.test(wrap.className);
        var fold = function () {
          box.classList.remove('show');
          if (toggler) {
            toggler.setAttribute('aria-expanded', 'false');
            toggler.classList.add('collapsed');
          }
        };
        // Se pliega cada vez que aparece el bloque (al elegir cliente); el botón nativo lo vuelve a abrir
        new MutationObserver(function () {
          var hidden = /(^|\s)d-none(\s|$)/.test(wrap.className);
          if (wasHidden && !hidden) {
            fold();
          }
          wasHidden = hidden;
        }).observe(wrap, { attributes: true, attributeFilter: ['class'] });
        if (!wasHidden) {
          fold();
        }
      }
    }
    return true;
  }

  /* Ficha de cliente: tablas sin paginación propia */
  function customerView() {
    if (!cfg.customer) {
      return;
    }
    var perPage = parseInt(cfg.customerPerPage, 10) || 10;
    // 1.7.6 a 9: tarjetas de la ficha. Antes de 1.7.6: paneles de la ficha antigua.
    var isView = document.querySelector('.customer-personal-informations-card, [class*="customer-"][class*="-card"], #container-customer');
    if (!isView) {
      return;
    }
    var tables = document.querySelectorAll('.card table.table, #container-customer .panel table.table');
    for (var i = 0; i < tables.length; i += 1) {
      var tb = tables[i];
      // Las rejillas de PrestaShop ya traen su paginación
      if (/grid-table/.test(tb.className) || (tb.closest && tb.closest('.grid, .js-grid, .js-grid-panel'))) {
        continue;
      }
      paginate(tb, perPage);
    }
  }

  function init() {
    if (!orderCreate()) {
      customerView();
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
