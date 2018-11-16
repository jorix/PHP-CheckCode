<?php 
require_once('../code/CheckCode.php');
    // Create and set configuration
    $s = new CheckCode('../../aixada2', 'work/');
    
    $s->php_check(array(
        'file-pattern' => '#\.php$#i'
    ));
    
    $s = null; // Important to show execution summary.
