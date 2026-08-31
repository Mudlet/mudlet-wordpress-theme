-- What the world says when there is no site to ask ----------------------------
--
-- The shape of the seed's answer and the fallback for everywhere there is no
-- WordPress behind the frame. seed.lua overwrites what it can reach; everything
-- it cannot stays as written here. See mudlet-demo/seed.lua.

local URL = require('mudlet-demo.urls')

-- What the site says about itself ---------------------------------------------
--
-- Every number and version in this world used to be typed into its prose, and
-- prose does not notice when a release ships: the vault was still stacked with
-- 4.22.0 crates the week 5.0 came out. So the world asks the site it is framed
-- in, once, while the console is still animating its fake connect:
--
--     GET /wp-json/mudlet/v1/demo
--
-- The other end of that is wordpress/theme/mudlet/inc/demo-seed.php, which
-- answers out of the same plugins the pages themselves are drawn from — so the
-- vault and /download/ cannot disagree, and neither can the shelves and the
-- games grid.
--
-- What is written below is both the fallback and the shape of the answer. It
-- has to be a fallback, because the demo is not always inside mudlet.org: it
-- runs from the prototype page, from a Vite dev server and from a file:// copy,
-- none of which have a WordPress behind them. There, and on any request that
-- fails or arrives late, the world stays as written here — the July 2026
-- snapshot the rooms were composed against — and says nothing about it. A hero
-- has no business showing a visitor an error.
local SITE = {
    release = {
        version    = '4.22.0',
        date       = '6 July 2026',
        date_short = '6 Jul 2026',
        date_loud  = '6 JULY 2026',
        url        = URL.download,
        -- Keyed the way the crates are named, which is what a visitor types at.
        builds = {
            windows = { label = 'Windows',              size = '128.8 MiB',
                        short = 'b9f49c8d…9089bd39', url = URL.win },
            macos   = { label = 'macOS (Intel)',        size = '131.7 MiB',
                        short = '64371626…e64ac6d7', url = URL.macx },
            silicon = { label = 'macOS (Apple Silicon)', size = '130.1 MiB',
                        short = '54d97693…10261bdb', url = URL.macarm },
            linux   = { label = 'Linux',                size = '170.4 MiB',
                        short = '8f10a78a…8a7c9040', url = URL.linux },
        },
    },
    games = {
        count = 42,
        names = { 'Achaea', 'Aetolia', 'Lusternia', 'Imperian', 'Starmourn',
            'Aardwolf', 'BatMUD', 'ZombieMUD', 'WoTMUD', 'Icesus', '3Kingdoms',
            'God Wars II' },
        url = URL.download,
    },
    -- The ledger, and what the sage says when asked about anyone in it. MAKERS
    -- in people.lua is the same list written out, and it is the fallback: the
    -- site's copy wins wherever there is a site, so nobody's credit depends on
    -- when this file was last edited.
    makers = { count = 30, people = {}, url = URL.makers },
    news = {
        count = 178,
        url   = URL.news,
        posts = {
            { date = '6 Jul 2026', title = '4.22 — mapping, made friendlier',
              author = 'Vadim Peretokin', url = URL.post422,
              blurb = 'create, rename and delete map areas from the mapper itself' },
            { date = '6 Jul 2026', title = 'Mudlet 4.22.0',
              author = 'Vadim Peretokin', url = URL.post4220,
              blurb = 'a Configure Areas UI, lockable stub exits, fourteen fixes' },
            { date = '13 Jun 2026', title = '4.21 — Mudlet, made better',
              author = 'ZookaOnGit', url = URL.post421,
              blurb = '47 features, 77 improvements, 207 bug fixes' },
        },
    },
}

return SITE
