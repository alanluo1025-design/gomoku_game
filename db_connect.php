<?php
// db_connect.php
// 設定台灣時區
date_default_timezone_set('Asia/Taipei');

// 啟動 Session（因為你的功能幾乎都要檢查登入狀態，寫在這裡就不用每個檔案都重複寫 session_start 了）
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$mysqli = new mysqli("sql311.infinityfree.com", "if0_42226581", "lnaiLsTjRBoNbW", "if0_42226581_gomoku_game");

if ($mysqli->connect_errno) {
    exit("資料庫連線失敗: " . $mysqli->connect_error);
}

// 設定編碼，確保中文字暱稱或聊天內容不會變成亂碼
$mysqli->set_charset("utf8mb4");

// 設定 MySQL 時區為台灣時間
$mysqli->query("SET time_zone = '+08:00'"); 
