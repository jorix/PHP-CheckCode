<?php 
require_once('../code/Check_js.php');
echo "Start\n";
    // Create and set configuration
    $s = new Check_js('../../aixada2', 'work2');
    $s->set_cc_jar('C:\_Apache\_Eines\compiler-latest/compiler.jar') // Required!
        ->set_externs('externs_js/jQuery.js', '') // Optional
    
    // To check
        ->minimize_js(
            array(
                'js/jquery/jquery-1.7.1.min.js',
                'js/jqueryui/jquery-ui-1.8.20.custom.min.js',
                'js/fgmenu/fg.menu.js',
                'js/aixadautilities/jquery.aixadaMenu.js',
                'js/aixadautilities/jquery.aixadaXML2HTML.js',
                'js/aixadautilities/jquery.aixadaUtilities.js'
            ),
            'js/js_for_aixadaXML2HTML.min.js'
        )
        ->check_mixed_js("js\aixadautilities/stock.js.php")
        ->check_extract_js("login.php")
        ->check_extract_js("manage_money.php")
        ->check_extract_js("manage_ordersXXXXX.php") // not exist don't break
        ->check_extract_js("manage_orders.php")
        ;
    
    $s = null; // Important to show execution summary.
echo "End\n";;
?>
