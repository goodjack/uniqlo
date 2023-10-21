<?php

/**
 * @see https://github.com/goodjack/dotfiles/blob/master/PHP/.php-cs-fixer.dist.php
 * @see https://mlocati.github.io/php-cs-fixer-configurator/
 */

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::create()
    ->in([
        __DIR__ . '/app',
        __DIR__ . '/config',
        __DIR__ . '/database',
        __DIR__ . '/resources',
        __DIR__ . '/routes',
        __DIR__ . '/tests',
    ])
    ->name('*.php')
    ->notName('*.blade.php')
    ->ignoreDotFiles(true)
    ->ignoreVCS(true);

// A provisional rule set for PSR-12
// https://github.com/FriendsOfPHP/PHP-CS-Fixer/issues/4502#issuecomment-570408101

$psr12Rules = [
    '@PSR12' => true,
    '@PSR12:risky' => true,
    'concat_space' => [
        'spacing' => 'one',
    ],
];

// Laravel Pint Laravel Preset
// https://github.com/laravel/pint/blob/main/resources/presets/laravel.php

$laravelRules = [
    'array_indentation' => true,
    'array_syntax' => ['syntax' => 'short'],
    'binary_operator_spaces' => ['default' => 'single_space'],
    'blank_line_before_statement' => ['statements' => ['continue', 'return']],
    'cast_spaces' => true,
    'class_attributes_separation' => ['elements' => ['const' => 'one', 'method' => 'one', 'property' => 'one', 'trait_import' => 'none']],
    'class_definition' => ['inline_constructor_arguments' => false, 'multi_line_extends_each_single_line' => true, 'single_item_single_line' => true, 'single_line' => true, 'space_before_parenthesis' => true],
    'clean_namespace' => true,
    // 'concat_space' => ['spacing' => 'none'],
    'curly_braces_position' => ['control_structures_opening_brace' => 'same_line', 'functions_opening_brace' => 'next_line_unless_newline_at_signature_end', 'anonymous_functions_opening_brace' => 'same_line', 'classes_opening_brace' => 'next_line_unless_newline_at_signature_end', 'anonymous_classes_opening_brace' => 'next_line_unless_newline_at_signature_end', 'allow_single_line_empty_anonymous_classes' => false, 'allow_single_line_anonymous_functions' => false],
    'declare_parentheses' => true,
    'fully_qualified_strict_types' => true,
    'function_typehint_space' => true,
    'general_phpdoc_tag_rename' => true,
    'heredoc_to_nowdoc' => true,
    'include' => true,
    'increment_style' => ['style' => 'post'],
    'integer_literal_case' => true,
    'lambda_not_used_import' => true,
    'linebreak_after_opening_tag' => true,
    'list_syntax' => true,
    'magic_constant_casing' => true,
    'magic_method_casing' => true,
    'method_chaining_indentation' => true,
    'multiline_whitespace_before_semicolons' => ['strategy' => 'no_multi_line'],
    'native_function_casing' => true,
    'native_function_type_declaration_casing' => true,
    'no_alias_functions' => true,
    'no_alias_language_construct_call' => true,
    'no_alternative_syntax' => true,
    'no_binary_string' => true,
    'no_blank_lines_after_phpdoc' => true,
    'no_empty_phpdoc' => true,
    'no_empty_statement' => true,
    'no_extra_blank_lines' => ['tokens' => ['extra', 'throw', 'use']],
    'no_leading_namespace_whitespace' => true,
    'no_mixed_echo_print' => ['use' => 'echo'],
    'no_multiline_whitespace_around_double_arrow' => true,
    'no_short_bool_cast' => true,
    'no_singleline_whitespace_before_semicolons' => true,
    'no_spaces_around_offset' => ['positions' => ['inside', 'outside']],
    'no_superfluous_phpdoc_tags' => ['allow_mixed' => true, 'allow_unused_params' => true],
    'no_trailing_comma_in_singleline' => true,
    'no_unneeded_control_parentheses' => ['statements' => ['break', 'clone', 'continue', 'echo_print', 'return', 'switch_case', 'yield']],
    'no_unneeded_curly_braces' => true,
    'no_unset_cast' => true,
    'no_unused_imports' => true,
    'no_useless_return' => true,
    'no_whitespace_before_comma_in_array' => true,
    'normalize_index_brace' => true,
    'not_operator_with_successor_space' => true,
    'object_operator_without_whitespace' => true,
    'ordered_imports' => ['imports_order' => ['class', 'function', 'const'], 'sort_algorithm' => 'alpha'],
    'phpdoc_indent' => true,
    'phpdoc_inline_tag_normalizer' => true,
    'phpdoc_no_access' => true,
    'phpdoc_no_package' => true,
    'phpdoc_no_useless_inheritdoc' => true,
    'phpdoc_order' => true,
    'phpdoc_scalar' => true,
    'phpdoc_separation' => true,
    'phpdoc_single_line_var_spacing' => true,
    'phpdoc_tag_type' => ['tags' => ['inheritdoc' => 'inline']],
    'phpdoc_trim' => true,
    'phpdoc_types' => true,
    'phpdoc_var_without_name' => true,
    'return_type_declaration' => ['space_before' => 'none'],
    'self_static_accessor' => true,
    'single_line_comment_style' => ['comment_types' => ['hash']],
    'single_quote' => true,
    'single_space_around_construct' => true,
    'space_after_semicolon' => true,
    'standardize_not_equals' => true,
    'trailing_comma_in_multiline' => ['elements' => ['arrays']],
    'trim_array_spaces' => true,
    'types_spaces' => true,
    'unary_operator_spaces' => true,
    'whitespace_after_comma_in_array' => true,
];

$rules = array_merge($psr12Rules, $laravelRules);

return (new Config())
    ->setRules($rules)
    ->setFinder($finder)
    ->setRiskyAllowed(true)
    ->setUsingCache(true);
