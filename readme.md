# TUT バス時刻BOT for VUI

## 概要

　本プログラムは、バス時刻表APIで返される情報を基にVUIでの情報取得を可能にするスキルと、それに用いるモジュールです。
　現在はAmazon Alexaにのみ対応していますが、少ないコストで他のVUIにも転用可能です。

## 動作環境
	- PHP
		- 作者は7.2を仕様。他のバージョンでは未検証。
		- phpの追加モジュールとして、mbstring・json・opensslが必要。
		- php.iniにて、date.timezoneおよびopenssl.cafileを適切に設定しておく必要がある。
	- webサーバ
		- 信頼された証明機関が発行した証明書を用いてHTTPSでのリクエストに正しく応答すること

## 初期設定
	- setting-example.phpを参考に、setting.phpというファイル名で設定値を保存する


## ファイル構造
	- mod_TUT-busVUI
		- 時刻表情報の取得や音声応答文字列の生成を行うモジュール
		- どのプラットフォームでも使用できる
	- logo
		- スキルのロゴ画像
	- alexa-sdk-master
		- Sven Wagener氏が作成し、GPLv3の下で公開しているオープンソースのモジュール
		- Amazon lexaとのやり取りを補助してくれる
		- skill.phpから読み込んで使用している
		- 一部、オリジナルから手を加えてカスタマイズしてある
	- skill.json
		- Amazon Alexaとの会話パターンの定義ファイル
		- このファイルの内容をAlexa developperConsoleのjsonEditorに入力することですぐにスキルを設定できる
	- skill.php
		- Amazon Alexaから呼び出されるエンドポイント
	- readme.md
		- 本文書
	- setting-example.php
		- 設定ファイルの例


## 注意事項
	- Amazonからのリクエストの処理中には、セキュリティ上脆弱な箇所がある。

## 更新履歴
	- 2019/09/11
		- 初版公開


## 著作権・ライセンス
	- (c) 2019 Tago laboratry.
	- 本プログラムはGPLv3の下でだれでも利用可能です。







