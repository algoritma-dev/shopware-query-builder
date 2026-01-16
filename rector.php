<?php

use Rector\Config\RectorConfig;

$additionalRules = [];
$rulesProvider = new Algoritma\CodingStandards\Shared\Rules\CompositeRulesProvider([
    new Algoritma\CodingStandards\Shared\Rules\ArrayRulesProvider($additionalRules),
]);

$autoloadPathProvider = new Algoritma\CodingStandards\AutoloadPathProvider();

$setsProvider = new Algoritma\CodingStandards\Rector\RectorSetsProvider();

return RectorConfig::configure()
    ->withFileExtensions(['php'])
    ->withImportNames(importShortClasses: false)
    ->withParallel()
    ->withPaths($autoloadPathProvider->getPaths())
    ->withSkip(['**/vendor/*', '**/node_modules/*'])
    ->withPhpSets()
    ->withSymfonyContainerXml(__DIR__ . '/var/cache/dev/AppKernelDevDebugContainer.xml')
    ->withComposerBased(symfony: true)
    ->withSets(array_merge($setsProvider->getSets(), [/* custom sets */]))
    ->withRules(array_merge($rulesProvider->getRules(), [/* custom rules */]));
