/**
 * The shared entry, which both `forum.ts` and `admin.ts` re-export.
 *
 * It carries no runtime code on purpose. The skeleton left an initializer here
 * whose whole body was a `console.log`, and being on the common entry meant it
 * ran in both bundles: every visitor, every page, with the package name in the
 * message -- on an extension that goes out of its way not to put its name
 * anywhere a reader's browser can see it.
 *
 * What the two frontends actually share is exported from where it lives, and
 * re-exported by `forum/index.ts` and `admin/index.ts` for extensions that
 * want to build on it.
 */

export {};
