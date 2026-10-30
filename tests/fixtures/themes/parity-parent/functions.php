<?php
declare(strict_types=1);

// Deliberate finding: bad-things must report this on both paths, including via parity-child.
eval( 'return 1;' );
