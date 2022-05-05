<?php

//addons/casetime/cis/error500.php
//just for faking response code
http_response_code(500);
echo $_SERVER['REQUEST_URI'];
