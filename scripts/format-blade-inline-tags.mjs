/**
 * TTE v6.10: normalize broken HTML tag delimiters produced by Prettier Blade.
 *
 * Only whitespace INSIDE an HTML tag's delimiter is removed. Do not insert
 * whitespace between adjacent inline elements: that can affect rendering.
 * Protect literal JS/CSS/textarea content and common Blade/HTML comments.
 */
const openingWithoutAttributes = /<(strong|em|b|i|span|small|code|mark)[ \t]*\r?\n[ \t]*>/gi;
const splitClosingTag = /<\/([A-Za-z][A-Za-z0-9:-]*)[ \t]*(?:\r?\n[ \t]*)+>/g;
const protectedBlock = /(<script\b[^>]*>[\s\S]*?<\/script\s*>|<style\b[^>]*>[\s\S]*?<\/style\s*>|<textarea\b[^>]*>[\s\S]*?<\/textarea\s*>|\{\{--[\s\S]*?--\}\}|<!--[\s\S]*?-->|@verbatim\b[\s\S]*?@endverbatim|@php\s+[\s\S]*?@endphp|<\?php[\s\S]*?\?>|\{\{[\s\S]*?\}\}|\{!![\s\S]*?!!\})/gi;

export function compactBladeInlineOpeningTags(source) {
    return source
        .split(protectedBlock)
        .map((part, index) =>
            index % 2 === 1
                ? part
                : part
                      .replace(openingWithoutAttributes, '<$1>')
                      .replace(splitClosingTag, '</$1>'),
        )
        .join('');
}
