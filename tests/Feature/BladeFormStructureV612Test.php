<?php

/**
 * TTE v6.12: HTML/Blade field structure must remain accessible and stable.
 * Test source structure, not whitespace or attribute ordering produced by Prettier.
 */
it(
    'keeps explicit label connections and avoids nested input elements in account forms',
    function () {
        $views = [
            'auth/login' => 2,
            'auth/forgot-password' => 1,
            'auth/reset-password' => 3,
            'auth/profile' => 8,
            'admin/verifiers' => 10,
        ];

        foreach ($views as $view => $expected) {
            $source = file_get_contents(resource_path('views/' . $view . '.blade.php'));
            expect($source)->not->toBeFalse();
            preg_match_all('~<label\b[^>]*>[\s\S]*?</label>~i', $source, $labels);
            foreach ($labels[0] as $label) {
                expect(preg_match('~<input\b~i', $label))->toBe(0);
            }
            preg_match_all(
                '~<div\s+class="form-field">\s*<label\s+for="([^"]+)"\s*>[\s\S]*?</label>\s*<input\b([\s\S]*?)/>\s*</div>~i',
                $source,
                $fields,
                PREG_SET_ORDER,
            );
            expect(count($fields))->toBe($expected);
            foreach ($fields as $field) {
                expect($field[2])->toContain('id="' . $field[1] . '"');
            }
        }
    },
);
