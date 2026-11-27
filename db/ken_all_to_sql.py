#!/usr/bin/env python3
"""
日本郵便の郵便番号データ（UTF-8版 utf_ken_all.csv）を、post_codes テーブルに取り込める形に変換する。
注記（「以下に掲載がない場合」など）の除去や、町域の長さの確認もここで行う。

使い方:
    1. https://www.post.japanpost.jp/service/search/zipcode/download/utf-zip.html から
       「住所の郵便番号（1レコード1行、UTF-8形式）」の utf_ken_all.zip をダウンロードして展開
    2-a. 月次の自動更新・make seed-full（bin/import-postcodes.php で取り込む CSV を1つ出力する）
           python3 ken_all_to_sql.py utf_ken_all.csv post_codes.csv --format csv
    2-b. phpMyAdmin で手作業で取り込む場合（INSERT 文を、アップロードできる大きさに分けて出力する）
           python3 ken_all_to_sql.py utf_ken_all.csv out
         out/post_codes_001.sql から順に、phpMyAdmin の「インポート」で取り込む

標準ライブラリのみで動作します（Python 3.8 以降）。
"""
import argparse
import csv
import os
import sys

ROWS_PER_FILE = 10000     # 1ファイルあたりの行数（約1.2MB。phpMyAdminのアップロード上限に収まる大きさ）
ROWS_PER_INSERT = 500     # 1つのINSERT文に含める行数

# post_codes.town_area 列の長さ（db/schema.sql と合わせる）。これを超える町域があれば、SQL を出力せずに止める
TOWN_AREA_MAX_LENGTH = 1000

# 町域欄に入る「住所ではない注記」は空文字として扱う
NO_TOWN_NOTES = ('以下に掲載がない場合',)


def sql_str(value: str) -> str:
    """SQLの文字列リテラルにする（' と \\ をエスケープ）"""
    return "'" + value.replace('\\', '\\\\').replace("'", "''") + "'"


def normalize_town(town: str, municipality: str) -> str:
    if town in NO_TOWN_NOTES:
        return ''
    # 例:「○○の次に番地がくる場合」「○○一円」は町域としては空にする
    if town.endswith('の次に番地がくる場合') or town == municipality + '一円':
        return ''
    return town


def read_rows(csv_path: str):
    """utf_ken_all.csv を読み、町域が複数行に分割されているレコードを結合して返す"""
    with open(csv_path, encoding='utf-8', newline='') as f:
        reader = csv.reader(f)
        pending = None
        for cols in reader:
            if len(cols) < 9:
                continue
            zip7, pref, muni, town = cols[2], cols[6], cols[7], cols[8]

            if pending is not None:
                # 括弧が閉じるまで同じ郵便番号の行の町域をつなげる
                if pending[0] == zip7:
                    pending[3] += town
                    if '）' in town:
                        yield tuple(pending)
                        pending = None
                    continue
                # 想定外に括弧が閉じないまま次の郵便番号に進んだ場合はそのまま出力する
                yield tuple(pending)
                pending = None

            if '（' in town and '）' not in town:
                pending = [zip7, pref, muni, town]
                continue
            yield (zip7, pref, muni, town)

        if pending is not None:
            yield tuple(pending)


def write_csv(records, out_path: str) -> None:
    """bin/import-postcodes.php で取り込む CSV を出力する（見出し行なし。列: 郵便番号7桁, 都道府県, 市区町村, 町域）"""
    with open(out_path, 'w', encoding='utf-8', newline='') as out:
        writer = csv.writer(out, lineterminator='\n')
        for _post_code, zip7, pref, muni, town in records:
            writer.writerow([zip7, pref, muni, town])
    print(f'{len(records)} 件を出力しました: {out_path}')


def main():
    parser = argparse.ArgumentParser(description='日本郵便の utf_ken_all.csv を、post_codes テーブルに取り込める形に変換する')
    parser.add_argument('csv_path', help='日本郵便の utf_ken_all.csv')
    parser.add_argument('out', help='出力先（--format sql: フォルダ / --format csv: ファイル）')
    parser.add_argument('--format', choices=['sql', 'csv'], default='sql',
                        help='sql: phpMyAdmin 用の INSERT 文（初期値） / csv: bin/import-postcodes.php 用の CSV')
    args = parser.parse_args()
    csv_path, out_dir = args.csv_path, args.out

    records = []
    for zip7, pref, muni, town in read_rows(csv_path):
        town = normalize_town(town, muni)
        records.append((f'{zip7[:3]}-{zip7[3:]}', zip7, pref, muni, town))

    # 町域の長さを確認する（DB の列より長いと、取り込みの途中でエラーになり、途中までしか入らないため）
    longest = max(records, key=lambda r: len(r[4]))
    print(f'町域の最長: {len(longest[4])} 文字（{longest[0]} {longest[2]}{longest[3]}）／ 列の上限: {TOWN_AREA_MAX_LENGTH} 文字')
    too_long = [r for r in records if len(r[4]) > TOWN_AREA_MAX_LENGTH]
    if too_long:
        for r in too_long:
            print(f'  長すぎる町域: {len(r[4])} 文字 {r[0]} {r[2]}{r[3]} {r[4][:30]}…', file=sys.stderr)
        print('db/schema.sql の town_area の長さと TOWN_AREA_MAX_LENGTH を広げてください。SQL は出力していません。', file=sys.stderr)
        sys.exit(1)
    
    if args.format == 'csv':
        write_csv(records, args.out)
        return

    os.makedirs(out_dir, exist_ok=True)
    
    file_no = 0
    for start in range(0, len(records), ROWS_PER_FILE):
        file_no += 1
        chunk = records[start:start + ROWS_PER_FILE]
        path = os.path.join(out_dir, f'post_codes_{file_no:03d}.sql')
        with open(path, 'w', encoding='utf-8', newline='\n') as out:
            out.write('SET NAMES utf8mb4;\n')
            if file_no == 1:
                out.write('TRUNCATE TABLE post_codes;\n')
            for i in range(0, len(chunk), ROWS_PER_INSERT):
                values = ',\n'.join(
                    '(' + ','.join(sql_str(v) for v in row) + ')'
                    for row in chunk[i:i + ROWS_PER_INSERT]
                )
                out.write('INSERT INTO post_codes '
                          '(post_code, post_code_noHyphen, prefecture, municipality, town_area) VALUES\n'
                          + values + ';\n')

    print(f'{len(records)} 件を {file_no} ファイルに出力しました: {out_dir}')


if __name__ == '__main__':
    main()
