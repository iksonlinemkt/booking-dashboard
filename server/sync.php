<?php
require_once __DIR__ . '/db_config.php';

define('GAS_SOURCE_URL', 'https://script.google.com/macros/s/AKfycby9fgv7_yKRfKzu1N0La3Mw0yOpazrn0BsasyGqettV1ODEghFuSGRq9NyKXnEcQiGb4Q/exec');
define('COOLDOWN_SEC', 60);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://iksonlinemkt.github.io');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Cache-Control: no-store');
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') { exit; }

function out($arr, $code = 200) { http_response_code($code); echo json_encode($arr, JSON_UNESCAPED_UNICODE); exit; }

set_time_limit(180);
$tmp   = sys_get_temp_dir();
$lockF = fopen($tmp . '/iks_sync.lock', 'c');
if (!$lockF || !flock($lockF, LOCK_EX | LOCK_NB)) {
    out(['ok' => false, 'busy' => true, 'message' => 'มีการอัปเดตกำลังทำงานอยู่ กรุณารอสักครู่']);
}
$stamp = $tmp . '/iks_sync.stamp';
if (file_exists($stamp) && (time() - filemtime($stamp)) < COOLDOWN_SEC) {
    $wait = COOLDOWN_SEC - (time() - filemtime($stamp));
    out(['ok' => false, 'cooldown' => true, 'wait' => $wait, 'message' => "เพิ่งอัปเดตไป กรุณารออีก $wait วินาที"]);
}

try {
    $ctx = stream_context_create(['http' => ['timeout' => 120]]);
    $raw = @file_get_contents(GAS_SOURCE_URL . '?type=all', false, $ctx);
    if ($raw === false) throw new Exception('ดึงข้อมูลจาก Google Sheet ไม่สำเร็จ');
    $data = json_decode($raw, true);
    if (!is_array($data) || isset($data['error'])) throw new Exception('Google Sheet ตอบกลับผิดรูปแบบ');
    $bk = $data['bk'] ?? []; $rs = $data['rs'] ?? []; $sc = $data['sc'] ?? [];
    if (count($bk) === 0 && count($rs) === 0) throw new Exception('ไม่พบข้อมูลใน Sheet จึงไม่เปลี่ยนแปลงอะไร (ป้องกันข้อมูลหาย)');

    $pdo = getDB();
    $pdo->beginTransaction();

    $pdo->exec('DELETE FROM bookings');
    $st = $pdo->prepare("INSERT INTO bookings (booking_date, branch, sc_name, model_group, model_type, occupation, purchase_type, contact_source, status, sc_code, finance, model_code) VALUES (?,?,?,?,?,?,?,?,?,?,?,'')");
    $nBk = 0;
    foreach ($bk as $r) { if (empty($r[0])) continue;
        $st->execute([$r[0],$r[1]??'',$r[2]??'',$r[3]??'',$r[4]??'',$r[5]??'',$r[6]??'',$r[7]??'',$r[8]??'',$r[9]??'',$r[10]??'']); $nBk++; }

    $pdo->exec('DELETE FROM retail_sales');
    $st = $pdo->prepare("INSERT INTO retail_sales (sale_date, branch, sc_name, model_group, model_type, customer, bill_to, so_no, finance, model_code) VALUES (?,?,?,?,?,?,?,?,?,'')");
    $nRs = 0;
    foreach ($rs as $r) { if (empty($r[0])) continue;
        $st->execute([$r[0],$r[1]??'',$r[2]??'',$r[3]??'',$r[4]??'',$r[5]??'',$r[6]??'',$r[7]??'',$r[8]??'']); $nRs++; }

    $pdo->exec('DELETE FROM sc_employees');
    $st = $pdo->prepare('INSERT INTO sc_employees (name, code, branch, team) VALUES (?,?,?,?)');
    $nSc = 0;
    foreach ($sc as $r) { if (empty($r[0])) continue;
        $st->execute([$r[0],$r[1]??'',$r[2]??'',$r[3]??'']); $nSc++; }

    $pdo->commit();
    touch($stamp);
    out(['ok' => true, 'bk' => $nBk, 'rs' => $nRs, 'sc' => $nSc]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    out(['ok' => false, 'message' => $e->getMessage()], 500);
}
