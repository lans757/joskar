<?php
$_env_file = __DIR__ . '/.env';
$_env = parse_ini_file($_env_file);
print_r($_env);
