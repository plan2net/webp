-- Minimal root-page TypoScript. page.10 = IMG_RESOURCE renders the URL of
-- the processed image as the page body. The `c` suffix forces a real
-- crop/resize, which makes FAL go through process() → ProcessedFile, which
-- fires the AfterFileProcessing event listener in plan2net/webp and writes
-- the .webp/.avif/.jxl siblings next to the ProcessedFile.
--
-- INSERT OR REPLACE because the runner may re-execute against an existing
-- cached instance. uid=1 collides only with our own previous row.
INSERT OR REPLACE INTO sys_template
    (uid, pid, root, clear, title, constants, config, hidden, deleted, sorting, crdate, tstamp)
VALUES (
    1, 1, 1, 3, 'plan2net/webp E2E',
    '',
    'page = PAGE
page.10 = IMG_RESOURCE
page.10.file = fileadmin/photo.jpg
page.10.file.width = 400c
page.10.file.height = 300c',
    0, 0, 1, strftime('%s','now'), strftime('%s','now')
);
