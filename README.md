# block_proctoru

Provides a library of functions oriented towards establishing ProctorU registration status for users.
Creates a custom profile field in `user_info_field` which stores a status code corresponding to an enumeration 
of possible statuses (defined as class constants in `lib.php`). Relies on an included set of classes for 
communicating with the ProctorU webservice. __NB__ the library currently relies on some LSU-specific lookups. Roadmap 
includes decoupling `Webservicelib.php` and making the local user lookup process optional or configurable.
Runs at cron.

##Instalation
Installation is the same for this as for any Moodle local plugin. Add to you `<SiteRoot>/local` directory
and navigate to the _Notifications_ page.

##Configuration
Admin settings determine whether the module processes users during cron, define roles to consider 
`ProctorU::EXEMPT` and all of the connection details required for communication with the various webservices.

