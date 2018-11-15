<?php 
require_once('../code/CheckCode.php');

    // Create and set configuration
    $s = new CheckCode('../../aixada2', 'work');
    
        // Set closure compiler
    $s  ->set_cc_jar('C:\_Apache\_Eines\compiler-latest/compiler.jar') // Required!
        ->set_externs('externs_js/jQuery.js', '') // Optional
    
        // Checks
        ->check_js_dir(
            '#^js/.*\.js$#i',
            array(
                '#\.datepicker-#i',
                '#\.min\.js$#i',
                '#/jqGrid-4\.3\.1/#i',
                '#/jquery/#i',
                '#/jquery\.mobile-1\.0\.1/#i',
                '#/jquery-fileupload/#i',
                '#/jqueryui/#i',
                '#/tablesorter/#i'
            )
        )
        ->check_mixed_js_dir(
            '#^js/.*\.php$#i', array('#/jquery-fileupload/#i')
        )
        ->check_extract_js_dir(
            '#\.php$#i', '#/external/#i', array('#^js/#i')
        )
        ;
    $s = null; // Important to show execution summary.
?>
