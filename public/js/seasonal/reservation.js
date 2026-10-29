/*
 * 季節商品予約フォームの表示補助（個数の増減、合計金額、受取店舗に応じた選択肢の絞り込み）。
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
    var totalElement = document.getElementById('total');
    var countElement = document.getElementById('item-count');

    function yen(amount) {
        return '¥' + amount.toLocaleString('ja-JP');
    }

    function quantityOf(input) {
        var value = parseInt(input.value, 10);
        if (isNaN(value) || value < 0) {
            return 0;
        }
        return Math.min(value, parseInt(input.max, 10));
    }

    function updateTotals() {
        var total = 0;
        var count = 0;
        form.querySelectorAll('[data-product]').forEach(function (card) {
            var input = card.querySelector('.stepper__input');
            var quantity = input.disabled ? 0 : quantityOf(input);
            total += data.products[card.dataset.product].price * quantity;
            count += quantity;
            card.classList.toggle('is-selected', quantity > 0);
            card.querySelector('[data-step="-1"]').disabled = input.disabled || quantity <= 0;
            card.querySelector('[data-step="1"]').disabled = input.disabled || quantity >= parseInt(input.max, 10);
        });
        totalElement.textContent = yen(total);
        countElement.textContent = count > 0 ? count + '点' : '';
    }

    function selectedStore() {
        var checked = form.querySelector('input[name="store"]:checked');
        return checked ? checked.value : null;
    }

    function filterChoices(name, allowed) {
        form.querySelectorAll('input[name="' + name + '"]').forEach(function (input) {
            var enabled = allowed === null || allowed.indexOf(input.value) !== -1;
            input.disabled = !enabled;
            if (!enabled) {
                input.checked = false;
            }
            input.closest('.choice').hidden = !enabled;
        });
    }

    function updateStore() {
        var code = selectedStore();
        var store = code ? data.stores[code] : null;
        filterChoices('date', store ? store.dates : null);
        filterChoices('time_slot', store ? store.timeSlots : null);

        form.querySelectorAll('[data-product]').forEach(function (card) {
            var available = !code || data.products[card.dataset.product].stores.indexOf(code) !== -1;
            card.classList.toggle('is-unavailable', !available);
            card.querySelector('.stepper__input').disabled = !available;
        });
        updateTotals();
    }

    form.querySelectorAll('[data-product]').forEach(function (card) {
        var input = card.querySelector('.stepper__input');
        card.querySelectorAll('[data-step]').forEach(function (button) {
            button.addEventListener('click', function () {
                input.value = String(Math.max(0, Math.min(quantityOf(input) + parseInt(button.dataset.step, 10), parseInt(input.max, 10))));
                updateTotals();
            });
        });
        input.addEventListener('input', updateTotals);
        input.addEventListener('change', function () {
            input.value = String(quantityOf(input));
            updateTotals();
        });
    });
    form.querySelectorAll('input[name="store"]').forEach(function (input) {
        input.addEventListener('change', updateStore);
    });

    updateStore();
})();
