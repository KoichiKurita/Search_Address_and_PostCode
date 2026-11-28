#!/usr/bin/env python3
"""
日本郵便の「事業所の個別郵便番号」データ（jigyosyo.csv）を、offices テーブルに取り込める CSV に変換する（#36）。

使い方:
    1. https://www.post.japanpost.jp/service/search/zipcode/download/office/index-zip.html から
       jigyosyo.zip をダウンロードして展開
    2. python3 jigyosyo_to_csv.py jigyosyo.csv offices.csv
    3. php bin/import-postcodes.php --offices offices.csv

住所の郵便番号データ（utf_ken_all.csv）とは、次の点が違う。
    - 文字コードが Shift_JIS（Windows の拡張文字を含むので cp932 で読む）
    - 事業所名のフリガナが半角カタカナ → 全角カタカナにそろえる（住所のフリガナと同じ形にするため）
    - 列の並びが違う（事業所名・番地がある）

標準ライブラリのみで動作します（Python 3.8 以降）。
"""
import argparse
import csv
import sys
import unicodedata

# 出力する列（DB の列名）と、その最大文字数（db/schema.sql の offices と合わせる）
COLUMNS = [
    ('post_code_noHyphen', 7),
    ('name', 100),
    ('name_kana', 100),
    ('prefecture', 10),
    ('municipality', 50),
    ('town_area', 50),
    ('street', 100),
    ('is_po_box', 1),
]

# 修正コード（13列目）: 0 変更なし / 1 新規追加 / 5 廃止
DISCONTINUED = '5'


def read_rows(csv_path: str):
    """jigyosyo.csv を読み、COLUMNS の順の値を返す（廃止の行は除く）"""
    with open(csv_path, encoding='cp932', newline='') as f:
        for cols in csv.reader(f):
            if len(cols) < 13 or cols[12] == DISCONTINUED:
                continue
            yield [
                cols[7],                                     # 大口事業所個別番号（7桁）
                cols[2].strip(),                             # 事業所名
                unicodedata.normalize('NFKC', cols[1]).strip(),  # フリガナ（半角カナ → 全角カナ。ｶﾞ → ガ のように濁点もまとめる）
                cols[3], cols[4], cols[5],                   # 都道府県・市区町村・町域
                cols[6].strip(),                             # 小字・丁目・番地など
                cols[10],                                    # 個別番号の種別（0: 大口事業所 / 1: 私書箱）
            ]


def main():
    parser = argparse.ArgumentParser(description='日本郵便の jigyosyo.csv を、offices テーブルに取り込める CSV に変換する')
    parser.add_argument('csv_path', help='日本郵便の jigyosyo.csv（Shift_JIS）')
    parser.add_argument('out', help='出力する CSV（UTF-8。見出し行なし）')
    args = parser.parse_args()

    records = list(read_rows(args.csv_path))
    if not records:
        print('データがありません。', file=sys.stderr)
        sys.exit(1)

    # 列ごとに最長の値を表示し、DB の列より長いものがあれば止める
    too_long = False
    for index, (name, max_length) in enumerate(COLUMNS):
        longest = max(records, key=lambda r: len(r[index]))
        print(f'{name} の最長: {len(longest[index])} 文字（{longest[0]}）／ 列の上限: {max_length} 文字')
        for r in records:
            if len(r[index]) > max_length:
                print(f'  長すぎる {name}: {len(r[index])} 文字 {r[0]} {r[index][:30]}…', file=sys.stderr)
                too_long = True
    if too_long:
        print('db/schema.sql の列の長さと COLUMNS を広げてください。何も出力していません。', file=sys.stderr)
        sys.exit(1)

    with open(args.out, 'w', encoding='utf-8', newline='') as out:
        csv.writer(out, lineterminator='\n').writerows(records)
    print(f'{len(records)} 件を出力しました: {args.out}')


if __name__ == '__main__':
    main()
