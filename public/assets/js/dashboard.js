'use strict';

/**
 * SwitchBot ダッシュボード — フロントエンド
 *
 * /api/devices.php から温湿度データを取得してカードを描画する。
 * CSS の --bar-color / --metric-color / --gauge-color / --battery-color
 * カスタムプロパティを JS から設定することで温度・湿度ゾーンの色を表現する。
 */

// ============================================================
// 定数
// ============================================================

/** 温度ゾーン定義 [上限℃, CSS カスタムプロパティ値, ラベル] */
const TEMP_ZONES = [
    [15, 'var(--color-temp-cold)',    '寒い'],
    [20, 'var(--color-temp-cool)',    '涼しい'],
    [26, 'var(--color-temp-comfort)', '快適'],
    [30, 'var(--color-temp-warm)',    '暖かい'],
    [Infinity, 'var(--color-temp-hot)', '暑い'],
];

/** 湿度ゾーン定義 [上限%, CSS カスタムプロパティ値] */
const HUM_ZONES = [
    [40,       'var(--color-hum-low)',     '乾燥'],
    [60,       'var(--color-hum-comfort)', '快適'],
    [Infinity, 'var(--color-hum-high)',    '多湿'],
];

// ============================================================
// DOM 参照
// ============================================================
const grid            = /** @type {HTMLElement} */ (document.getElementById('device-grid'));
const loading         = /** @type {HTMLElement} */ (document.getElementById('loading'));
const errorBanner     = /** @type {HTMLElement} */ (document.getElementById('error-banner'));
const noDevices       = /** @type {HTMLElement} */ (document.getElementById('no-devices'));
const btnRefresh      = /** @type {HTMLButtonElement} */ (document.getElementById('btn-refresh'));
const metaUpdated     = /** @type {HTMLElement} */ (document.getElementById('meta-updated'));
const btnSettings     = /** @type {HTMLButtonElement} */ (document.getElementById('btn-settings'));
const visibilityModal = /** @type {HTMLElement} */ (document.getElementById('visibility-modal'));
const visibilityList  = /** @type {HTMLElement} */ (document.getElementById('visibility-list'));
const modalClose      = /** @type {HTMLButtonElement} */ (document.getElementById('modal-close'));
const btnShowAll      = /** @type {HTMLButtonElement} */ (document.getElementById('modal-show-all'));
const btnHideAll      = /** @type {HTMLButtonElement} */ (document.getElementById('modal-hide-all'));

// ============================================================
// データ取得
// ============================================================

/**
 * /api/devices.php を呼び出してカードを描画する。
 * ボタン連打防止・ローディング制御・エラー表示を含む。
 */
async function fetchDevices() {
    setLoading(true);
    showError(null);
    btnRefresh.disabled = true;
    btnRefresh.classList.add('btn-refresh--spinning');

    try {
        const res = await fetch('./api/devices.php', { cache: 'no-store' });

        if (!res.ok) {
            throw new Error(`HTTP ${res.status}`);
        }

        const data = await res.json();

        if (!data.success) {
            showError(data.error ?? '不明なエラーが発生しました。');
            return;
        }

        renderDevices(data.devices ?? [], data.updatedAt ?? null);

    } catch (err) {
        showError('サーバーとの通信に失敗しました。ネットワーク接続を確認してください。');
        console.error('[dashboard]', err);
    } finally {
        setLoading(false);
        btnRefresh.disabled = false;
        btnRefresh.classList.remove('btn-refresh--spinning');
    }
}

// ============================================================
// 描画
// ============================================================

/**
 * 1デバイス分のカード `<article>` 要素を生成して返す。
 *
 * @param {Object}      device
 * @param {string|null} updatedAt
 * @returns {HTMLElement}
 */
