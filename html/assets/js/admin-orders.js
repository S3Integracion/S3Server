/* admin-orders.js */
(() => {
    'use strict';
    const { init, apiFetch, buildTable, buildPaginator, escHtml, fmtDate, badge, wireAccordions } = window.AdminCommon;

    let currentPage = 1;

    async function loadMarketplaces() {
        const res = await apiFetch('/admin/orders/marketplaces');
        if (!res || !res.ok) return;
        const { data } = await res.json();
        const sel = document.getElementById('orderMarketplace');
        if (!sel) return;
        (data || []).forEach(m => {
            const opt = document.createElement('option');
            opt.value       = m.country_code;
            opt.textContent = `${m.country_code} — ${m.currency_code}`;
            sel.appendChild(opt);
        });
    }

    function deliveryStatusBadge(status) {
        if (!status) return '—';
        const map = {
            'Entregado':     'success',
            'Tránsito':      'warn',
            'Sin movimiento':'warn',
            'No entregado':  'danger',
            'Cancelado':     'danger',
        };
        return badge(status, map[status] || 'neutral');
    }

    function refundBadge(hasRefund) {
        if (!hasRefund || hasRefund === 'No') return badge('No', 'neutral');
        return badge('Sí', 'danger');
    }

    function fmtCurrency(amount) {
        if (amount === null || amount === undefined || amount === '') return '—';
        const n = parseFloat(amount);
        return isNaN(n) ? '—' : n.toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    async function loadOrders() {
        const params = new URLSearchParams({ page: currentPage, per_page: 20 });
        const search   = document.getElementById('orderSearch')?.value.trim();
        const market   = document.getElementById('orderMarketplace')?.value;
        const from     = document.getElementById('orderFrom')?.value;
        const to       = document.getElementById('orderTo')?.value;
        const delivery = document.getElementById('orderDeliveryStatus')?.value;
        const refund   = document.getElementById('orderHasRefund')?.value;
        if (search)   params.set('search', search);
        if (market)   params.set('marketplace_country', market);
        if (from)     params.set('date_from', from);
        if (to)       params.set('date_to', to);
        if (delivery) params.set('delivery_status', delivery);
        if (refund)   params.set('has_refund', refund);

        const res = await apiFetch('/admin/orders?' + params);
        if (!res || !res.ok) return;
        const { data, meta } = await res.json();

        buildTable(document.getElementById('ordersTable'), [
            { key: 'order_id',                        label: 'Orden ID' },
            { key: 'tracking_id',                     label: 'Tracking ID' },
            { key: 'asin',                            label: 'ASIN' },
            { key: 'sku',                             label: 'SKU' },
            { key: 'order_item_id',                   label: 'Item ID' },
            { key: 'product_name',                    label: 'Producto' },
            { key: 'condition',                       label: 'Condición' },
            { key: 'flag_multiples_paquetes',         label: 'Múlt. paquetes' },
            { key: 'cant_paquetes',                   label: 'Cant. paquetes' },
            { key: 'cant_items',                      label: 'Cant. items' },
            { key: 'total_cantidad',                  label: 'Total cantidad' },
            { key: 'shipping_service',                label: 'Servicio envío' },
            { key: 'flag_tiene_movimiento',           label: 'Con movimiento' },
            { label: 'Estatus entrega',   html: r => deliveryStatusBadge(r.delivery_status) },
            { label: 'Reembolso',         html: r => refundBadge(r.has_refund) },
            { label: 'Monto reembolso',   render: r => fmtCurrency(r.refund_amount) },
            { label: '1er rastreo pkg1',              render: r => fmtDate(r.primera_fecha_rastreo_paquete_1) },
            { label: 'Últ. rastreo pkg1',             render: r => fmtDate(r.ultima_fecha_rastreo_paquete_1) },
            { key: 'primer_evento_rastreo_paquete_1', label: '1er evento pkg1' },
            { key: 'ultimo_evento_rastreo_paquete_1', label: 'Últ. evento pkg1' },
            { label: 'Fecha compra',                  render: r => fmtDate(r.fecha_compra) },
            { label: 'Límite envío',                  render: r => fmtDate(r.fecha_limite_envio) },
            { label: 'Entrega est. desde',            render: r => fmtDate(r.fecha_entrega_estimada_desde) },
            { label: 'Entrega est. hasta',            render: r => fmtDate(r.fecha_entrega_estimada_hasta) },
            { label: 'Fecha extracción',              render: r => fmtDate(r.fecha_extraccion) },
            { key: 'package_type',                    label: 'Tipo paquete' },
            { key: 'dimensions_LWH',                  label: 'Dimensiones LxAxA' },
            { key: 'package_weight',                  label: 'Peso' },
            { key: 'cantidad_item',                   label: 'Cantidad item' },
            { key: 'precio_item',                     label: 'Precio item' },
            { key: 'precio_total_items',              label: 'Total items' },
            { key: 'impuesto_total',                  label: 'Impuesto total' },
            { key: 'precio_total_orden',              label: 'Total orden' },
            { key: 'destinatario_nombre',             label: 'Destinatario' },
            { key: 'contacto_nombre',                 label: 'Contacto' },
            { key: 'telefono',                        label: 'Teléfono' },
            { key: 'direccion_linea_1',               label: 'Dirección 1' },
            { key: 'direccion_linea_2',               label: 'Dirección 2' },
            { key: 'direccion_linea_3',               label: 'Dirección 3' },
            { key: 'ciudad',                          label: 'Ciudad' },
            { key: 'estado_region',                   label: 'Estado/Región' },
            { key: 'codigo_postal',                   label: 'C.P.' },
            { key: 'first_imported_by',               label: '1er importado por' },
            { key: 'last_imported_by',                label: 'Últ. importado por' },
            { label: '1era importación',              render: r => fmtDate(r.primera_importacion_at) },
            { label: 'Últ. importación',              render: r => fmtDate(r.ultima_importacion_at) },
            { label: 'País mkt', html: r => badge(r.marketplace_country || '—', 'neutral') },
            { key: 'marketplace_currency',            label: 'Moneda mkt' },
        ], (data || []).map(r => ({
            ...r,
            _onClick: () => loadOrderDetail(r.orders_pk),
        })));

        buildPaginator(document.getElementById('ordersPagination'), meta, p => { currentPage = p; loadOrders(); });
    }

    async function loadOrderDetail(orderId) {
        const res = await apiFetch(`/admin/orders/${orderId}`);
        if (!res || !res.ok) return;
        const { data } = await res.json();

        document.getElementById('orderDetailSection').style.display = '';
        document.getElementById('orderDetailTitle').textContent = `Orden: ${data.order_id}`;

        const info = document.getElementById('orderInfoPanel');
        const refundAmountText = (data.refund_amount === null || data.refund_amount === undefined || data.refund_amount === '')
            ? '—'
            : `${escHtml(data.marketplace_currency || '')} ${parseFloat(data.refund_amount).toFixed(2)}`;
        info.innerHTML = `
            <h3>Información general</h3>
            <dl style="display:grid;grid-template-columns:160px 1fr;gap:.4rem .75rem;font-size:.9rem">
              <dt style="font-weight:700;color:var(--text-dim)">Orden ID</dt><dd>${escHtml(data.order_id)}</dd>
              <dt style="font-weight:700;color:var(--text-dim)">Destinatario</dt><dd>${escHtml(data.recipient_name || '—')}</dd>
              <dt style="font-weight:700;color:var(--text-dim)">Dirección</dt>
              <dd>${escHtml([data.address_line_1, data.address_line_2, data.city, data.state_region, data.postal_code, data.country_code].filter(Boolean).join(', '))}</dd>
              <dt style="font-weight:700;color:var(--text-dim)">Total</dt><dd>${escHtml(data.marketplace_currency || '')} ${parseFloat(data.grand_total || 0).toFixed(2)}</dd>
              <dt style="font-weight:700;color:var(--text-dim)">Estatus entrega</dt><dd>${deliveryStatusBadge(data.delivery_status)}</dd>
              <dt style="font-weight:700;color:var(--text-dim)">Reembolso</dt><dd>${refundBadge(data.has_refund)}</dd>
              <dt style="font-weight:700;color:var(--text-dim)">Monto reembolso</dt><dd>${refundAmountText}</dd>
              <dt style="font-weight:700;color:var(--text-dim)">Pago</dt><dd>${escHtml(data.payment_method || '—')}</dd>
              <dt style="font-weight:700;color:var(--text-dim)">Fulfillment</dt><dd>${escHtml(data.fulfillment_channel || '—')}</dd>
              <dt style="font-weight:700;color:var(--text-dim)">Fecha compra</dt><dd>${fmtDate(data.purchase_datetime)}</dd>
              <dt style="font-weight:700;color:var(--text-dim)">Enviar antes de</dt><dd>${fmtDate(data.ship_by_date)}</dd>
            </dl>`;

        const itemTbody = document.querySelector('#orderItemsTable tbody');
        itemTbody.innerHTML = '';
        (data.items || []).forEach(item => {
            const tr = itemTbody.insertRow();
            tr.innerHTML = `
                <td>${escHtml(item.asin || '—')}</td>
                <td>${escHtml(item.sku || '—')}</td>
                <td class="wrap">${escHtml(item.product_name || '—')}</td>
                <td>${escHtml(String(item.quantity || 0))}</td>
                <td>${parseFloat(item.unit_price || 0).toFixed(2)}</td>
                <td>${parseFloat(item.item_total || 0).toFixed(2)}</td>`;
        });

        const pkgSection = document.getElementById('orderPackagesSection');
        pkgSection.innerHTML = '';
        (data.packages || []).forEach((pkg, i) => {
            const div = document.createElement('div');
            div.className = 'detail-panel';
            div.innerHTML = `
                <h3>Paquete #${pkg.package_number || (i + 1)} — ${escHtml(pkg.tracking_id || 'Sin tracking')}</h3>
                <p style="font-size:.88rem;color:var(--text-dim);margin-bottom:.75rem">
                  ${escHtml(pkg.carrier || '—')} · ${escHtml(pkg.shipping_service || '—')}
                </p>
                ${(pkg.delivery_events || []).length === 0
                    ? '<p style="color:var(--text-dim);font-size:.88rem">Sin eventos de entrega</p>'
                    : (pkg.delivery_events || []).map(ev => `
                        <div class="accordion-item">
                          <button class="accordion-trigger" aria-expanded="false">
                            ${escHtml(fmtDate(ev.event_time))} — ${escHtml(ev.location || '—')}
                            <span class="accordion-arrow">▾</span>
                          </button>
                          <div class="accordion-body">
                            <p>${escHtml(ev.event_details || '—')}</p>
                          </div>
                        </div>`).join('')
                }`;
            wireAccordions(div);
            pkgSection.appendChild(div);
        });

        document.getElementById('orderDetailSection').scrollIntoView({ behavior: 'smooth' });
    }

    async function setup() {
        await init();
        await loadMarketplaces();
        await loadOrders();

        document.getElementById('btnSearch')?.addEventListener('click', () => { currentPage = 1; loadOrders(); });
        document.getElementById('orderSearch')?.addEventListener('keydown', e => { if (e.key === 'Enter') { currentPage = 1; loadOrders(); } });
        document.getElementById('orderDeliveryStatus')?.addEventListener('change', () => { currentPage = 1; loadOrders(); });
        document.getElementById('orderHasRefund')?.addEventListener('change', () => { currentPage = 1; loadOrders(); });
        document.getElementById('btnCloseDetail')?.addEventListener('click', () => {
            document.getElementById('orderDetailSection').style.display = 'none';
        });
    }

    document.addEventListener('DOMContentLoaded', setup);
})();
