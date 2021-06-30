The _public_ subdirectory should contain the files that you want to make visible via your PHPCentauri API's HTTP/HTTPS endpoint.

In other words, this _public_ directory is your PHPCentauri API endpoint's root directory, as configured in your web server of choise.

You can either make this _public_ directory the root firectory of your API website, or you can make _public_ a subdirectory of your API website. The choice depends on your preference.

Files that would go in here are usually, at a minimum, the PVE Controller, PIE Item and PVE View handlers (middleware):
PCE.php
PIE.php
PVE.php

And then, any other custom PHP files that you want to have exposed as part of your PHPCentauri API over your web server, can also go in here, and they would be accessible as one would normally expect across a web server.

The reason for having this _public_ subdirectory is to protect the actual root of your PHPCentauri API's project directory.

You can consider the _public_ subdirectory to be the exposed part of your PHPCentauri API, and any files outside the _public_ subdirectory to be the unexposed (_protected_) part of your PHPCentauri API.