function buildCard(device, updatedAt) {
    const isOnline = device.online === true && !device.error;
    const temp     = typeof device.temperature === 'number' ? device.temperature : null;
    const hum      = typeof device.humidity    === 'number' ? device.humidity    : null;
    const bat      = typeof device.battery     === 'number' ? device.battery     : null;

    const [tempColor] = resolveTempZone(temp);
    const [humColor]  = resolveHumZone(hum);

    // ── カード本体 ──────────────────────────────────────────
    const card = el('article', { className: 'device-card' });

    // 上部カラーバー
    const bar = el('div', { className: 'device-card__bar' });
    bar.style.setProperty('--bar-color', isOnline ? tempColor : 'var(--color-border)');
    card.appendChild(bar);

    // カードボディ
    const body = el('div', { className: 'device-card__body' });

    // ヘッダー（デバイス名 + ステータスバッジ）
    body.appendChild(buildCardHeader(device, isOnline));

    if (isOnline) {
        // メトリクス（温度・湿度）
        body.appendChild(buildMetrics(temp, hum, tempColor, humColor));

        // 湿度ゲージ
        if (hum !== null) {
            body.appendChild(buildHumidityGauge(hum, humColor));
        }

        // バッテリー
        if (bat !== null) {
            body.appendChild(buildBattery(bat));
        }
    } else {
        // オフライン or エラー時
        body.appendChild(buildOfflineMessage(device.error ?? null));
    }

    card.appendChild(body);

    // カードフッター（更新日時）
    card.appendChild(buildCardFooter(updatedAt));

    return card;
}

// ── カード部品ビルダー ──────────────────────────────────────

/**
 * カードヘッダー（デバイス名 + 種別 + バッジ）
 */
function buildCardHeader(device, isOnline) {
    const header = el('div', { className: 'device-card__header' });

    const nameBlock = el('div');
    const name = el('p', { className: 'device-card__name' });
    name.textContent = device.deviceName ?? '不明';
    const type = el('p', { className: 'device-card__type' });
    type.textContent = device.deviceType ?? '';
    nameBlock.appendChild(name);
    nameBlock.appendChild(type);

    const badge = el('span', {
        className: isOnline
            ? 'status-badge status-badge--online'
            : 'status-badge status-badge--offline',
    });
    badge.textContent = isOnline ? 'Online' : 'Offline';

    header.appendChild(nameBlock);
    header.appendChild(badge);
    return header;
}

/**
 * 温度・湿度メトリクス行
 */
function buildMetrics(temp, hum, tempColor, humColor) {
    const metrics = el('div', { className: 'device-card__metrics' });

    // 温度
    const tempMetric = el('div', { className: 'metric' });
    const tempLabel  = el('span', { className: 'metric__label' });
    tempLabel.textContent = '温度';
    const tempRow  = el('div', { className: 'metric__row' });
    const tempVal  = el('span', { className: 'metric__value' });
    tempVal.style.setProperty('--metric-color', tempColor);
    tempVal.textContent = temp !== null ? temp.toFixed(1) : '--';
    const tempUnit = el('span', { className: 'metric__unit' });
    tempUnit.textContent = '℃';
    tempRow.appendChild(tempVal);
    tempRow.appendChild(tempUnit);
    tempMetric.appendChild(tempLabel);
    tempMetric.appendChild(tempRow);
    metrics.appendChild(tempMetric);

    // 湿度
    const humMetric = el('div', { className: 'metric' });
    const humLabel  = el('span', { className: 'metric__label' });
    humLabel.textContent = '湿度';
    const humRow  = el('div', { className: 'metric__row' });
    const humVal  = el('span', { className: 'metric__value' });
    humVal.style.setProperty('--metric-color', humColor);
    humVal.textContent = hum !== null ? String(hum) : '--';
    const humUnit = el('span', { className: 'metric__unit' });
    humUnit.textContent = '%';
    humRow.appendChild(humVal);
    humRow.appendChild(humUnit);
    humMetric.appendChild(humLabel);
    humMetric.appendChild(humRow);
    metrics.appendChild(humMetric);

    return metrics;
}

/**
 * 湿度ゲージ
 */
function buildHumidityGauge(hum, humColor) {
    const [, , humLabel] = resolveHumZone(hum);

    const gauge  = el('div', { className: 'humidity-gauge' });
    const track  = el('div', { className: 'humidity-gauge__track' });
    const fill   = el('div', { className: 'humidity-gauge__fill' });
    // 幅は 0→100% にクランプ
    fill.style.width = Math.min(100, Math.max(0, hum)) + '%';
    fill.style.setProperty('--gauge-color', humColor);
    track.appendChild(fill);

    const labels = el('div', { className: 'humidity-gauge__labels' });
    const left   = el('span');
    left.textContent = '0%';
    const center = el('span');
    center.textContent = humLabel;
    const right  = el('span');
    right.textContent = '100%';
    labels.appendChild(left);
    labels.appendChild(center);
    labels.appendChild(right);

    gauge.appendChild(track);
    gauge.appendChild(labels);
    return gauge;
}

