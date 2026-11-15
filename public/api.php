<?php
declare(strict_types=1);

// Single entry point of the back end. Every fetch() call from the page lands here.
// URL format: api.php?resource=students|courses|prerequisites|enrollments|transcript[&id=N]

// Load each class from src/ when it is first used (one class per file).
spl_autoload_register(function (string $class): void {
    $file = __DIR__ . '/src/' . $class . '.php';
    if (is_file($file)) {
        require $file;
    }
});

// db.php (provided) calls fail() when DATABASE_URL is missing, so we define it here.
function fail(string $message, int $status): never {
    throw new ApiException($message, $status);
}

require __DIR__ . '/db.php';

(new RequestHandler())->handle();
