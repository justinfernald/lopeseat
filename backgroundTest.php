<?php
require('api.php');

use Cocur\BackgroundProcess\BackgroundProcess;

$process = new BackgroundProcess('sleep 5');
$process->run();

echo sprintf('Crunching numbers in process %d', $process->getPid());
while ($process->isRunning()) {
    echo '.';
    sleep(1);
}
echo "\nDone.\n";

?>