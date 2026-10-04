// assets/js/admin-edit.js — データセット（poll）編集画面
(function ($) {
    'use strict';

    $(function () {
        // ショートコードのコピー
        $('#kashiwazaki_poll_copy_btn').on('click', function () {
            var input = document.getElementById('kashiwazaki_poll_shortcode_field');
            var done = $('.kspoll-copy-done');
            if (!input) { return; }
            var showDone = function () { done.text('コピーしました'); setTimeout(function () { done.text(''); }, 2000); };
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(input.value).then(showDone, function () { input.select(); document.execCommand('copy'); showDone(); });
            } else {
                input.select();
                document.execCommand('copy');
                showDone();
            }
        });

        // 投票数の合計と割合をその場で更新
        var $countInputs = $('.kspoll-count-input');
        function refreshCounts() {
            var values = [];
            var total = 0;
            $countInputs.each(function () {
                var v = parseInt(this.value, 10);
                if (isNaN(v) || v < 0) { v = 0; }
                values.push(v);
                total += v;
            });
            $('.kspoll-total').text(total);
            $countInputs.each(function (i) {
                var text = total > 0 ? (Math.round(values[i] / total * 1000) / 10) + '%' : '—';
                $(this).closest('tr').find('.kspoll-percent').text(text);
            });
        }
        $countInputs.on('input change', refreshCounts);

        // 説明文の文字数
        $('#kashiwazaki_poll_description_field').on('input', function () {
            $('.kspoll-desc-count').text(Array.from(this.value).length);
        });

        // 集計データの全削除
        $('#kashiwazaki_poll_reset_btn').on('click', function () {
            if (!window.confirm('本当にこの集計データをすべて削除してもよろしいですか？\n\nこの操作は元に戻せません。')) { return; }
            var field = document.getElementById('kashiwazaki_poll_reset_action_field');
            var form = document.getElementById('post');
            if (!field || !form) { return; }
            field.value = '1';
            field.name = 'kashiwazaki_poll_reset_data_submit';
            // form.submit() は submit イベントを起こさないので、前の送信で写したボタンの値を先に消す
            // （取り消された「公開」の publish が残ったまま送られ、下書きが公開されるのを防ぐ）。
            removeSubmitterCopies(form);
            form.submit();
        });
    });

    // 押された送信ボタンの name / value を hidden に写す。
    // 他のプラグインが submit イベントの中で送信ボタンを disabled にすると、ブラウザは
    // そのボタンの name を送らない（無効な要素は送信内容に含まれない）。すると新規の「公開」
    // (name=publish) が下書きとして保存されてしまう。submit イベントを捕捉段階で受け取り、
    // 実際に押されたボタン (event.submitter) と同じ name / value を写しておく。
    // 公開済みの「更新」ボタンは name=save なので save が写り、ステータスの選択はそのまま効く。
    // 写しはこの送信のためだけのもので、次のタスクで必ず消す。送信内容 (entry list) は submit
    // イベントの直後に同じ処理の中で作られるので、送信が進めば写しは含まれる。ほかの処理が送信を
    // 取り消した場合も写しは残らず、後の form.submit() や jQuery の trigger('submit')（どちらも
    // submit イベントを起こさない。例: プレビュー）に「公開」の値が紛れ込むことはない。
    var COPY_CLASS = 'kspoll-submitter-copy';
    function removeSubmitterCopies(form) {
        var olds = form.querySelectorAll('input.' + COPY_CLASS);
        for (var i = 0; i < olds.length; i++) { olds[i].parentNode.removeChild(olds[i]); }
    }
    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (!form || form.id !== 'post') { return; }
        removeSubmitterCopies(form);
        var submitter = event.submitter;
        if (!submitter || !submitter.name || submitter.form !== form) { return; }
        var copy = document.createElement('input');
        copy.type = 'hidden';
        copy.className = COPY_CLASS;
        copy.name = submitter.name;
        copy.value = submitter.value;
        form.appendChild(copy);
        window.setTimeout(function () {
            if (copy.parentNode) { copy.parentNode.removeChild(copy); }
        }, 0);
    }, true);
})(jQuery);
