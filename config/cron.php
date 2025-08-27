<?php
// app/Config/cron.php
if (!defined('CRON_TOKEN')) {
    define('CRON_TOKEN', getenv('CRON_TOKEN') ?: 'met_un_bon_secret_long_et_imprevisible');
}
