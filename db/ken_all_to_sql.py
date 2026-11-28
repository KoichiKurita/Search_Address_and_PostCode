#!/usr/bin/env python3
"""
日本郵便の郵便番号データ（UTF-8版 utf_ken_all.csv）を、post_codes テーブルに取り込める形に変換する。
注記（「以下に掲載がない場合」など）の除去や、列の長さの確認もここで行う。

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

# 出力する列（DB の列名）と、その最大文字数（db/schema.sql と合わせる）
# これを超える値があれば、何も出力せずに止める（DB の列より長いと、取り込みに失敗するか、黙って切り詰められるため）
COLUMNS = [
    ('post_code_noHyphen', 7),
    ('prefecture', 10),
    ('municipality', 50),
    ('town_area', 1000),
    ('prefecture_kana', 20),
    ('municipality_kana', 100),
    ('town_area_kana', 2000),
]

# 町域欄に入る「住所ではない注記」は空文字として扱う
NO_TOWN_NOTES = ('以下に掲載がない場合',)


def sql_str(value: str) -> str:
    """SQLの文字列リテラルにする（' と \\ をエスケープ）"""
    return "'" + value.replace('\\', '\\\\').replace("'", "''") + "'"


def is_town_note(town: str, municipality: str) -> bool:
    """町域が「住所ではない注記」か（例:「以下に掲載がない場合」「○○の次に番地がくる場合」「○○一円」）"""
    return (town in NO_TOWN_NOTES
            or town.endswith('の次に番地がくる場合')
            or town == municipality + '一円')


def read_rows(csv_path: str):
    """
    utf_ken_all.csv を読み、[郵便番号7桁, 都道府県, 市区町村, 町域, 都道府県カナ, 市区町村カナ, 町域カナ] を返す
    （古い形式のデータで、町域が複数行に分割されているレコードは結合する）
    """
    with open(csv_path, encoding='utf-8', newline='') as f:
        reader = csv.reader(f)
        pending = None
        for cols in reader:
            if len(cols) < 9:
                continue
            row = [cols[2], cols[6], cols[7], cols[8], cols[3], cols[4], cols[5]]

            if pending is not None:
                # 括弧が閉じるまで、同じ郵便番号の行の町域（とそのカナ）をつなげる
                if pending[0] == row[0]:
                    pending[3] += row[3]
                    pending[6] += row[6]
                    if '）' in row[3]:
                        yield pending
                        pending = None
                    continue
                # 想定外に括弧が閉じないまま次の郵便番号に進んだ場合はそのまま出力する
                yield pending
                pending = None

            if '（' in row[3] and '）' not in row[3]:
                pending = row
                continue
            yield row

        if pending is not None:
            yield pending


def write_csv(records, out_path: str) -> None:
    """bin/import-postcodes.php で取り込む CSV を出力する（見出し行なし。列は COLUMNS の順）"""
    with open(out_path, 'w', encoding='utf-8', newline='') as out:
        writer = csv.writer(out, lineterminator='\n')
        writer.writerows(records)
    print(f'{len(records)} 件を出力しました: {out_path}')


def write_sql(records, out_dir: str) -> None:
    """phpMyAdmin で取り込む INSERT 文を、ROWS_PER_FILE 行ずつのファイルに分けて出力する"""
    os.makedirs(out_dir, exist_ok=True)
    columns = 'post_code, ' + ', '.join(name for name, _ in COLUMNS)
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
                    '(' + ','.join(sql_str(v) for v in [f'{row[0][:3]}-{row[0][3:]}', *row]) + ')'
                    for row in chunk[i:i + ROWS_PER_INSERT]
                )
                out.write(f'INSERT INTO post_codes ({columns}) VALUES\n' + values + ';\n')
    print(f'{len(records)} 件を {file_no} ファイルに出力しました: {out_dir}')


def main():
    parser = argparse.ArgumentParser(description='日本郵便の utf_ken_all.csv を、post_codes テーブルに取り込める形に変換する')
    parser.add_argument('csv_path', help='日本郵便の utf_ken_all.csv')
    parser.add_argument('out', help='出力先（--format sql: フォルダ / --format csv: ファイル）')
    parser.add_argument('--format', choices=['sql', 'csv'], default='sql',
                        help='sql: phpMyAdmin 用の INSERT 文（初期値） / csv: bin/import-postcodes.php 用の CSV')
    args = parser.parse_args()

    records = []
    for row in read_rows(args.csv_path):
        if is_town_note(row[3], row[2]):
            row[3] = ''     # 町域
            row[6] = ''     # 町域カナ（注記の読み「イカニケイサイガナイバアイ」なども消す）
        records.append(row)

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

    if args.format == 'csv':
        write_csv(records, args.out)
    else:
        write_sql(records, args.out)


if __name__ == '__main__':
    main()