/**
 * バッテリーインジケーター
 */
function buildBattery(bat) {
    const batColor = bat > 30
        ? 'var(--color-battery-ok)'
        : bat > 10
            ? 'var(--color-battery-low)'
            : 'var(--color-battery-crit)';

    const wrapper = el('div', { className: 'battery' });
    wrapper.style.setProperty('--battery-color', batColor);

    // バッテリーアイコン
    const icon = el('span', { className: 'battery__icon', 'aria-hidden': 'true' });
    const barEl = el('span', { className: 'battery__bar' });
    const fillEl = el('span', { className: 'battery__fill' });
    fillEl.style.width = bat + '%';
    barEl.appendChild(fillEl);
    icon.appendChild(barEl);

    const level = el('span', { className: 'battery__level' });
    level.textContent = bat + '%';

    const labelEl = el('span');
    labelEl.textContent = 'バッテリー';
    labelEl.style.color = 'var(--color-text-muted)';

    wrapper.appendChild(icon);
    wrapper.appendChild(level);
    wrapper.appendChild(labelEl);
    return wrapper;
}

/**
 * オフライン / エラー時メッセージ
 */
function buildOfflineMessage(errorMsg) {
    const wrap = el('div', { className: 'metric metric--offline' });
    const row  = el('div', { className: 'metric__row' });
    const val  = el('span', { className: 'metric__value' });
    val.textContent = errorMsg ?? 'オフライン';
    row.appendChild(val);
    wrap.appendChild(row);
    return wrap;
}

/**
 * カードフッター（更新日時）
 */
function buildCardFooter(updatedAt) {
    const footer = el('div', { className: 'device-card__footer' });
    const icon   = el('span', { 'aria-hidden': 'true' });
    icon.textContent = '🕐';
    const text = el('span');
    text.textContent = updatedAt ? formatDateTime(updatedAt) : '---';
    footer.appendChild(icon);
    footer.appendChild(text);
    return footer;
}

// ============================================================
// ゾーン解決ヘルパー
// ============================================================

/**
 * 温度値からゾーン [color, label] を返す
 * @param {number|null} temp
 * @returns {[string, string]}
 */
function resolveTempZone(temp) {
    if (temp === null) {
        return ['var(--color-primary)', '---'];
    }
    for (const [limit, color, label] of TEMP_ZONES) {
        if (temp < limit) {
            return [color, label];
        }
    }
    return ['var(--color-temp-hot)', '暑い'];
}

/**
 * 湿度値からゾーン [color, label] を返す
 * @param {number|null} hum
 * @returns {[string, string, string]}
 */
function resolveHumZone(hum) {
    if (hum === null) {
        return ['var(--color-primary)', '---', '---'];
    }
    for (const [limit, color, label] of HUM_ZONES) {
        if (hum <= limit) {
            return [color, color, label];
        }
    }
    return ['var(--color-hum-high)', 'var(--color-hum-high)', '多湿'];
}

// ============================================================
// UI ヘルパー
// ============================================================

/** ローディング表示を切り替える */
function setLoading(visible) {
    loading.hidden = !visible;
    if (visible) {
        grid.innerHTML = '';
        noDevices.hidden = true;
    }
}

/**
 * エラーバナーを表示する。null を渡すと非表示。
 * @param {string|null} message
 */
function showError(message) {
    if (message) {
        errorBanner.textContent = '⚠ ' + message;
        errorBanner.hidden = false;
    } else {
        errorBanner.textContent = '';
        errorBanner.hidden = true;
    }
}

/**
 * ISO 8601 文字列をロケール形式に変換する
 * @param {string} iso
 * @returns {string}
 */
function formatDateTime(iso) {
    try {
        return new Date(iso).toLocaleString('ja-JP', {
            year: 'numeric', month: '2-digit', day: '2-digit',
            hour: '2-digit', minute: '2-digit', second: '2-digit',
        });
    } catch {
        return iso;
    }
}

