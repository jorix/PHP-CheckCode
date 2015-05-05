<?php 
require_once('../code/Check_js.php');
echo "Start\n";
    $s = new Check_js('../../aixada2_cpy', 'work');
    $s->set_cc_jar('C:\_Apache\_Eines\compiler-latest/compiler.jar');
    $s->set_externs('../externs_js/jQuery.js', '');
    $s->ccompile_extract_js("login.php");
    $s->ccompile_extract_js("manage_ordercs.php");
    $s->ccompile_extract_js("manage_money.php");
    $s->ccompile_extract_js("manage_orders.php");
    $s = null;
echo "End\n";;
?>
