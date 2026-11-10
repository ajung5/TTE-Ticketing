import assert from 'node:assert/strict';
import { test } from 'node:test';
import { compactBladeInlineOpeningTags as normalize } from '../scripts/format-blade-inline-tags.mjs';

test('keeps adjacent span tags inline without inserting a space', () => {
    const input = '<span class="stat-icon stat-sky">↻</span\n                ><span class="stat-micro">PEKERJAAN SAYA</span>';
    const expected = '<span class="stat-icon stat-sky">↻</span><span class="stat-micro">PEKERJAAN SAYA</span>';
    assert.equal(normalize(input), expected);
});

test('compacts simple Blade inline opening and closing tags', () => {
    const input = '<strong\n  >{{ $stats[\'duplicate_alerts\'] }} tiket aktif.</strong\n  >';
    assert.equal(normalize(input), '<strong>{{ $stats[\'duplicate_alerts\'] }} tiket aktif.</strong>');
});

test('handles generic closing tags (including custom elements), not just span', () => {
    const input = '<div>Isi</div\n  ><x-label>Judul</x-label\n \n  >';
    assert.equal(normalize(input), '<div>Isi</div><x-label>Judul</x-label>');
});

test('does not alter ordinary opening tags with attributes, or surrounding whitespace', () => {
    const input = '  <span class="a"\n      id="b">X</span\n >\n <span>Y</span>  ';
    assert.equal(normalize(input), '  <span class="a"\n      id="b">X</span>\n <span>Y</span>  ');
});

test('protects scripts styles textarea comments and Blade template literals', () => {
    const parts = [
        '<script>const x = "</span\n >";</script>',
        '<style>/* </div\n > */</style>',
        '<textarea>Literal </span\n ></textarea>',
        '<!-- literal </span\n > -->',
        '{{-- literal </span\n > --}}',
        '@verbatim literal </span\n > @endverbatim',
        '@php $html = "</span\n >"; @endphp',
        '{{ "</span\n >" }}',
        '{!! "</span\n >" !!}',
    ];
    for (const part of parts) {
        assert.equal(normalize(part), part);
    }
});

test('produces stable output when applied repeatedly', () => {
    const input = '<span\n>Halo</span\n ><span>lagi</span\n>';
    const once = normalize(input);
    assert.equal(once, '<span>Halo</span><span>lagi</span>');
    assert.equal(normalize(once), once);
});
