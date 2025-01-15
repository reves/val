
Usage example, `/public/index.php`:

```php
<?php

require __DIR__.'/../vendor/autoload.php';

use Val\App;

App::run(function() {

    echo 'Hello, World!';

});
```

Timezone defaults to UTC.