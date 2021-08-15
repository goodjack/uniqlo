<?php

/**
 * @see https://github.com/matt-allan/laravel-code-style/blob/main/src/Dev/GenerateRules.php
 * @see https://gist.github.com/laravel-shift/cab527923ed2a109dda047b97d53c200
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
    'function_typehint_space' => true,
    'no_leading_namespace_whitespace' => true,
];

// StyleCI Laravel Preset
// https://docs.styleci.io/presets#laravel

$laravelRules = [
    // align_phpdoc
    'align_multiline_comment' => [
        'comment_type' => 'phpdocs_like',
    ],

    // alpha_ordered_imports
    'ordered_imports' => [
        'sort_algorithm' => 'alpha',
    ],

    // array_indentation
    'array_indentation' => true,

    // binary_operator_spaces
    'binary_operator_spaces' => [
        'default' => 'single_space',
        // equivalent to not having the align_double_arrow
        // or unalign_double_arrow rule enabled
        'operators' => [
            '=>' => null,
        ],
    ],

    // blank_line_after_namespace
    // NOTE: same as PSR-12

    // blank_line_after_opening_tag
    // NOTE: same as PSR-12

    // blank_line_before_return
    'blank_line_before_statement' => [
        'statements' => [
            'return',
        ],
    ],

    // cast_spaces
    'cast_spaces' => true,

    // class_definition
    // NOTE: same as PSR-12

    // clean_namespace
    'clean_namespace' => true,

    // compact_nullable_typehint
    // NOTE: same as PSR-12

    // concat_without_spaces
    // NOTE: follow PSR-12
    // 'concat_space' => [
    //     'spacing' => 'none',
    // ],

    // declare_equal_normalize
    // NOTE: same as PSR-12

    // die_to_exit
    'no_alias_language_construct_call' => true,

    // elseif
    // NOTE: same as PSR-12

    // encoding
    // NOTE: same as PSR-12

    // full_opening_tag
    // NOTE: same as PSR-12

    // function_declaration
    // NOTE: same as PSR-12

    // function_typehint_space
    // NOTE: same as PSR-12

    // hash_to_slash_comment
    'single_line_comment_style' => [
        'comment_types' => [
            'hash',
        ],
    ],

    // heredoc_to_nowdoc
    'heredoc_to_nowdoc' => true,

    // include
    'include' => true,

    // indentation
    // NOTE: same as PSR-12

    // laravel_braces
    // NOTE: follow PSR-12

    // lowercase_cast
    // NOTE: same as PSR-12

    // lowercase_constants
    // NOTE: same as PSR-12

    // lowercase_keywords
    // NOTE: same as PSR-12

    // lowercase_static_reference
    // NOTE: same as PSR-12

    // magic_constant_casing
    'magic_constant_casing' => true,

    // magic_method_casing
    'magic_method_casing' => true,

    // method_argument_space
    // NOTE: follow PSR-12

    // method_separation
    'class_attributes_separation' => [
        'elements' => [
            'method' => 'one',
        ],
    ],

    // method_visibility_required
    // NOTE: same as PSR-12

    // native_function_casing
    'native_function_casing' => true,

    // native_function_type_declaration_casing
    'native_function_type_declaration_casing' => true,

    // no_alternative_syntax
    'no_alternative_syntax' => true,

    // no_binary_string
    'no_binary_string' => true,

    // no_blank_lines_after_class_opening
    // NOTE: same as PSR-12

    // no_blank_lines_after_phpdoc
    'no_blank_lines_after_phpdoc' => true,

    // no_blank_lines_after_throw
    // no_blank_lines_between_imports
    // no_blank_lines_between_traits
    'no_extra_blank_lines' => [
        'tokens' => [
            'extra',
            'throw',
            'use',
            'use_trait',
        ],
    ],

    // no_closing_tag
    'no_closing_tag' => true,

    // no_empty_phpdoc
    'no_empty_phpdoc' => true,

    // no_empty_statement
    'no_empty_statement' => true,

    // no_extra_consecutive_blank_lines
    // NOTE: no_extra_blank_lines

    // no_leading_import_slash
    // NOTE: same as PSR-12

    // no_leading_namespace_whitespace
    // NOTE: same as PSR-12

    // no_multiline_whitespace_around_double_arrow
    'no_multiline_whitespace_around_double_arrow' => true,

    // no_multiline_whitespace_before_semicolons
    'multiline_whitespace_before_semicolons' => true,

    // no_short_bool_cast
    'no_short_bool_cast' => true,

    // no_singleline_whitespace_before_semicolons
    'no_singleline_whitespace_before_semicolons' => true,

    // no_spaces_after_function_name
    // NOTE: same as PSR-12

    // no_spaces_inside_offset
    'no_spaces_around_offset' => [
        'positions' => [
            'inside',
        ],
    ],

    // no_spaces_inside_parenthesis
    // NOTE: same as PSR-12

    // no_trailing_comma_in_list_call
    'no_trailing_comma_in_list_call' => true,

    // no_trailing_comma_in_singleline_array
    'no_trailing_comma_in_singleline_array' => true,

    // no_trailing_whitespace
    // NOTE: same as PSR-12

    // no_trailing_whitespace_in_comment
    // NOTE: same as PSR-12

    // no_unneeded_control_parentheses
    'no_unneeded_control_parentheses' => true,

    // no_unneeded_curly_braces
    'no_unneeded_curly_braces' => true,

    // no_unset_cast
    'no_unset_cast' => true,

    // no_unused_imports
    'no_unused_imports' => true,

    // no_unused_lambda_imports
    'lambda_not_used_import' => true,

    // no_useless_return
    'no_useless_return' => true,

    // no_whitespace_before_comma_in_array
    'no_whitespace_before_comma_in_array' => true,

    // no_whitespace_in_blank_line
    // NOTE: same as PSR-12

    // normalize_index_brace
    'normalize_index_brace' => true,

    // not_operator_with_successor_space
    'not_operator_with_successor_space' => true,

    // object_operator_without_whitespace
    'object_operator_without_whitespace' => true,

    // phpdoc_indent
    'phpdoc_indent' => true,

    // phpdoc_inline_tag_normalizer
    'phpdoc_inline_tag_normalizer' => true,

    // phpdoc_no_access
    'phpdoc_no_access' => true,

    // phpdoc_no_package
    'phpdoc_no_package' => true,

    // phpdoc_no_useless_inheritdoc
    'phpdoc_no_useless_inheritdoc' => true,

    // phpdoc_return_self_reference
    'phpdoc_return_self_reference' => true,

    // phpdoc_scalar
    'phpdoc_scalar' => true,

    // phpdoc_single_line_var_spacing
    'phpdoc_single_line_var_spacing' => true,

    // phpdoc_singular_inheritdoc
    'phpdoc_no_useless_inheritdoc' => true,

    // phpdoc_summary
    'phpdoc_summary' => true,

    // phpdoc_trim
    'phpdoc_trim' => true,

    // phpdoc_type_to_var
    'phpdoc_no_alias_tag' => [
        'replacements' => [
            'type' => 'var',
        ],
    ],

    // phpdoc_types
    'phpdoc_types' => true,

    // phpdoc_var_without_name
    'phpdoc_var_without_name' => true,

    // post_increment
    'increment_style' => [
        'style' => 'post',
    ],

    // print_to_echo
    'no_mixed_echo_print' => [
        'use' => 'echo',
    ],

    // property_visibility_required
    // NOTE: same as PSR-12

    // return_type_declaration
    // NOTE: same as PSR-12

    // short_array_syntax
    'array_syntax' => [
        'syntax' => 'short',
    ],

    // short_list_syntax
    'list_syntax' => [
        'syntax' => 'short',
    ],

    // short_scalar_cast
    // NOTE: same as PSR-12

    // single_blank_line_at_eof
    // NOTE: same as PSR-12

    // single_blank_line_before_namespace
    // NOTE: same as PSR-12

    // single_class_element_per_statement
    // NOTE: same as PSR-12

    // single_import_per_statement
    // NOTE: same as PSR-12

    // single_line_after_imports
    // NOTE: same as PSR-12

    // single_quote
    'single_quote' => true,

    // space_after_semicolon
    'space_after_semicolon' => true,

    // standardize_not_equals
    'standardize_not_equals' => true,

    // switch_case_semicolon_to_colon
    // NOTE: same as PSR-12

    // switch_case_space
    // NOTE: same as PSR-12

    // switch_continue_to_break
    'switch_continue_to_break' => true,

    // ternary_operator_spaces
    // NOTE: same as PSR-12

    // trailing_comma_in_multiline_array
    'trailing_comma_in_multiline' => [
        'elements' => [
            'arrays',
        ],
    ],

    // trim_array_spaces
    'trim_array_spaces' => true,

    // unalign_equals
    // NOTE: with binary_operator_spaces

    // unary_operator_spaces
    'unary_operator_spaces' => true,

    // unix_line_endings
    // NOTE: same as PSR-12

    // whitespace_after_comma_in_array
    'whitespace_after_comma_in_array' => true,

    // no_alias_functions
    'no_alias_functions' => true,

    // no_unreachable_default_argument_value
    'no_unreachable_default_argument_value' => true,

    // psr4
    'psr_autoloading' => true,

    // self_accessor
    'self_accessor' => true,
];

$rules = array_merge($psr12Rules, $laravelRules);

return (new Config())
    ->setRules($rules)
    ->setFinder($finder)
    ->setRiskyAllowed(true)
    ->setUsingCache(true);
