PHP-CheckCode
=============

Allows to check js code into a PHP pages even if exist PHP in the middle of js code.

Uses [Closure Compiler](https://developers.google.com/closure/compiler/) to detect
**undefined variables** and **trailing commas**.

It also allows to compress the code 100% js using Closure Compiler performance,
by default using `'SIMPLE_OPTIMIZATIONS'`.

NOTE: The mixed code (code js with PHP in the middle) is also checked but
compressed result (located in a temporary folder) should not be used.
