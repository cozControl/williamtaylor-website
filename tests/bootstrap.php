<?php

require dirname(__DIR__).'/vendor/autoload.php';

// PHPUnit and fidelity must never produce evidence concurrently in this checkout.
$validationLock = dirname(__DIR__).'/storage/framework/validation-run.lock';
if (! @mkdir($validationLock)) {
    throw new RuntimeException('Another validation run owns storage/framework/validation-run.lock. Wait for it to finish; investigate stale ownership before removal.');
}
file_put_contents($validationLock.'/owner.json', json_encode(['tool' => 'phpunit', 'pid' => getmypid()]));
register_shutdown_function(static function () use ($validationLock): void {
    @unlink($validationLock.'/owner.json');
    @rmdir($validationLock);
});
