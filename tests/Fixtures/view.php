<?php
if (!isset($name) || !is_string($name)) {
    throw new RuntimeException('The view requires a string name.');
}
?>Hello, <?= phpb_e($name) ?>!
