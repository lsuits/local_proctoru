<?php
$capabilities = array(
    'local/proctoru:viewstats' => array(
        'riskbitmask'  => RISK_PERSONAL,
        'captype'      => 'read',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes'   => array(
            'student'        => CAP_PROHIBIT,
            'teacher'        => CAP_PROHIBIT,
            'editingteacher' => CAP_PROHIBIT,
            'manager'          => CAP_PROHIBIT
        )
    ),
)
?>