/**
 * DOM 要素を生成するユーティリティ
 * @param {string} tag
 * @param {Object} [attrs]
 * @returns {HTMLElement}
 */
function el(tag, attrs = {}) {
    const node = document.createElement(tag);
    for (const [k, v] of Object.entries(attrs)) {
        if (k === 'className') {
            node.className = v;
        } else {
            node.setAttribute(k, v);
        }
    }
    return node;
}

// ============================================================
// 表示/非表示 管理
// ============================================================

/** localStorage のキー */
const STORAGE_KEY = 'switchbot_hidden_devices';

/**
 * 非表示デバイス ID の Set を localStorage から読み込む
 * @returns {Set<string>}
 */
function loadHiddenIds() {
    try {
        const raw = localStorage.getItem(STORAGE_KEY);
        if (!raw) {
            return new Set();
        }
        const parsed = JSON.parse(raw);
        return Array.isArray(parsed) ? new Set(parsed) : new Set();
    } catch {
        return new Set();
    }
}

/**
 * 非表示デバイス ID の Set を localStorage に保存する
 * @param {Set<string>} hiddenIds
 */
function saveHiddenIds(hiddenIds) {
    try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify([...hiddenIds]));
    } catch {
        // localStorage が使えない環境では無視
    }
}

/**
 * デバイスカードの表示状態をすべて更新する
 * hiddenIds に含まれる deviceId のカードを非表示にする。
 * すべて非表示になった場合はメッセージを表示する。
 *
 * @param {Set<string>} hiddenIds
 */
function applyVisibility(hiddenIds) {
    const cards = /** @type {NodeListOf<HTMLElement>} */ (
        grid.querySelectorAll('.device-card')
    );

    let visibleCount = 0;
    for (const card of cards) {
        const isHidden = hiddenIds.has(card.dataset.deviceId ?? '');
        card.classList.toggle('device-card--hidden', isHidden);
        if (!isHidden) {
            visibleCount++;
        }
    }

    // 「すべて非表示」メッセージの制御
    let noVisible = document.getElementById('no-visible-devices');
    if (visibleCount === 0 && cards.length > 0) {
        if (!noVisible) {
            noVisible = el('p', { id: 'no-visible-devices' });
            noVisible.innerHTML =
                '<span aria-hidden="true" style="display:block;font-size:2rem;margin-bottom:.5rem">👁</span>' +
                'すべてのデバイスが非表示になっています。<br>' +
                'ヘッダーの <strong>表示設定</strong> から表示するデバイスを選択してください。';
            grid.appendChild(noVisible);
        }
    } else if (noVisible) {
        noVisible.remove();
    }
}

// ============================================================
// モーダル制御
// ============================================================

/**
 * モーダルを開き、現在のデバイス一覧を元にトグルリストを描画する
 */
function openModal() {
    const cards = /** @type {NodeListOf<HTMLElement>} */ (
        grid.querySelectorAll('.device-card')
    );

    // カードが 0 件ならモーダルを開いても意味がない
    if (cards.length === 0) {
        return;
    }

    const hiddenIds = loadHiddenIds();
    visibilityList.innerHTML = '';

    const fragment = document.createDocumentFragment();
    for (const card of cards) {
        const deviceId   = card.dataset.deviceId   ?? '';
        const deviceName = card.dataset.deviceName ?? '不明';
        const deviceType = card.dataset.deviceType ?? '';
        const isVisible  = !hiddenIds.has(deviceId);

        fragment.appendChild(buildVisibilityItem(deviceId, deviceName, deviceType, isVisible));
    }
    visibilityList.appendChild(fragment);

    visibilityModal.hidden = false;
    // フォーカスを閉じるボタンへ移動（アクセシビリティ）
    modalClose.focus();
}

/** モーダルを閉じる */
function closeModal() {
    visibilityModal.hidden = true;
    // フォーカスを設定ボタンへ戻す
    btnSettings.focus();
}

/**
 * 表示トグルリストの 1 行 `<li>` を生成する
 *
 * @param {string}  deviceId
 * @param {string}  deviceName
 * @param {string}  deviceType
 * @param {boolean} isVisible
 * @returns {HTMLElement}
 */
