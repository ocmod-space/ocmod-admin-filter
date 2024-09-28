<?php

output('OpenCart MODule Wrapper.');
output('Using: php ocmodw.php [-option]');
output();
output('Options:');
output(' -z[0|1|2..N]' . "\t" . 'Zip extension or addon number [N] (see below).');
output(' -d[subdir]' . "\t" . 'Use module directory sub-directories as the source. Allowed values are "2x", "3x", "4x", "old".');
output(' -a' . "\t\t" . 'Make encrypted fcl-archive.');
output(' -x' . "\t\t" . 'Extract encrypted fcl-archive into current directory.');
output(' -l' . "\t\t" . 'List of files in encrypted fcl-archive.');
output(' -h' . "\t\t" . 'Print this help.');
output();
