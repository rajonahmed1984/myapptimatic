<?php
 = 'app/Models/ProjectOverhead.php';
 = file_get_contents();
if (strncmp(,  \xEF\xBB\xBF, 3) === 0) {
     = substr(, 3);
}
file_put_contents(, );
