<?php

namespace local_proctoru\task;

class proctor_users extends \core\task\scheduled_task
{      
    public function get_name() {
        return get_string('task_name', 'local_proctoru');
    }
                                                                     
    public function execute() {       
    	global $CFG;
		require_once($CFG->dirroot.'/local/proctoru/lib.php');

		return handle_local_proctoru();
    }                                                                                                                               
} 