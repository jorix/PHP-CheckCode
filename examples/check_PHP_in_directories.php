<?php 
require_once('../code/CheckCode.php');
    // Create and set configuration
    $s = new CheckCode('../../aixada2', 'work');
    
    $s->check_php_dir(
        '#\.php$#i', ''
    );
    
    $s = null; // Important to show execution summary.
?>
