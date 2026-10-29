/*
 * 季節商品予約フォームの表示補助（合計金額、受取店舗に応じた選択肢の絞り込み）。
 * 金額・選択肢の正しさはサーバー側で検証するため、ここでの計算は表示専用。
 */
(function () {
    'use strict';

    var form = document.getElementById('reservation-form');
    var dataElement = document.getElementById('campaign-data');
    if (!form || !dataElement) {
        return;
    }

    var data = JSON.parse(dataElement.textContent);
    var storeSelect = document.getElementById('store');
    var dateSelect = document.getElementById('date');
    var timeSlotSelect = document.getElementById('time_slot');
    var totalElement = document.getElementById('total');

    function yen(amount) {
        return '¥' + amount.toLocaleString('ja-JP');
    }

    function updateTotals() {
        var total = 0;
        form.querySelectorAll('[data-product]').forEach(function (card) {
            var product = data.products[card.dataset.product];
            var select = card.querySelector('.quantity-select');
            var subtotal = select.disabled ? 0 : product.price * parseInt(select.value || '0', 10);
            card.querySelector('[data-subtotal]').textContent = yen(subtotal);
            total += subtotal;
        });
        totalElement.textContent = yen(total);
    }

    function filterOptions(select, allowed) {
        Array.prototype.forEach.call(select.options, function (option) {
            if (option.value === '') {
                return;
            }
            var enabled = allowed === null || allowed.indexOf(option.value) !== -1;
            option.disabled = !enabled;
            option.hidden = !enabled;
            if (!enabled && option.selected) {
                select.value = '';
            }
        });
    }

    function updateStore() {
        var store = data.stores[storeSelect.value] || null;
        filterOptions(dateSelect, store ? store.dates : null);
        filterOptions(timeSlotSelect, store ? store.timeSlots : null);

        form.querySelectorAll('[data-product]').forEach(function (card) {
            var product = data.products[card.dataset.product];
            var available = !store || product.stores.indexOf(storeSelect.value) !== -1;
            card.classList.toggle('is-unavailable', !available);
            card.querySelector('.quantity-select').disabled = !available;
        });
        updateTotals();
    }

    form.querySelectorAll('.quantity-select').forEach(function (select) {
        select.addEventListener('change', updateTotals);
    });
    storeSelect.addEventListener('change', updateStore);

    updateStore();
})();
