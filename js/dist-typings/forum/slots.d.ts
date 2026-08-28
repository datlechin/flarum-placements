/**
 * Wires every built-in placement to the `ItemList` that renders it.
 *
 * Each `extend` below targets a real, priority-ordered extension point in
 * core; the priorities are chosen against the items core already puts in the
 * same list, which are named in the comments. There is not one `view()`
 * override here except the footer, which core gives no other seam for.
 *
 * `PostStream` is reached by module path rather than by import because it is
 * one of the twelve components core code-splits, and importing it would drag
 * the whole chunk into this extension's bundle. The registry calls the handler
 * immediately when the module is already loaded, so the string form is correct
 * whether or not it happens to be split.
 */
export default function registerSlots(): void;
/**
 * The tag directory, which only exists when flarum/tags is enabled.
 *
 * A tag-filtered listing at /t/{slug} is still a plain IndexPage and is
 * already covered by index_above_list, so this is the directory page alone.
 */
export declare function registerTagSlots(): void;
