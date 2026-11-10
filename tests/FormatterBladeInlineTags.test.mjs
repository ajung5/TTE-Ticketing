import assert from 'node:assert/strict';
import { test } from 'node:test';
import { compactBladeInlineOpeningTags as normalize } from '../scripts/format-blade-inline-tags.mjs';

test('keeps adjacent span tags inline without inserting a space', () => {
    const input =
        '<span class="stat-icon stat-sky">↻</span\n                ><span class="stat-micro">PEKERJAAN SAYA</span>';
    const expected =
        '<span class="stat-icon stat-sky">↻</span><span class="stat-micro">PEKERJAAN SAYA</span>';
    assert.equal(normalize(input), expected);
});

test('compacts simple Blade inline opening and closing tags', () => {
    const input = "<strong\n  >{{ $stats['duplicate_alerts'] }} tiket aktif.</strong\n  >";
    assert.equal(
        normalize(input),
        "<strong>{{ $stats['duplicate_alerts'] }} tiket aktif.</strong>",
    );
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
test('compacts the split opening label tag reported in the public form', () => {
    const input = '<label\n                    >Inisial (opsional)</label>';
    assert.equal(normalize(input), '<label>Inisial (opsional)</label>');
});

test('handles ordinary and custom opening tags without attributes', () => {
    const input = '<section\n  ><div\n >A</div\n ><x-label\n >B</x-label\n ></section>';
    assert.equal(normalize(input), '<section><div>A</div><x-label>B</x-label></section>');
});

test('does not combine attributes or change significant whitespace between siblings', () => {
    const input = '<label class="required"\n id="a">Nama</label\n >\n <span\n >Keterangan</span>';
    assert.equal(
        normalize(input),
        '<label class="required"\n id="a">Nama</label>\n <span>Keterangan</span>',
    );
});

test('protects literal split label tags inside Blade expressions, comments and scripts', () => {
    for (const part of [
        '<script>const label = "<label\\n >";</script>',
        '<!-- <label\n > -->',
        '{{-- <label\n > --}}',
        '{{ "<label\n >" }}',
        '@verbatim <label\n > @endverbatim',
    ]) {
        assert.equal(normalize(part), part);
    }
});

test('keeps generic opening-tag normalization idempotent', () => {
    const original = '<label\n >Inisial</label\n ><span\n >X</span>';
    const formatted = normalize(original);
    assert.equal(normalize(formatted), formatted);
});
