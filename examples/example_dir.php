<?php 
require_once('../code/Check_js.php');
echo "Start\n";
    // Create and set configuration
    $s = new Check_js('../../aixada2', 'work');
    $s->set_cc_jar('C:\_Apache\_Eines\compiler-latest/compiler.jar') // Required!
        ->set_externs('externs_js/jQuery.js', '') // Optional
    
    // To check
        ->check_js_dir(
            '#^js/.*\.js$#i',
            array(
                '#\.datepicker-#i',
                '#\.min\.js$#i',
                '#/jqGrid-4\.3\.1/#i',
                '#/jquery/#i',
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
echo "End\n";;
?>
