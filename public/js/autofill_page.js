/*
    郵便番号・住所検索(autofill_page.js)
    住所の自動入力デモ（/autofill）で読み込む
    郵便番号を入力すると、JSON API（api/address）で住所を調べ、住所の欄を埋める
 */

import {normalizePostCode, isValidPostCode} from "./postcode.js";   // 郵便番号の正規化・書式チェック（住所検索と共通）

/**
 * 住所の JSON API の URL（相対パスなので、<base href> の場所から解決される）
 */
const API_ADDRESS = 'api/address';

/**
 * 郵便番号から住所の候補を取得する
 * @param {string} zip 正規化済みの郵便番号
 * @param {AbortSignal} signal 取り消し用のシグナル（新しい入力があったら、前の通信を取り消す）
 * @return {Promise<Array<{zip: string, prefecture: string, municipality: string, town_area: string, full_address: string}>>}
 * @throws {Error} 通信エラー、またはサーバー側でエラーが起きたとき（取り消したときは AbortError）
 */
const fetchAddresses = async (zip, signal) => {
    const response = await fetch(`${API_ADDRESS}?${new URLSearchParams({zip})}`, {signal});
    const data = await response.json();
    if (!response.ok) {
        throw new Error(data.error_msg ?? `住所の取得に失敗しました（HTTP ${response.status}）`);
    }
    return data.addresses;
};

/**
 * 住所の自動入力を設定する
 */
const setupAutofill = () => {
    const form = document.getElementById('autofill-form');
    const zipInput = document.getElementById('zip');
    const status = document.getElementById('autofill-status');
    const candidates = document.getElementById('autofill-candidates');
    const fields = {
        prefecture: document.getElementById('prefecture'),
        municipality: document.getElementById('municipality'),
        town_area: document.getElementById('town_area'),
    };
    const streetInput = document.getElementById('street');

    // 実行中の通信（新しい入力があったら、古い通信を取り消して、古い結果で上書きされないようにする）
    // 郵便番号検索の画面（municipality.js）では番号で古い結果を捨てているが、ここでは AbortController で通信そのものを止める
    let controller = null;
    let lastZip = '';

    /** 住所の欄を埋めて、続きの「番地・建物名」に入力できるようにする */
    const fill = (address) => {
        for (const [name, input] of Object.entries(fields)) {
            input.value = address[name];
        }
        candidates.replaceChildren();
        candidates.hidden = true;
        status.textContent = `${address.full_address} を入力しました。続けて番地を入力してください。`;
        streetInput.focus();
    };

    /** 候補が複数あるときは、選べるようにボタンを並べる */
    const showCandidates = (addresses) => {
        const items = addresses.map((address) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.textContent = address.full_address;     // textContent なので、HTML として解釈されない
            button.addEventListener('click', () => fill(address));
            const item = document.createElement('li');
            item.append(button);
            return item;
        });
        candidates.replaceChildren(...items);
        candidates.hidden = false;
        status.textContent = `${addresses.length} 件の候補があります。選んでください。`;
    };

    const lookUp = async () => {
        const zip = normalizePostCode(zipInput.value);
        if (zip.replace('-', '') === lastZip.replace('-', '')) {
            return;     // 同じ郵便番号（ハイフンの有無だけが違う場合も含む）なら、もう一度調べない
        }
        lastZip = zip;

        controller?.abort();
        candidates.replaceChildren();
        candidates.hidden = true;

        if (!isValidPostCode(zip)) {
            status.textContent = '';
            return;     // 7桁そろうまでは調べない
        }

        controller = new AbortController();
        status.textContent = '検索中…';
        try {
            const addresses = await fetchAddresses(zip, controller.signal);
            if (addresses.length === 0) {
                status.textContent = 'この郵便番号の住所は見つかりませんでした。';
            } else if (addresses.length === 1) {
                fill(addresses[0]);
            } else {
                showCandidates(addresses);
            }
        } catch (error) {
            if (error.name === 'AbortError') {
                return;     // 新しい入力で取り消した通信なので、何もしない
            }
            console.error(error);   // 詳細は開発者ツールのコンソールに出す
            lastZip = '';           // 同じ郵便番号で、もう一度調べられるようにする
            status.textContent = '住所の取得に失敗しました。時間をおいて再度お試しください。';
        }
    };

    // 1文字入力するたびに調べる（日本語入力の変換中は、確定するまで待つ）
    zipInput.addEventListener('input', (event) => {
        if (!event.isComposing) {
            lookUp();
        }
    });
    zipInput.addEventListener('compositionend', lookUp);

    // デモなので、Enter キーなどで送信されないようにする
    form.addEventListener('submit', (event) => event.preventDefault());
};

document.addEventListener('DOMContentLoaded', setupAutofill);