function buildVisibilityItem(deviceId, deviceName, deviceType, isVisible) {
    const inputId = 'toggle-' + deviceId;

    const item = el('li', { class: 'visibility-item' });

    // デバイス情報
    const info = el('div', { class: 'visibility-item__info' });
    const name = el('p', { class: 'visibility-item__name' });
    name.textContent = deviceName;
    const type = el('p', { class: 'visibility-item__type' });
    type.textContent = deviceType;
    info.appendChild(name);
    info.appendChild(type);

    // トグルスイッチ
    const label = el('label', { class: 'toggle', for: inputId });
    const input = /** @type {HTMLInputElement} */ (
        el('input', {
            class: 'toggle__input',
            type:  'checkbox',
            id:    inputId,
            'aria-label': deviceName + ' の表示',
        })
    );
    input.checked = isVisible;

    // チェック変更時に即座に保存・適用
    input.addEventListener('change', () => {
        const ids = loadHiddenIds();
        if (input.checked) {
            ids.delete(deviceId);
        } else {
            ids.add(deviceId);
        }
        saveHiddenIds(ids);
        applyVisibility(ids);
    });

    const track = el('span', { class: 'toggle__track', 'aria-hidden': 'true' });
    label.appendChild(input);
    label.appendChild(track);

    item.appendChild(info);
    item.appendChild(label);
    return item;
}

/**
 * すべてのトグルを ON/OFF にしてすぐ反映する
 * @param {boolean} show  true=すべて表示 / false=すべて非表示
 */
function setAllVisibility(show) {
    const inputs = /** @type {NodeListOf<HTMLInputElement>} */ (
        visibilityList.querySelectorAll('.toggle__input')
    );
    const ids = loadHiddenIds();

    for (const input of inputs) {
        const deviceId = input.id.replace('toggle-', '');
        input.checked = show;
        if (show) {
            ids.delete(deviceId);
        } else {
            ids.add(deviceId);
        }
    }

    saveHiddenIds(ids);
    applyVisibility(ids);
}

// ============================================================
// renderDevices のラップ：カードに data 属性を付加して可視性を適用
// ============================================================

/**
 * デバイス配列を受け取り、グリッドにカードを描画する。
 * 描画後に保存済みの表示/非表示設定を適用する。
 *
 * @param {Array<Object>} devices
 * @param {string|null}   updatedAt  ISO 8601 文字列
 */
function renderDevices(devices, updatedAt) {
    // 既存カードをクリア
    grid.innerHTML = '';
    noDevices.hidden = true;

    // 更新日時をヘッダーに反映
    if (metaUpdated && updatedAt) {
        metaUpdated.textContent = '最終更新: ' + formatDateTime(updatedAt);
    }

    if (devices.length === 0) {
        noDevices.hidden = false;
        return;
    }

    const fragment = document.createDocumentFragment();
    for (const device of devices) {
        const card = buildCard(device, updatedAt);
        // 表示/非表示フィルタリング用の data 属性を付与
        card.dataset.deviceId   = device.deviceId   ?? '';
        card.dataset.deviceName = device.deviceName ?? '';
        card.dataset.deviceType = device.deviceType ?? '';
        fragment.appendChild(card);
    }
    grid.appendChild(fragment);

    // 保存済みの表示設定を即座に適用
    applyVisibility(loadHiddenIds());
}

// ============================================================
// イベント & 初期ロード
// ============================================================

// 更新ボタン
btnRefresh.addEventListener('click', fetchDevices);

// 設定ボタン → モーダルを開く
btnSettings.addEventListener('click', openModal);

// 閉じるボタン
modalClose.addEventListener('click', closeModal);

// バックドロップクリックで閉じる
visibilityModal.addEventListener('click', (e) => {
    if (e.target === visibilityModal) {
        closeModal();
    }
});

// Escape キーで閉じる
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && !visibilityModal.hidden) {
        closeModal();
    }
});

// すべて表示 / すべて非表示
btnShowAll.addEventListener('click', () => setAllVisibility(true));
btnHideAll.addEventListener('click', () => setAllVisibility(false));

// 初期ロード
fetchDevices();
