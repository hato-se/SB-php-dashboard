<div id="error-banner" class="error-banner" role="alert" hidden></div>

<div id="loading" class="loading" aria-live="polite">
    <div class="spinner" aria-hidden="true"></div>
    <p>データを取得中...</p>
</div>

<section id="device-grid"
         class="device-grid"
         aria-label="温湿度計デバイス一覧">
    {{-- JavaScript によって動的に生成されます --}}
</section>

<div id="no-devices" class="no-devices" role="status" hidden>
    <span class="no-devices__icon" aria-hidden="true">📡</span>
    温湿度計デバイスが見つかりませんでした。<br>
    SwitchBot アプリでデバイスが追加済みか、<br>
    クラウドサービスが有効になっているか確認してください。
</div>

<!-- 表示設定モーダル -->
<div id="visibility-modal"
     class="modal-backdrop"
     role="dialog"
     aria-modal="true"
     aria-labelledby="modal-title"
     hidden>
    <div class="modal">
        <div class="modal__header">
            <h2 id="modal-title" class="modal__title">
                <span aria-hidden="true">⚙</span> 表示設定
            </h2>
            <button id="modal-close"
                    class="modal__close"
                    type="button"
                    aria-label="閉じる">✕</button>
        </div>
        <div class="modal__body">
            <p class="modal__description">
                ダッシュボードに表示するデバイスを選択してください。
            </p>
            <!-- デバイスのトグルリストは JavaScript が動的に生成 -->
            <ul id="visibility-list" class="visibility-list" role="list"></ul>
        </div>
        <div class="modal__footer">
            <button id="modal-show-all" class="btn-secondary" type="button">すべて表示</button>
            <button id="modal-hide-all" class="btn-secondary" type="button">すべて非表示</button>
        </div>
    </div>
</div>
