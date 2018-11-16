<?php 
require_once('../code/CheckCode.php');

    // Create and set configuration
    $s = new CheckCode('../../aixada2', 'work');
    
        // Set closure compiler
    $s  ->set_cc_jar('C:\_Apache\_Eines\compiler-latest/compiler.jar') // Required!
        ->set_cc_externs('externs_js/jQuery.js', '') // Optional
    
        // Checks
        ->cc_checkJs(array(
            'file-pattern' => '#^js/.*\.js$#i',
            'excluded-patterns' => array(
                '#\.datepicker-#i',
                '#\.min\.js$#i',
                '#/jqGrid-4\.3\.1/#i',
                '#/jquery/#i',
                '#/jquery\.mobile-1\.0\.1/#i',
                '#/jquery-fileupload/#i',
                '#/jqueryui/#i',
                '#/tablesorter/#i'
            )
        ))
        ->cc_extractJs(array(
            'file-pattern' => '#^js/.*\.php$#i', 
            'excluded-patterns' => '#/jquery-fileupload/#i',
            'from-code' => CheckCode::$JS_CODE
        ))
        ->cc_extractJs(array(
            'file-pattern' => '#\.php$#i', 
            'excluded-patterns' => '#/external/#i'
        ))
        ;
    $s = null; // Important to show execution summary.
