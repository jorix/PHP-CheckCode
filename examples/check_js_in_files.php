<?php 
require_once('../code/CheckCode.php');
    // Create and set configuration
    $s = new CheckCode('../../aixada2', 'work/');
    
        // Set closure compiler
    $s  ->set_cc_jar('C:\_Apache\_Eines\compiler-latest/compiler.jar') // Required!
        ->set_cc_externs('externs_js/jQuery.js', '') // Optional
    
    // To check
        ->cc_extractJs(array(
            'files' => 'js\aixadautilities/stock.js.php',
            'from-code' => CheckCode::$JS_CODE
        ))
        ->cc_extractJs(array(
            'files' => array(
                'login.php',
                'manage_money.php', 
                'manage_ordersXXXXX.php', // not exist don't break
                'manage_orders.php'
            )
        ))
        ->php_check(array('files' => 'manage_ordersXXXXX.php')) // not exist don't break
        
        ->set_cc_options() // remove default options of Check_js
        ->cc_minimizeJs(array(
            'files' => array(
                'js/jquery/jquery-1.7.1.min.js',
                'js/jqueryui/jquery-ui-1.8.20.custom.min.js',
                'js/fgmenu/fg.menu.js',
                'js/aixadautilities/jquery.aixadaMenu.js',
                'js/aixadautilities/jquery.aixadaXML2HTML.js',
                'js/aixadautilities/jquery.aixadaUtilities.js'
            ),
            'output-file' => 'js/js_for_aixadaXML2HTML.min.js'
        ))
        ;
    
    $s = null; // Important to show execution summary.
