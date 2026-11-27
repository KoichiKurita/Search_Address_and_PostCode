/*
    郵便番号・住所検索(municipality.js)
    都道府県のプルダウンに合わせて、市区町村のプルダウンを作り直す
    郵便番号検索の画面（/postcode）で使う
 */

/**
 * 市区町村の一覧を返す API の URL
 * 相対パスなので、アプリを置いている場所（<base href>）が変わっても書き換えなくてよい
 * （以前は環境ごとに config.js で設定していた）
 */
const API_MUNICIPALITIES = 'api/municipalities';

/**
 * 都道府県に対応する市区町村の一覧を、API から取得する
 * @param {string} prefecture 都道府県名
 * @return {Promise<string[]>} 市区町村名の配列
 * @throws {Error} 通信エラー、またはサーバー側でエラーが起きたとき
 */
export const fetchMunicipalities = async (prefecture) => {
    const response = await fetch(API_MUNICIPALITIES, {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(prefecture),
    });

    // fetch は HTTP 500 などでも例外にならないため、ステータスを自分で確認する
    if (!response.ok) {
        throw new Error(`市区町村の取得に失敗しました（HTTP ${response.status}）`);
    }

    const data = await response.json();

    // 成功時は配列、失敗時は {error_msg: "..."} が返ってくる
    if (!Array.isArray(data)) {
        throw new Error(data.error_msg ?? '市区町村の取得に失敗しました');
    }
    return data;
};

/**
 * プルダウンの選択肢を作り直す（先頭は「選択してください」）
 * @param {HTMLSelectElement} select 対象のプルダウン
 * @param {string[]} names 選択肢にする名前の配列
 * @param {string} selectedName 最初から選んでおく名前（無ければ空文字）
 */
const replaceOptions = (select, names, selectedName = '') => {
    const options = names.map((name) => new Option(name, name, false, name === selectedName));
    // replaceChildren で、古い選択肢を消して新しい選択肢に一度で入れ替える
    select.replaceChildren(new Option('選択してください', ''), ...options);
};

/**
 * 都道府県と市区町村のプルダウンを連動させる
 * @param {string} initialMunicipality 最初に選んでおく市区町村（検索結果画面で、検索条件を残すときに使う）
 */
export const setupMunicipalitySelect = async (initialMunicipality = '') => {
    const prefectureSelect = document.getElementById('prefecture');
    const municipalitySelect = document.getElementById('municipality');
    const errorMessage = document.getElementById('error-message');

    // 都道府県をすばやく切り替えたとき、先に送った古いリクエストの結果で上書きされないようにする番号
    let latestRequest = 0;

    const load = async (selectedName) => {
        const requestId = ++latestRequest;

        replaceOptions(municipalitySelect, []);
        municipalitySelect.disabled = true;     // 取得中・未選択のあいだは選べないようにする

        if (prefectureSelect.value === '') {
            return;
        }

        try {
            const names = await fetchMunicipalities(prefectureSelect.value);
            if (requestId !== latestRequest) {
                return;     // より新しいリクエストがあるので、この結果は捨てる
            }
            replaceOptions(municipalitySelect, names, selectedName);
            municipalitySelect.disabled = false;
        } catch (error) {
            console.error(error);   // 詳細は開発者ツールのコンソールに出す
            errorMessage.textContent = '市区町村の取得に失敗しました。時間をおいて再度お試しください。';
        }
    };

    // 都道府県を選び直したら、前のエラーメッセージを消してから読み込む
    // （画面を開いたときには消さない。サーバーが表示した入力エラーのメッセージを残すため）
    prefectureSelect.addEventListener('change', () => {
        errorMessage.textContent = '';
        load('');
    });

    // 画面を開いた時点で都道府県が選ばれていれば（検索結果画面）、市区町村を読み込む
    await load(initialMunicipality);
};
