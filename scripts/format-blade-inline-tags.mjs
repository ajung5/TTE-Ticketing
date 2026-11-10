/**
 * TTE v6.10: normalize broken HTML tag delimiters produced by Prettier Blade.
 *
 * Only whitespace INSIDE an HTML tag's delimiter is removed. Do not insert
 * whitespace between adjacent inline elements: that can affect rendering.
 * Protect literal JS/CSS/textarea content and common Blade/HTML comments.
 */
// All opening HTML/component tags without attributes (including <label>) may
// have the closing bracket moved to a new line by Prettier Blade. Normalize
// *only* newline whitespace between a standalone tag name and '>'.
// Keep attribute-bearing openings untouched and do not alter inter-tag spaces.
const openingWithoutAttributes = /<([A-Za-z][A-Za-z0-9:-]*)[ \t]*(?:\r?\n[ \t]*)+>/g;
const splitClosingTag = /<\/([A-Za-z][A-Za-z0-9:-]*)[ \t]*(?:\r?\n[ \t]*)+>/g;
const protectedBlock =
    /(<script\b[^>]*>[\s\S]*?<\/script\s*>|<style\b[^>]*>[\s\S]*?<\/style\s*>|<textarea\b[^>]*>[\s\S]*?<\/textarea\s*>|\{\{--[\s\S]*?--\}\}|<!--[\s\S]*?-->|@verbatim\b[\s\S]*?@endverbatim|@php\s+[\s\S]*?@endphp|<\?php[\s\S]*?\?>|\{\{[\s\S]*?\}\}|\{!![\s\S]*?!!\})/gi;

export function compactBladeInlineOpeningTags(source) {
    return source
        .split(protectedBlock)
        .map((part, index) =>
            index % 2 === 1
                ? part
                : part.replace(openingWithoutAttributes, '<$1>').replace(splitClosingTag, '</$1>'),
        )
        .join('');
}
