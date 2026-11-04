/*
 * 季節商品予約フォームの表示補助（個数の増減、合計金額、受取店舗に応じた選択肢の絞り込み、
 * 店舗と商品の受け取り可否のその場チェック、入力途中の内容の保持）。
 * 金額・選択肢・受け取り可否の正しさはサーバー側でも検証する。
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
    var productErrorElement = document.getElementById('product-availability-error');
    var storeErrorElement = document.getElementById('store-availability-error');

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

    function selectedStore() {
        var checked = form.querySelector('input[name="store"]:checked');
        return checked ? checked.value : null;
    }

    function isProductAvailableAt(productId, storeCode) {
        return !storeCode || data.products[productId].stores.indexOf(storeCode) !== -1;
    }

    function availabilityMessage(productId, storeCode) {
        return '「' + data.products[productId].name + '」は「' + data.stores[storeCode].name + '」ではお受け取りできません。';
    }

    function conflictingProductIds(storeCode) {
        var ids = [];
        if (!storeCode) {
            return ids;
        }
        form.querySelectorAll('[data-product]').forEach(function (card) {
            var productId = card.dataset.product;
            if (quantityOf(card.querySelector('.stepper__input')) > 0 && !isProductAvailableAt(productId, storeCode)) {
                ids.push(productId);
            }
        });
        return ids;
    }

    function setError(element, message) {
        if (!element) {
            return;
        }
        if (message) {
            element.textContent = message;
            element.hidden = false;
        } else {
            element.textContent = '';
            element.hidden = true;
        }
    }

    // 受け取り可能な店舗として確定した選択。不一致の店舗はここに入れない。
    var acceptedStoreCode = null;

    function clearAvailabilityErrors() {
        setError(productErrorElement, '');
        setError(storeErrorElement, '');
    }

    function showStoreAvailabilityError(storeCode) {
        var conflicts = conflictingProductIds(storeCode);
        if (conflicts.length === 0) {
            return false;
        }
        setError(productErrorElement, '');
        setError(storeErrorElement, availabilityMessage(conflicts[0], storeCode));
        storeErrorElement.scrollIntoView({block: 'nearest', behavior: 'smooth'});
        return true;
    }

    function showProductAvailabilityError(productId, storeCode) {
        setError(storeErrorElement, '');
        setError(productErrorElement, availabilityMessage(productId, storeCode));
        productErrorElement.scrollIntoView({block: 'nearest', behavior: 'smooth'});
    }

    function updateTotals() {
        var total = 0;
        var count = 0;
        form.querySelectorAll('[data-product]').forEach(function (card) {
            var input = card.querySelector('.stepper__input');
            var quantity = quantityOf(input);
            total += data.products[card.dataset.product].price * quantity;
            count += quantity;
            card.classList.toggle('is-selected', quantity > 0);
            card.querySelector('[data-step="-1"]').disabled = quantity <= 0;
            card.querySelector('[data-step="1"]').disabled = quantity >= parseInt(input.max, 10);
        });
        totalElement.textContent = yen(total);
        countElement.textContent = count > 0 ? count + '点' : '';
        saveDraft();
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

    function checkStore(code) {
        form.querySelectorAll('input[name="store"]').forEach(function (input) {
            input.checked = code !== null && input.value === code;
        });
    }

    function refreshStoreDependentUi() {
        var code = selectedStore();
        var store = code ? data.stores[code] : null;
        filterChoices('date', store ? store.dates : null);
        filterChoices('time_slot', store ? store.timeSlots : null);

        form.querySelectorAll('[data-product]').forEach(function (card) {
            card.classList.toggle('is-unavailable', !isProductAvailableAt(card.dataset.product, code));
        });
        updateTotals();
    }

    // 個数の＋と同様、受け取りできない店舗はエラーだけ出して選択を確定しない
    function applyStore(code) {
        if (code && conflictingProductIds(code).length > 0) {
            showStoreAvailabilityError(code);
            checkStore(acceptedStoreCode);
            refreshStoreDependentUi();
            return false;
        }

        acceptedStoreCode = code;
        checkStore(code);
        clearAvailabilityErrors();
        refreshStoreDependentUi();
        return true;
    }

    function applyQuantity(input, productId, nextQuantity) {
        var code = selectedStore();
        if (code && nextQuantity > 0 && !isProductAvailableAt(productId, code)) {
            showProductAvailabilityError(productId, code);
            return false;
        }
        input.value = String(nextQuantity);
        if (conflictingProductIds(code).length === 0) {
            clearAvailabilityErrors();
        }
        updateTotals();
        return true;
    }

    form.querySelectorAll('[data-product]').forEach(function (card) {
        var productId = card.dataset.product;
        var input = card.querySelector('.stepper__input');

        card.querySelectorAll('[data-step]').forEach(function (button) {
            button.addEventListener('click', function () {
                var next = Math.max(0, Math.min(
                    quantityOf(input) + parseInt(button.dataset.step, 10),
                    parseInt(input.max, 10)
                ));
                applyQuantity(input, productId, next);
            });
        });

        input.addEventListener('focus', function () {
            input.dataset.previousQuantity = String(quantityOf(input));
        });
        input.addEventListener('input', updateTotals);
        input.addEventListener('change', function () {
            var next = quantityOf(input);
            if (!applyQuantity(input, productId, next)) {
                input.value = input.dataset.previousQuantity || '0';
                updateTotals();
            }
        });
    });

    form.querySelectorAll('input[name="store"]').forEach(function (input) {
        input.addEventListener('change', function () {
            applyStore(input.value);
        });
    });
    form.addEventListener('input', saveDraft);
    form.addEventListener('change', saveDraft);
    form.addEventListener('submit', function (event) {
        var code = selectedStore();
        if (code && showStoreAvailabilityError(code)) {
            event.preventDefault();
        }
    });

    // 入力エラー・「修正する」で戻ったときはサーバーから戻した内容を優先する
    if (!form.hasAttribute('data-has-old-input')) {
        restoreDraft();
        applyStore(selectedStore());
    } else {
        acceptedStoreCode = selectedStore();
        refreshStoreDependentUi();
    }
})();
