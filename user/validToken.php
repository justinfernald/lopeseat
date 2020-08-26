<?php
require('../api.php');

if ($user == null) {
    result(false, "Not valid API Token");
    exit();
}

result(true);
exit();
?>