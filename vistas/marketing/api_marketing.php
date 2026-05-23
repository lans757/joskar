<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();
if (!has_module_access('MARKETING')) {
    http_response_code(403);
    echo json_encode(['error' => 'Access Denied']);
    exit;
}

$action = $_GET['action'] ?? '';
$year = $_GET['year'] ?? date('Y');
$month = str_pad($_GET['month'] ?? date('m'), 2, '0', STR_PAD_LEFT);
$periodo = "$year-$month-01";
$prev_period = date('Y-m-01', strtotime("-1 month", strtotime($periodo)));

header('Content-Type: application/json');

if ($action === 'get_dashboard') {
    // Current month
    $stmt = $pdo->prepare("SELECT * FROM indicadores_marketing WHERE periodo = ?");
    $stmt->execute([$periodo]);
    $current = $stmt->fetch();

    // Previous month
    $stmt->execute([$prev_period]);
    $prev = $stmt->fetch();

    if ($current) {
        $videos = json_decode($current['videos'] ?? '[]', true) ?: [];
        $campanas = json_decode($current['campanas'] ?? '[]', true) ?: [];
    } else {
        $videos = [];
        $campanas = [];
    }

    echo json_encode([
        'metrics' => $current ?: null,
        'prev_metrics' => $prev ?: null,
        'videos' => $videos,
        'campanas' => $campanas
    ]);
    exit;
}

echo json_encode(['error' => 'Invalid action']);
