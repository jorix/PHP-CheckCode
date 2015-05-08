<?php 
require_once('../code/Check_js.php');
echo "Start\n";
    // Create and set configuration
    $s = new Check_js('../../aixada2_cpy', 'work');
    $s->set_cc_jar('C:\_Apache\_Eines\compiler-latest/compiler.jar'); // Required!
    $s->set_externs('../externs_js/jQuery.js', ''); // Optional
    
    // To check
    $s->check_js("js\aixadautilities/loadPDF.js");
    $s->check_mixed_js("js\aixadautilities/stock.js.php");
    $s->check_extract_js("login.php");
    $s->check_extract_js("manage_money.php");
    $s->check_extract_js("manage_ordersXXXXX.php"); // not exist don't break
    $s->check_extract_js("manage_orders.php");
    
    $s = null; // Important to show summary of execution.
echo "End\n";;
?>
