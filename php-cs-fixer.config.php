<?php

if (PHP_SAPI !== 'cli') {
    exit('This script supports command line usage only.');
}

// https://cs.symfony.com/doc/config.html
$finder = PhpCsFixer\Finder::create()
    ->exclude('Resources')
    ->name('*.php')
    ->in([
        __DIR__,
    ]);

$config = new PhpCsFixer\Config();

return $config->setRiskyAllowed(true)
    ->setRules([
        '@Symfony' => true,
        // Changes from Symfony
        'concat_space' => ['spacing' => 'one'], // 'foo' . 'bar'
        'phpdoc_to_comment' => ['ignored_tags' => ['psalm-suppress']],
        // Custom rules
        'align_multiline_comment' => ['comment_type' => 'phpdocs_like'],
        'array_push' => true,
        'combine_consecutive_issets' => true,
        'comment_to_phpdoc' => true,
        'declare_parentheses' => true,
        'dir_constant' => true,
        'ereg_to_preg' => true,
        'string_implicit_backslashes' => ['double_quoted' => 'escape', 'heredoc' => 'escape', 'single_quoted' => 'escape'],
        'explicit_indirect_variable' => true,
        'explicit_string_variable' => true,
        'fopen_flags' => ['b_mode' => false],
        'fopen_flag_order' => true,
        'function_to_constant' => true,
        'general_phpdoc_annotation_remove' => ['annotations' => ['author', 'package', 'subpackage']],
        'global_namespace_import' => [
            'import_classes' => false,
            'import_constants' => false,
            'import_functions' => false,
        ],
        'heredoc_to_nowdoc' => true,
        'implode_call' => true,
        'logical_operators' => true,
        'method_chaining_indentation' => true,
        'modernize_types_casting' => true,
        'multiline_whitespace_before_semicolons' => true,
        'no_null_property_initialization' => true,
        'no_useless_return' => true,
        'no_useless_sprintf' => true,
        'no_php4_constructor' => true,
        'nullable_type_declaration_for_default_null_value' => false,
        'operator_linebreak' => true,
        'phpdoc_var_annotation_correct_order' => true,
        'regular_callable_call' => true,
        'set_type_to_cast' => true,
        'simple_to_complex_string_variable' => true,
        'simplified_null_return' => true,
        'static_lambda' => true,
    ])
    ->setCacheFile(__DIR__ . '/php-cs-fixer.json')
    ->setFinder($finder);
