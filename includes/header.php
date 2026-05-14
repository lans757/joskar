<?php
// Default values if not set
if (!isset($pageTitle)) $pageTitle = "ProteoERP | Dashboard";
if (!isset($path_prefix)) $path_prefix = "";
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
    <link rel='stylesheet' href='<?php echo $path_prefix; ?>assets/css/style.css?v=<?php echo $cssVersion; ?>'>
    <script src='<?php echo $path_prefix; ?>assets/js/error-reporter.js'></script>
    <?php if (isset($extraStyles)) echo $extraStyles; ?>
</head>
<body>
    <div class='app-container'>
