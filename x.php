<?php
file_put_contents('documents/logs/test.log', "Log test at " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
echo "Done";
