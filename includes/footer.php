<?php
if (!isset($path_prefix)) $path_prefix = "";
?>
    </div><!-- .app-container -->
    
    <script src='<?php echo $path_prefix; ?>app.js?v=<?php echo filemtime(dirname(__DIR__) . '/app.js'); ?>'></script>
    <?php if (isset($extraScripts)) echo $extraScripts; ?>
</body>
</html>
