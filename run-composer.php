<?php
putenv('COMPOSER_HOME=' . __DIR__ . '/composer_home');
exec('php composer.phar require smalot/pdfparser 2>&1', $output, $return_var);
echo implode("\n", $output);
echo "\nDone with exit code: " . $return_var;
?>
