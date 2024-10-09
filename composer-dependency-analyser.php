<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

$config = new Configuration();

return $config
    ->ignoreErrorsOnPackage('gember/cache-psr', [ErrorType::UNUSED_DEPENDENCY])
    ->ignoreErrorsOnPackage('gember/file-finder-symfony', [ErrorType::UNUSED_DEPENDENCY])
    ->ignoreErrorsOnPackage('gember/file-reflector-roave', [ErrorType::UNUSED_DEPENDENCY])
    ->ignoreErrorsOnPackage('gember/identity-generator-symfony', [ErrorType::UNUSED_DEPENDENCY])
    ->ignoreErrorsOnPackage('gember/message-bus-symfony', [ErrorType::UNUSED_DEPENDENCY])
    ->ignoreErrorsOnPackage('gember/rdbms-event-store-doctrine-dbal', [ErrorType::UNUSED_DEPENDENCY])
    ->ignoreErrorsOnPackage('gember/serializer-symfony', [ErrorType::UNUSED_DEPENDENCY]);
