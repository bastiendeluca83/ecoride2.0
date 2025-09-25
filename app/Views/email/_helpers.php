<?php
// Je centralise les helpers d’échappement pour tous les templates email.
if (!function_exists('e'))   { function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); } }
if (!function_exists('esc')) { function esc($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); } }
