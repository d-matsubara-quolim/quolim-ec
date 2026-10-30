/*
 * 季節商品予約フォームの表示補助（個数の増減、合計金額、受取店舗に応じた選択肢の絞り込み、入力途中の内容の保持）。
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

    // 商品詳細ページなどへ移動して戻っても入力内容が消えないよう、タブ内（sessionStorage）に保存する。
    // タブを閉じると消える。予約完了画面でも消す。
    var draftKey = form.dataset.draftKey;
    var draftFields = ['store', 'date', 'time_slot', 'name', 'kana', 'tel', 'note'];

    function saveDraft() {
        var draft = {quantities: {}};
        form.querySelectorAll('.stepper__input').forEach(function (input) {
            draft.quantities[input.id] = input.value;
        });
        draftFields.forEach(function (name) {
            var field = form.elements[name];
            draft[name] = field ? field.value : '';
        });
        try {
            sessionStorage.setItem(draftKey, JSON.stringify(draft));
        } catch (e) {
            // 保存できない環境（プライベートモードなど）では保持しない
        }
    }

    function restoreDraft() {
        var draft;
        try {
            draft = JSON.parse(sessionStorage.getItem(draftKey));
        } catch (e) {
            return;
        }
        if (!draft) {
            return;
        }
        Object.keys(draft.quantities || {}).forEach(function (id) {
            var input = document.getElementById(id);
            if (input && input.classList.contains('stepper__input')) {
                input.value = draft.quantities[id];
            }
        });
        draftFields.forEach(function (name) {
            var field = form.elements[name];
            if (field && typeof draft[name] === 'string') {
                field.value = draft[name];
            }
        });
    }

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
        saveDraft();
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
    form.addEventListener('input', saveDraft);
    form.addEventListener('change', saveDraft);

    // 入力エラー・「修正する」で戻ったときはサーバーから戻した内容を優先する
    if (!form.hasAttribute('data-has-old-input')) {
        restoreDraft();
    }
    updateStore();
})();
