<?php

/*
 * Additional rules or rules to override.
 * These rules will be added to default rules or will override them if the same key already exists.
 */

$additionalRules = [];
$rulesProvider = new Algoritma\CodingStandards\Shared\Rules\CompositeRulesProvider([
    new Algoritma\CodingStandards\PhpCsFixer\Rules\DefaultRulesProvider(),
    new Algoritma\CodingStandards\Shared\Rules\ArrayRulesProvider($additionalRules),
]);

$config = new PhpCsFixer\Config();
$config->setRules($rulesProvider->getRules());

$finder = new PhpCsFixer\Finder();

/*
 * You can set manually these paths:
 */
$autoloadPathProvider = new Algoritma\CodingStandards\AutoloadPathProvider();
$finder
    ->in($autoloadPathProvider->getPaths())
    ->exclude(['node_modules', '*/vendor/*']);

$config->setFinder($finder);

return $config;
