--TEST--
Check for jIKVttl interface
--SKIPIF--
<?php if (!extension_loaded("jelix")) print "skip"; ?>
--FILE--
<?php 
if(interface_exists('jIKVttl', false)) echo "YES"; else echo "NO";
?>
--EXPECT--
YES