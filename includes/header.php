<?php
if (session_status() === PHP_SESSION_NONE) session_start();

if (empty($_SESSION['logged_in'])) {
    if (!isset($path_prefix)) $path_prefix = "";
    header('Location: ' . $path_prefix . 'index.php');
    exit;
}

// Default values if not set
if (!isset($pageTitle)) $pageTitle = "Dashboard Droguería Joskar | Sistema";
if (!isset($path_prefix)) $path_prefix = "";

// Validación de red local
require_once $path_prefix . 'includes/lan_check.php';

$cssVersion = filemtime(dirname(__DIR__) . '/assets/css/style.css');
?>
<!DOCTYPE html>
<html lang='es'>
<head>
    <meta charset='UTF-8'>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link rel='stylesheet' href='https://fonts.googleapis.com/css2?family=Inter:wght@400;600&family=Outfit:wght@700;800&display=swap'>
    <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css'>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel='stylesheet' href='<?php echo $path_prefix; ?>assets/css/style.css?v=<?php echo $cssVersion; ?>'>
    <script>const ROOT_PATH = '<?php echo $path_prefix; ?>';</script>
    <?php if (isset($extraStyles)) echo $extraStyles; ?>
</head>
<body>
    <div class='app-container'>
