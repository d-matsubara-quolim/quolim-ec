/*
 * data-submit-once を付けたフォームの二度押し防止（サーバー側でも同じ予約は 1 回だけ登録する）。
 */
(function () {
    'use strict';

    document.querySelectorAll('form[data-submit-once]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (form.dataset.submitted === 'true') {
                event.preventDefault();
                return;
            }
            form.dataset.submitted = 'true';
            document.querySelectorAll('.actions button').forEach(function (button) {
                button.disabled = true;
            });
        });
    });
})();
