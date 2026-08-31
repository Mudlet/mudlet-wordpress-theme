-- mudlet.org, walked instead of scrolled.
--
-- Six rooms stand in for the site: the front page, the release downloads, the
-- news archive, the places the site links out to, the people who built it and
-- the workshop they build it in. Everything a visitor can open here opens the
-- real page it is parodying — the descriptions carry the links, so
-- `look windows` is the download page's Windows row and the link in it is that
-- row's button.
--
-- One room is the exception to all of that, and it is the last one: the
-- Workshop does not know what it says until somebody asks, because what landed
-- this week is not a fact about the site at all. See "The Workshop", below.
--
-- Nothing has to be typed. Every noun, exit and suggested command prints as a
-- clickable link, so the whole world is playable with a mouse — which is the
-- point in a homepage hero, where most visitors will click before they type.
--
-- The prose is written; the facts inside it are not. Versions, weights, hashes,
-- headlines and counts are asked of the site this is framed in, once, over one
-- REST call while the console animates its connect — see SITE, further down.
-- What is written into SITE is the July 2026 snapshot, and it is what the world
-- says anywhere there is no site to ask: the prototype page, a dev server, a
-- file:// copy.
demo = demo or {}
local D = demo

local C = {
    room = '<245,108,39>',
    desc = '<156,143,124>',
    exit = '<130,192,199>',
    sys  = '<138,154,91>',
    text = '<232,220,198>',
    dim  = '<124,112,98>',
    -- The sage is the only thing in this world that talks, and what they say is
    -- quoted from Mudlet's own About box — so it gets a colour of its own
    -- rather than borrowing the narration's.
    say  = '<212,175,110>',
}

-- Two kinds of clickable, one rule the visitor can learn in one glance:
-- underlined means clickable, orange means it leaves the site. Commands that
-- stay in the world keep the colour of the line they sit in.
local U = '<u>'

-- The terminal on the plinth quotes this package's own size back at the
-- visitor, and a hand-typed number is wrong the moment anyone edits the world.
-- scripts/build-package.mjs counts the .lua files it zips and rewrites the
-- literal below on every build, failing if this line stops matching the pattern
-- it looks for. What is written here is only what an unbuilt copy would claim.
local SCRIPT_LINES = 0

-- 1315 -> "1,315". One separator is all a package this size will ever need.
local function thousands(n)
    return (tostring(n):gsub('^(%d+)(%d%d%d)$', '%1,%2'))
end

local URL = {
    home     = 'https://www.mudlet.org/',
    download = 'https://www.mudlet.org/download/',
    news     = 'https://www.mudlet.org/news/',
    makers   = 'https://www.mudlet.org/the-makers/',
    packages = 'https://packages.mudlet.org/',
    forum    = 'https://forums.mudlet.org/',
    wiki     = 'https://wiki.mudlet.org/',
    discord  = 'https://discord.gg/kuYvMQ9',
    github   = 'https://github.com/Mudlet/Mudlet',
    -- The pages the clerk in the Workshop is reading off a wire. Every line
    -- they say carries one of these, so a visitor who gets the apology instead
    -- of the answer is still one click from the thing itself.
    commits  = 'https://github.com/Mudlet/Mudlet/commits',
    pulls    = 'https://github.com/Mudlet/Mudlet/pulls',
    issues   = 'https://github.com/Mudlet/Mudlet/issues',
    firstish = 'https://github.com/Mudlet/Mudlet/issues?q=is%3Aissue+is%3Aopen+label%3A%22good+first+issue%22',
    ptb      = 'https://make.mudlet.org/snapshots/?platform=all&source=ptb',
    -- The download manager's own links, which is what the real buttons point
    -- at: each one redirects straight to the installer, so opening it puts the
    -- file in the visitor's downloads rather than showing them another page.
    -- The ids belong to whichever release is current and move with each one —
    -- unlike the version, weights and hashes typed into the vault below, which
    -- are the July 2026 snapshot.
    win      = 'https://www.mudlet.org/download/70/',
    macx     = 'https://www.mudlet.org/download/69/',
    macarm   = 'https://www.mudlet.org/download/68/',
    linux    = 'https://www.mudlet.org/download/67/',
    post422  = 'https://www.mudlet.org/2026/07/4-22-mapping-made-friendlier/',
    post4220 = 'https://www.mudlet.org/2026/07/mudlet-4-22-0/',
    post421  = 'https://www.mudlet.org/2026/06/4-21-mudlet-made-better/',
}

-- Output ---------------------------------------------------------------------
--
-- One path for everything the world prints, so a line can mix plain text with
-- both kinds of link without two output mechanisms racing for the same buffer
-- line.
--
-- Adjacent strings are concatenated into a single decho: every decho and
-- dechoLink resets the format before it prints, so a colour tag passed as its
-- own argument would be reset away by the very next call. Coalescing means a
-- line reads as `say(C.text, 'a ', 'b')` at the call site and still arrives as
-- one run of colour — and a link only ever breaks the run where it has to.

-- A link out to the real mudlet.org page this thing is standing in for.
local function link(label, url, hint)
    return { label = label, code = string.format('openUrl(%q)', url),
        hint = hint or url, colour = C.room }
end

-- A link that plays the game: clicking it runs the command as though it had
-- been typed. expandAlias, not send — send goes straight at a socket that
-- isn't there, and it is the catch-all alias that makes an offline profile
-- answer at all.
local function cmd(label, command, hint, colour)
    return { label = label, code = string.format('expandAlias(%q)', command),
        hint = hint or command, colour = colour or C.text }
end

local function say(...)
    local buf = {}
    local function flush()
        if #buf > 0 then
            decho(table.concat(buf))
            buf = {}
        end
    end
    for i = 1, select('#', ...) do
        local part = select(i, ...)
        if type(part) == 'table' then
            flush()
            dechoLink(part.colour .. U .. part.label, part.code, part.hint, true)
        elseif part ~= nil then
            buf[#buf + 1] = part
        end
    end
    flush()
    echo('\n')
end

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
    -- further down is the same list written out, and it is the fallback: the
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

-- The world spells its numbers out — forty-two boxes, thirty near enough — so
-- the sage does too rather than dropping digits into the middle of a sentence.
-- It has to spell whatever the site sends back, which is why this composes
-- rather than lists: forty-two was a fact about July 2026, not a constant.
local NUMBERS = {
    'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten',
    'eleven', 'twelve', 'thirteen', 'fourteen', 'fifteen', 'sixteen', 'seventeen',
    'eighteen', 'nineteen', 'twenty',
}

local TENS = { 'twenty', 'thirty', 'forty', 'fifty', 'sixty', 'seventy', 'eighty', 'ninety' }

local function spell(n)
    n = tonumber(n) or 0
    if NUMBERS[n] then return NUMBERS[n] end
    if n > 20 and n < 100 then
        local ten, one = math.floor(n / 10), n % 10
        return TENS[ten - 1] .. (one > 0 and ('-' .. NUMBERS[one]) or '')
    end
    -- Past ninety-nine it is a figure. The news archive is already there.
    return tostring(n)
end

-- Sentences start with a capital; the spelling is the same word.
local function spellCap(n)
    local word = spell(n)
    return word:sub(1, 1):upper() .. word:sub(2)
end

-- What a crate has stencilled on it: "Mudlet 5.0.0 for Windows, 122.7 MiB".
-- One function because that string is printed three times per crate — on the
-- lid, in the link under the description, and in the line take() prints — and
-- three copies of it would drift apart inside one release.
local function crateLabel(name)
    local build = SITE.release.builds[name] or {}
    local label = 'Mudlet ' .. SITE.release.version .. ' for ' .. (build.label or name)
    return build.size and build.size ~= '' and (label .. ', ' .. build.size) or label
end

-- A dozen of the boxed worlds, drawn again every time somebody reads the
-- shelves. The site sends all forty-odd; naming a fixed twelve of them would
-- give the same twelve every look, which is the reason the page's own grid
-- shuffles too. Drawn without replacement, so no lid is stencilled twice.
local function someGames(wanted)
    local pool = {}
    for _, name in ipairs(SITE.games.names) do pool[#pool + 1] = name end
    local shown = {}
    for _ = 1, math.min(wanted, #pool) do
        shown[#shown + 1] = table.remove(pool, math.random(#pool))
    end
    return shown
end

-- The two lines under a crate's description: the hash, elided the way the
-- download page elides it, and the link that is the download itself.
local function crateLines(name)
    local build = SITE.release.builds[name] or {}
    if build.short and build.short ~= '' then
        say(C.dim, '  sha256 ', build.short)
    end
    say(C.dim, '  ', link(crateLabel(name), build.url or SITE.release.url))
end

-- The orange button ----------------------------------------------------------
--
-- The button on the front page is labelled DOWNLOAD MUDLET, so it downloads
-- Mudlet: the build for the machine the visitor is reading this on, the same
-- one the real front page would hand them.
--
-- getOS() is how it knows. In the browser that is not the platform Mudlet was
-- built for — there isn't one — it is the visitor's own OS, sniffed from the
-- user agent, which is the guess the download page makes too, so the world and
-- the page agree without either having to ask the other.
local BUILDS = {
    win    = { url = URL.win,    what = 'the Windows installer' },
    macarm = { url = URL.macarm, what = 'the macOS build', intel = true },
    linux  = { url = URL.linux,  what = 'the Linux AppImage' },
}

local function currentBuild()
    local name, version, third = getOS()
    if name == 'windows' then return BUILDS.win end
    if name == 'linux' then
        -- Android and ChromeOS both come back as linux, with an osType in
        -- front of the processor that nothing else has. Neither of them runs
        -- an AppImage.
        if third == 'android' or third == 'chromeos' then return nil end
        return BUILDS.linux
    end
    if name == 'mac' then
        -- iPhones and iPads say mac as well, and the version is the only thing
        -- here that separates them: the sniffer matches "Mac OS X 10_15_7" and
        -- not an iOS agent's "like Mac OS X", so those arrive unknown.
        if version == 'unknown' then return nil end
        -- Safari reports Intel even on Apple Silicon, so arm64 is the safer
        -- default — the same call the download page makes, with the x86_64
        -- build one line underneath.
        return BUILDS.macarm
    end
    return nil
end

-- `afar` is the visitor typing 'download' in a room the button is not in.
local function press(afar)
    local build = currentBuild()
    if not build then
        openUrl(URL.download)
        say(C.text, 'The button gives, then catches. Whatever you are reading this on is ',
            'not something Mudlet ships an installer for — ',
            link('the download page', URL.download), C.text, ' has every platform there is.')
        return
    end
    openUrl(build.url)
    if afar then
        say(C.text, 'The button is on the front page, but it reaches from here: ',
            build.what, ' starts downloading.')
    else
        say(C.text, 'It gives with a clunk you feel in your wrist, and ', build.what,
            ' starts downloading.')
    end
    say(C.dim, 'If your browser held that back, take it by hand: ',
        link(build.what, build.url), C.dim .. '.')
    if build.intel then
        say(C.dim, 'On an Intel Mac? ', link('The x86_64 build', URL.macx),
            C.dim, ' is the one you want instead.')
    end
end

-- The world ------------------------------------------------------------------
--
-- Each thing carries the noun the parser matches (keys[1] is canonical, the
-- rest are the phrasings a visitor might reach for), the name it is listed
-- under, an optional url that `take` opens, and what looking at it prints.

D.rooms = {
    home = {
        title = 'The Front Page',
        -- A function, not a string: the shelves hold however many worlds Mudlet
        -- currently ships, and the room is described after the seed lands.
        desc = function()
            return 'A wide room under a banner in letters the colour of a struck match: '
                .. 'play immersive, multiplayer, pure-text games. Below it, one orange '
                .. 'button, worn smooth in the middle. Shelves down the near wall hold '
                .. spell(SITE.games.count) .. ' boxed worlds. On a plinth in the centre '
                .. 'someone has left a terminal running a small MUD; you lean over it, '
                .. 'and lean over it, and lean over it.'
        end,
        exits = { north = 'news', down = 'vault', west = 'commons' },
        things = {
            {
                name = 'the banner',
                keys = { 'banner', 'letters', 'headline', 'sign' },
                look = function()
                    say(C.text, 'Hand-set, and slightly uneven if you stand close. It says what the ',
                        'real front page says: the games are text, the text is multiplayer, ',
                        'and forty years in, that is still enough.')
                    say(C.dim, 'It is also, word for word, ', link('the front page', URL.home), C.dim .. '.')
                end,
            },
            {
                name = 'the orange button',
                keys = { 'button', 'orange button', 'download button' },
                grab = press,
                look = function()
                    say(C.text, 'Big. Orange. Worn smooth by a great many hands. It reads DOWNLOAD ',
                        'MUDLET, and it is not a metaphor for anything — it does that.')
                    local build = currentBuild()
                    if build then
                        say(C.dim, 'Pressing it downloads ', build.what,
                            ', which is the one for the machine you are reading this on.')
                    end
                    say(C.dim, 'You could ', cmd('press it', 'press button', 'press the button', C.dim),
                        C.dim, ', or go ', cmd('down', 'down', 'go down', C.dim),
                        C.dim, ' and take the crates one at a time.')
                end,
            },
            {
                name = 'the shelves',
                keys = { 'shelves', 'shelf', 'boxes', 'worlds', 'games', 'muds' },
                url = URL.download,
                look = function()
                    local named = someGames(12)
                    local rest = SITE.games.count - #named
                    say(C.text, spellCap(SITE.games.count), ' boxes, a hostname stencilled on ',
                        'each lid: ', table.concat(named, ', '),
                        rest > 0 and (', and ' .. spell(rest) .. ' more.') or '.')
                    say(C.dim, 'Mudlet ships with the lot. None of them will ask you for a port number.')
                end,
            },
            {
                name = 'a terminal on a plinth',
                keys = { 'terminal', 'plinth', 'screen', 'demo' },
                look = function()
                    say(C.text, 'The screen shows a room description. The room is this one. In it, ',
                        'someone is leaning over a terminal.')
                    say(C.text, 'You have found the demo. It is a real Mudlet — Lua, PCRE2, the ',
                        'lot — compiled to WebAssembly and running in this browser tab. ',
                        'Nothing is connected to anything. Every line you type is answered by ',
                        'a Lua package ', thousands(SCRIPT_LINES), ' lines long.')
                    say(C.dim, 'Prove it: ',
                        cmd('lua echo("hello from Lua")', 'lua echo("hello from Lua")',
                            'run it in the demo\'s own Lua VM', C.dim))
                end,
            },
        },
    },

    news = {
        title = 'The News Room',
        desc = function()
            return 'A small office that is almost entirely cork. Notices go up faster than '
                .. 'anyone takes them down and the layers have gone geological — the bottom '
                .. 'of the board is from 2008. A drawer beneath is labelled ARCHIVE, amended '
                .. 'in a second pen to ' .. SITE.news.count .. ' AND RISING.'
        end,
        exits = { south = 'home' },
        things = {
            {
                name = 'the notice board',
                keys = { 'board', 'notice board', 'noticeboard', 'notices', 'news' },
                -- Whatever the site has posted most recently, in the site's own
                -- order. The dates are right-aligned into a fixed column so the
                -- headlines line up whether the day is one digit or two.
                look = function()
                    local posts = SITE.news.posts
                    if #posts == 0 then
                        say(C.text, 'The board is bare, which has never happened before.')
                        return
                    end
                    say(C.text, spellCap(#posts), ' notices near the top, still crisp:')
                    say()
                    for _, post in ipairs(posts) do
                        say(C.dim, ('%11s  '):format(post.date), link(post.title, post.url))
                        local by = post.author ~= '' and post.author or nil
                        local blurb = post.blurb ~= '' and post.blurb or nil
                        if by or blurb then
                            say(C.text, '    ' .. (by or ''), C.desc,
                                blurb and ((by and ' — ' or '    ') .. blurb) or '')
                        end
                    end
                end,
            },
            {
                name = 'the archive drawer',
                keys = { 'drawer', 'archive', 'label' },
                url = URL.news,
                look = function()
                    say(C.text, 'It opens much further than a drawer that size should. Every release ',
                        'since 2008 is in here, in order, and somebody has plainly been ',
                        'keeping it that way.')
                    say(C.dim, 'The whole of it: ', link('mudlet.org/news', URL.news), C.dim .. '.')
                end,
            },
        },
    },

    vault = {
        title = 'The Release Vault',
        desc = function()
            return 'Cold, dry, very well swept. Four crates stand on trestles, each stencilled '
                .. 'with a platform and a weight, each with a long number chalked on the lid '
                .. 'that nobody has ever checked. A fifth stands apart by the stairs, its lid '
                .. 'loose and its contents faintly warm. On the wall, in chalk: '
                .. SITE.release.version .. ' — ' .. SITE.release.date_loud .. '.'
        end,
        exits = { up = 'home' },
        things = {
            {
                name = 'windows',
                keys = { 'windows', 'win', 'exe' },
                url = URL.win,
                crate = 'windows',
                look = function()
                    say(C.text, 'Pine, sealed ', SITE.release.date, '. An installer, signed — ',
                        'the certificate was donated, which is the sort of thing that happens ',
                        'to projects people like.')
                    crateLines('windows')
                end,
            },
            {
                name = 'macos',
                keys = { 'macos', 'mac', 'intel', 'x86', 'x86_64' },
                url = URL.macx,
                crate = 'macos',
                look = function()
                    say(C.text, 'The older of the two Macs — Intel, x86_64, sealed the same ',
                        'morning as the rest of them.')
                    crateLines('macos')
                end,
            },
            {
                name = 'silicon',
                keys = { 'silicon', 'apple silicon', 'arm', 'arm64', 'm1', 'm2' },
                url = URL.macarm,
                crate = 'silicon',
                look = function()
                    say(C.text, 'Apple Silicon, built native — no translation layer, no apology on ',
                        'startup.')
                    crateLines('silicon')
                end,
            },
            {
                name = 'linux',
                keys = { 'linux', 'appimage', 'ubuntu', 'debian' },
                url = URL.linux,
                crate = 'linux',
                look = function()
                    say(C.text, 'The one that is not really an installer: an AppImage. Put it ',
                        'somewhere permanent and run it from there. It is the Ubuntu answer, ',
                        'the Debian answer and the "my distribution is unusual" answer, all ',
                        'under one lid.')
                    crateLines('linux')
                end,
            },
            {
                name = 'ptb',
                keys = { 'ptb', 'fifth', 'fifth crate', 'warm crate', 'test', 'public test build' },
                url = URL.ptb,
                heavy = 'the Public Test Build snapshots',
                look = function()
                    say(C.text, 'The Public Test Build: everything that has landed since ',
                        SITE.release.version, ', unsealed by design. The people who open this ',
                        'crate are the reason the other four are safe to open.')
                    say(C.dim, '  ', link('Public Test Build snapshots', URL.ptb))
                end,
            },
            {
                name = 'the chalk mark',
                keys = { 'chalk', 'wall', 'older', 'mark', 'trestles' },
                url = URL.download,
                look = function()
                    say(C.text, 'Under the chalk mark, faintly, are all the chalk marks before it — ',
                        'every version back to the early ones, still on the shelf, still ',
                        'downloadable by anyone with a reason.')
                    say(C.dim, 'The full list: ', link('mudlet.org/download', URL.download), C.dim .. '.')
                end,
            },
        },
    },

    commons = {
        title = 'The Commons',
        desc = 'Four doors and a cabinet, in a room that is otherwise all noticeboard. '
            .. 'None of the doors are locked. Two stand ajar, and you can hear the arguing '
            .. 'from here — amiably, about tabs. The cabinet is enormous and alphabetical.',
        exits = { east = 'home', north = 'workshop', west = 'makers' },
        things = {
            {
                name = 'the forum door',
                keys = { 'forum', 'forum door', 'forums' },
                url = URL.forum,
                look = function()
                    say(C.text, 'Slow, thorough, searchable, twenty years deep. Somebody had your ',
                        'exact problem in 2013 and somebody else solved it underneath in 2014.')
                    say(C.dim, '  ', link('forums.mudlet.org', URL.forum))
                end,
            },
            {
                name = 'the wiki door',
                keys = { 'wiki', 'wiki door', 'manual', 'docs' },
                url = URL.wiki,
                look = function()
                    say(C.text, 'The manual. Every function, every event, every argument, with ',
                        'examples that run. This is the door people mean when they say Mudlet ',
                        'is approachable.')
                    say(C.dim, '  ', link('wiki.mudlet.org', URL.wiki))
                end,
            },
            {
                name = 'the discord door',
                keys = { 'discord', 'discord door', 'chat' },
                url = URL.discord,
                look = function()
                    say(C.text, 'Over five thousand people behind it. A dozen will answer a Lua ',
                        'question inside a minute, and a few of them wrote the function you are ',
                        'asking about.')
                    say(C.dim, '  ', link('the invite', URL.discord))
                end,
            },
            {
                name = 'the workshop door',
                keys = { 'workshop', 'workshop door', 'github', 'source', 'contribute' },
                url = URL.github,
                look = function()
                    say(C.text, 'C++ and Lua, GPL, and open to anyone who can be bothered. There are ',
                        'good first issues stacked on the desk by the door, and whoever reviews ',
                        'your patch has been doing this since 2008.')
                    say(C.dim, 'It is also the one door here that goes somewhere: the workshop ',
                        'is ', cmd('north', 'north', 'go north', C.dim), C.dim, ', and there is ',
                        'somebody in it who knows what landed this week.')
                    say(C.dim, '  ', link('github.com/Mudlet/Mudlet', URL.github))
                end,
            },
            {
                name = 'the cabinet',
                keys = { 'cabinet', 'packages', 'drawers' },
                url = URL.packages,
                look = function()
                    say(C.text, '229 drawers from 123 authors: mappers, tabbed chat, curing systems, ',
                        'a keepalive pinger, and one that turns :) into an emoji. Mudlet ',
                        'installs any of them from its own command line.')
                    say(C.dim, '  mpkg install carto        ', link('packages.mudlet.org', URL.packages))
                end,
            },
            {
                name = 'the noticeboard',
                keys = { 'noticeboard', 'names' },
                url = URL.makers,
                look = function()
                    say(C.text, 'Names, mostly. A few dozen of them across a decade and change, ',
                        'pinned in no order anyone can explain and never taken down.')
                    say(C.dim, 'The people themselves are through the door ',
                        cmd('west', 'west', 'go west', C.dim), C.dim, '. The roll of them: ',
                        link('the makers', URL.makers), C.dim .. '.')
                end,
            },
        },
    },

    -- The only room whose contents are not written down anywhere in this file.
    -- Its two answers are fetched from GitHub when they are asked for; the
    -- machinery, and the reasoning, are under "The Workshop" further down.
    workshop = {
        title = 'The Workshop',
        desc = 'Long, high-windowed, and smelling of solder and yesterday\'s coffee. Down '
            .. 'one wall a bench of work half-done and carefully labelled; down the other, '
            .. 'a board of everything nobody has got to yet, which is a good deal longer. '
            .. 'By the window stands a slanted desk with this week\'s date on it, and a '
            .. 'clerk keeping the book open at that page.',
        exits = { south = 'commons' },
        things = {
            {
                name = 'the clerk',
                keys = { 'clerk', 'bookkeeper' },
                npc = true,
                presence = function()
                    say(C.text, 'A ', cmd('clerk', 'look clerk', 'look at the clerk', C.say),
                        C.text, ' stands at the slanted desk, one pen behind the ear, writing ',
                        'up the week.')
                end,
                look = function()
                    say(C.text, 'Sleeves rolled, one pen behind the ear and a second one in use. ',
                        'The clerk keeps the week — what has landed, who landed it, and what is ',
                        'still open — and writes none of it down until somebody asks, which ',
                        'they maintain is the only way to keep a book honest.')
                    say(C.dim, 'Ask ', cmd('about this week', 'ask about this week',
                            'what has landed in the last seven days', C.dim),
                        C.dim, ', or ', cmd('about what is open', 'ask about issues',
                            'what is still open', C.dim), C.dim, '.')
                    say(C.dim, 'Both answers come off github.com at the moment you ask for them. ',
                        'Nothing else in this world is that fresh.')
                end,
            },
            {
                name = 'the week\'s book',
                keys = { 'book', 'week', 'desk', 'ledger', 'commits' },
                look = function() D.week() end,
            },
            {
                name = 'the board',
                keys = { 'board', 'issues', 'bugs', 'wall' },
                url = URL.issues,
                look = function() D.issues() end,
            },
            {
                name = 'the bench',
                keys = { 'bench', 'pulls', 'patches', 'work' },
                url = URL.pulls,
                look = function()
                    say(C.text, 'Work half-done, each piece labelled with the name of whoever put ',
                        'it down and the date they meant to come back to it. Some of the labels ',
                        'are old. All of it is out in the open, which is the whole difference ',
                        'between this bench and most benches.')
                    say(C.dim, '  ', link('the open pull requests', URL.pulls))
                end,
            },
        },
    },

    makers = {
        title = 'Makers Hall',
        desc = 'A hall with one very long table down the middle and more chairs than there '
            .. 'are people — some of the names cut into the chairbacks have not been sat in '
            .. 'for a decade, and nobody has moved them out. At the head of the table a sage '
            .. 'keeps the ledger of everyone who ever built anything here.',
        exits = { east = 'commons' },
        -- The sage greets you a beat after you arrive rather than in the same
        -- breath as the room description: a greeting printed with the room
        -- reads as part of the furniture, and one that lands two seconds later
        -- reads as somebody noticing you came in.
        enter = function()
            D.enterTimer = tempTimer(2, function() D.greet() end)
        end,
        things = {
            {
                name = 'the sage',
                keys = { 'sage', 'keeper' },
                -- The only living thing in the world. It is listed on its
                -- own line rather than among the furniture, and the *name*
                -- carries the colour it speaks in while the sentence around it
                -- stays narration — colouring the whole line gold said "this
                -- line is different" without saying which word was the sage.
                npc = true,
                presence = function()
                    say(C.text, 'A ', cmd('sage', 'look sage', 'look at the sage', C.say),
                        C.text, ' sits at the head of the table, one hand flat on the ledger.')
                end,
                look = function()
                    say(C.text, 'Patient, and slightly ink-stained. The sage has read every commit ',
                        'message since 2008 and remembers who wrote what, which is a stranger ',
                        'kind of memory than it sounds.')
                    say(C.dim, 'Ask about anyone: ',
                        cmd('ask about vadi', 'ask about vadi', 'ask the sage about Vadim Peretokin', C.dim),
                        C.dim, ', or ', cmd('ask about everyone', 'ask about everyone',
                            'the whole ledger', C.dim), C.dim .. '.')
                end,
            },
            {
                name = 'the ledger',
                keys = { 'ledger', 'book', 'roll' },
                url = URL.makers,
                look = function() D.ledger() end,
            },
            {
                name = 'the chairs',
                keys = { 'chairs', 'chair', 'table', 'names', 'chairbacks' },
                look = function()
                    say(C.text, spellCap(SITE.makers.count), ' of them, near enough. Some are ',
                        'pulled right in and warm; ',
                        'some have been pushed back since 2010 and hold a name and one very ',
                        'specific contribution, like a build script or an installer, which is ',
                        'how open source remembers people.')
                    say(C.dim, 'The sage will tell you about any of them by name.')
                end,
            },
        },
    },
}

-- The ledger ------------------------------------------------------------------
--
-- Mudlet's own About box, in the About box's order: the eight who carry the
-- project first, then everyone else. The descriptions are cut to a line or two
-- each — the source of truth is Mudlet's `aboutMakers` list in C++, and the
-- full text lives there and on mudlet.org/the-makers, which every entry here
-- links out to. Handles are the public GitHub ones from that same list; the
-- email addresses in it are deliberately not copied here.
--
-- `keys` is what the sage answers to: first name, surname, handle, whatever a
-- visitor is likely to type.
local MAKERS = {
    { big = true, name = 'Heiko Köhn', keys = { 'heiko', 'kohn', 'köhn' },
      line = 'Original author and first project lead. Wrote the core of Mudlet, then retired.' },
    { big = true, name = 'Vadim Peretokin', gh = 'vadi2', keys = { 'vadi', 'vadim', 'peretokin', 'vadi2' },
      line = 'Head of the project since Heiko retired, and with it from the very beginning. '
          .. 'GUI design, the homepage, the manual, the wiki, the Lua API, the installers for '
          .. 'every platform, and most of the talking to the outside world.' },
    { big = true, name = 'Stephen Lyons', gh = 'SlySven', keys = { 'stephen lyons', 'lyons', 'slysven', 'sly' },
      line = 'Since 2013, poking the C++ and the GUI with a pointy stick and patching the holes '
          .. 'he finds. Mudlet speaks languages other than American English because the spelling '
          .. 'differences got on his nerves.' },
    { big = true, name = 'Damian Monogue', gh = 'demonnic', keys = { 'damian', 'monogue', 'demonnic' },
      line = 'Former maintainer of the early Windows and macOS packages. Runs the server and '
          .. 'helps the project in a dozen quieter ways.' },
    { big = true, name = 'Florian Scheel', gh = 'keneanung', keys = { 'florian', 'scheel', 'keneanung' },
      line = 'Much of the db: interface and the event system, and years of answering people.' },
    { big = true, name = 'Leris', gh = 'Kebap', keys = { 'leris', 'kebap' },
      line = 'Makes Mudlet, the website and the wiki readable whatever language you speak — and '
          .. 'talks the genre up wherever he goes.' },
    -- `own`: the site's copy of this one is the About dialog's, and the About
    -- dialog cannot say "the thing you are standing in" — it is not a demo of
    -- Mudlet Web running inside Mudlet Web. So this line stays written here and
    -- the seed leaves it alone.
    { big = true, own = true, name = 'Piotr Wilczynski', gh = 'Delwing',
      keys = { 'piotr', 'wilczynski', 'delwing' },
      line = 'Joined in 2020. Reworked much of the 2D mapper and added a great deal of the Lua '
          .. 'API. Outside the client: Mudlet Web — which is the thing you are standing in — the '
          .. 'documentation extract behind editor autocompletion, and the tools that share maps '
          .. 'online.' },
    { big = true, name = 'Zooka', gh = 'ZookaOnGit', keys = { 'zooka' },
      line = 'Joined in 2023 and works everywhere: script editor, preferences, package manager, '
          .. 'mapper, plenty of Lua. Wrote the Tutorial profile and keeps the package repository.' },

    { name = 'Ahmed Charles', gh = 'ahmedcharles', keys = { 'ahmed', 'charles', 'ahmedcharles' },
      line = 'CMake and the Visual C++ build, and a great deal of code quality and memory work.' },
    { name = 'Chris Mitchell', gh = 'Chris7', keys = { 'chris', 'mitchell', 'chris7' },
      line = 'Shared modules, so script packages can be shared between profiles; a viewer for '
          .. 'Lua variables, and mapper improvements.' },
    { name = 'Ben Carlsen', keys = { 'ben carlsen', 'carlsen' },
      line = 'Wrote the first macOS installer and maintained the Mac version.' },
    { name = 'Ben Smith', keys = { 'ben smith', 'smith' },
      line = 'Joined December 2009, having been around much longer. Contributed to the Lua API '
          .. 'and used to maintain it.' },
    { name = 'Blaine von Roeder', keys = { 'blaine', 'roeder' },
      line = 'Joined December 2009. Lua API, bugfix patches, and release management for 1.0.5.' },
    { name = 'Bruno Bigras', keys = { 'bruno', 'bigras' },
      line = 'Wrote the original cmake build script, and a number of patches after it.' },
    { name = 'Carter Dewey', keys = { 'carter', 'dewey' },
      line = 'Contributed to the Lua API.' },
    { name = 'Erik Pettis', gh = 'Oneymus', keys = { 'erik', 'pettis', 'oneymus' },
      line = 'Wrote Vyzor, a GUI manager for Mudlet.' },
    { name = 'Harrison', gh = 'Harrison-Teeg', keys = { 'harrison' },
      line = 'Brought the 3D mapper back to life — camera controls, lighting, proper geometry '
          .. 'for z-squished rooms — and fixed a batch of console annoyances.' },
    { name = 'ItsTheFae', gh = 'Kae', keys = { 'fae', 'thefae', 'itsthefae', 'kae' },
      line = 'Rejuvenated the website in 2017 and keeps the spambots off the fora, plus some '
          .. 'useful C++ core work. Prefers a little anonymity.' },
    { name = 'Ian Adkins', gh = 'dicene', keys = { 'ian', 'adkins', 'dicene' },
      line = 'Joined in 2017; C++ and Lua contributions since.' },
    { name = 'James Younquist', keys = { 'james', 'younquist' },
      line = 'Wrote Geyser, the layout manager, in March 2010 — the thing that makes GUI '
          .. 'scripting bearable.' },
    { name = 'John Dahlström', keys = { 'john dahlstrom', 'dahlström', 'dahlstrom' },
      line = 'Helped develop and debug the Lua API.' },
    { name = 'John McKisson', gh = 'jmckisson', keys = { 'john mckisson', 'mckisson', 'jmckisson' },
      line = 'Implemented MMCP, so Mudlet can join MudMaster chat networks, and a range of '
          .. 'console and Lua API fixes.' },
    { name = 'Karsten Bock', gh = 'Beliaar', keys = { 'karsten', 'bock', 'beliaar' },
      line = 'Several improvements and new features for Geyser.' },
    { name = 'Leigh Stillard', keys = { 'leigh', 'stillard' },
      line = 'The original author of the Windows installer.' },
    { name = 'Maksym Grinenko', keys = { 'maksym', 'grinenko' },
      line = 'The manual, forum help, and a hand in GUI design and documentation.' },
    { name = 'Manuel Wegmann', gh = 'Edru2', keys = { 'manuel', 'wegmann', 'edru', 'edru2' },
      line = 'Built much of the GUI toolkit you script with between 2020 and 2022: Adjustable '
          .. 'Containers, Geyser ScrollBox, animated labels, Geyser in UserWindows, the dark '
          .. 'theme toggle and the Package Exporter rework.' },
    { name = 'Mike Conley', gh = 'mpconley', keys = { 'mike', 'conley', 'mpconley' },
      line = 'Joined in 2018 and looks after nearly everything Mudlet plays or negotiates — '
          .. 'MCMP media, sound and video, closed captioning, MXP, OSC 8 hyperlinks, text '
          .. 'encodings — plus multi-window support with drag-and-drop tabs.' },
    { name = 'Stephen Hansen', keys = { 'stephen hansen', 'hansen' },
      line = 'The database Lua API, which made databases far easier to use, and one of the '
          .. 'original macOS installers.' },
    { name = 'Thorsten Wilms', keys = { 'thorsten', 'wilms' },
      line = 'Designed the logo, the splash screen, the about dialog, the website, and a pile '
          .. 'of icons and badges.' },
    { name = 'Tim Johnson', gh = 'atari2600tim', keys = { 'tim', 'johnson', 'atari2600tim' },
      line = 'Joined in 2020 and made Mudlet work far better with screen readers, alongside '
          .. 'secure IRC, Discord improvements, and a batch of editor shortcuts.' },
}


local function findMaker(noun)
    if noun == '' then return nil end
    for _, m in ipairs(MAKERS) do
        for _, key in ipairs(m.keys) do
            if key == noun then return m end
        end
    end
    for _, m in ipairs(MAKERS) do
        if m.name:lower():find(noun, 1, true) then return m end
        for _, key in ipairs(m.keys) do
            if key:find(noun, 1, true) then return m end
        end
    end
end

-- Code points, not bytes: two of these names carry an umlaut, and #str would
-- count it twice and knock the second column a space out of true. Continuation
-- bytes (0x80-0xBF) are the ones that do not start a character.
local function ulen(str)
    local n = 0
    for i = 1, #str do
        local byte = str:byte(i)
        if byte < 128 or byte > 191 then n = n + 1 end
    end
    return n
end

-- Names you have already asked about go dim, the way a visited link does, so
-- the ledger quietly keeps score and the eight-and-twenty-two stops being a
-- wall of identical text after the third question.
local function makerLink(m)
    local seen = D.asked and D.asked[m.name]
    return cmd(m.name, 'ask about ' .. m.keys[1],
        seen and ('you have asked about ' .. m.name) or ('ask the sage about ' .. m.name),
        seen and C.dim or C.text)
end

function D.tell(m)
    say()
    D.asked = D.asked or {}
    D.asked[m.name] = true
    say(C.text, m.big and 'The sage does not need the ledger for this one.'
        or 'The sage turns to a page nearer the back.')
    say(C.say, '"', m.name, '. ', m.line, '"')

    -- What is left after the answer: where to find them, and the reminder that
    -- the page is one of thirty.
    local tail = { C.dim, '  ' }
    if m.gh then
        tail[#tail + 1] = link('@' .. m.gh, 'https://github.com/' .. m.gh,
            m.name .. ' on GitHub')
        tail[#tail + 1] = C.dim .. '   ·   '
    end
    tail[#tail + 1] = cmd(spell(#MAKERS - 1) .. ' other names', 'look ledger',
        'turn back to the ledger', C.dim)
    say(unpack(tail))
end

-- The eight the About box lists first, then a count of the rest. Printing all
-- thirty by default is a wall of names in a console this size; `everyone` is
-- there for anyone who wants the wall.
function D.ledger()
    say()
    say(C.text, 'The sage lets the ledger fall open at the front.')
    local carried = 0
    for _, m in ipairs(MAKERS) do if m.big then carried = carried + 1 end end
    say(C.say, '"' .. spellCap(SITE.makers.count) .. ', near enough. These '
        .. spell(carried) .. ' carry it."')
    say()
    for _, m in ipairs(MAKERS) do
        if m.big then say(C.text, '  ', makerLink(m)) end
    end
    say()
    local rest = 0
    for _, m in ipairs(MAKERS) do if not m.big then rest = rest + 1 end end
    say(C.dim, 'And ', tostring(rest), ' more behind them, from the first cmake script to the ',
        '3D mapper. ', cmd('Ask for everyone', 'ask about everyone', 'the whole ledger', C.dim),
        C.dim, ', or read the roll at ', link('mudlet.org/the-makers', URL.makers), C.dim .. '.')
end

-- Three columns, read downwards, so the eight stay together at the top of the
-- first one. The whole point of the list is seeing all thirty at once: one
-- column is thirty lines of scrolling, two is fifteen, three is ten and fits a
-- hero-sized console without moving. COL_W is the longest name — Blaine von
-- Roeder, seventeen — plus a gap, which puts the widest row at 59 characters
-- against the ~68 this console wraps at.
--
-- The padding is its own segment because each name is a link, and a link is
-- its own decho call — there is no string in the middle of the line to pad.
local COLS, COL_W = 3, 20

function D.everyone()
    say()
    -- One line of preamble, not two: every line spent here is a name pushed
    -- off the top of the console.
    say(C.say, '"All of them, then."', C.text, ' The sage turns the ledger round.')
    say()
    local rows = math.ceil(#MAKERS / COLS)
    for r = 1, rows do
        local line = { C.dim, '  ' }
        for c = 0, COLS - 1 do
            local m = MAKERS[r + c * rows]
            if m then
                line[#line + 1] = makerLink(m)
                if MAKERS[r + (c + 1) * rows] then
                    line[#line + 1] = C.dim ..
                        string.rep(' ', math.max(1, COL_W - ulen(m.name)))
                end
            end
        end
        say(unpack(line))
    end
    say()
    say(C.dim, spellCap(#MAKERS), ' names, one client. ',
        link('mudlet.org/the-makers', SITE.makers.url))
end

-- Fired by the room's entry timer. It checks the room again because two
-- seconds is long enough to walk back out, and the sage should not call after
-- you from another room.
function D.greet()
    D.enterTimer = nil
    if D.here ~= 'makers' then return end

    local asked = 0
    for _ in pairs(D.asked or {}) do asked = asked + 1 end

    if not D.metSage then
        D.metSage = true
        say(C.text, 'The sage marks their place with a finger and looks up.')
        say(C.say, '"Ask me about any of them. That is the whole of the job."')
    elseif asked == 0 then
        say(C.say, '"Back again."', C.text, ' The sage turns to a fresh page, hopefully.')
    elseif asked >= #MAKERS then
        say(C.say, '"All ' .. spell(#MAKERS) .. ' of them."', C.text,
            ' The sage closes the ledger, ',
            'looking rather pleased.')
    else
        say(C.say, '"You have asked after ' .. spell(asked) .. ' of them,"', C.text,
            ' the sage says. ', C.say, '"There are ' .. spell(#MAKERS - asked) .. ' more."')
    end
end

-- The Workshop ----------------------------------------------------------------
--
-- One room in this world does not know what it says until somebody asks.
--
-- Everywhere else the facts arrive with the seed: one request, at boot, and the
-- prose is settled before the visitor has read a word of it. What landed this
-- week cannot work that way and should not — it is not a fact about mudlet.org
-- at all, it is a fact about the repository, and it is already out of date by
-- the time the page has finished loading. So the clerk asks GitHub, from the
-- visitor's own browser, at the moment the question is put.
--
-- That is possible because api.github.com allows any origin and wants no token
-- for either of these routes, and because mudlet-web falls back to its proxy
-- for any origin that refuses a direct fetch. It costs the visitor sixty
-- requests an hour on the commits route and ten a minute on the search — a
-- budget nobody can spend by hand, and the one who tries is told so in as many
-- words rather than shown an error.
--
-- Nothing here is required. Every failure has a line in the clerk's own voice,
-- every one of those lines carries the link to the page it could not read, and
-- the room reads the same whether GitHub answered or not.

local GH = {
    -- Commits on the default branch in the last seven days. per_page=100 is
    -- the ceiling, and a week that beats it is reported as "a hundred and
    -- more": a second page would be another request for a number nobody is
    -- checking against anything.
    commits = 'https://api.github.com/repos/Mudlet/Mudlet/commits?per_page=100&since=',
    -- Counting open issues without counting pull requests takes the search
    -- API — the repo endpoint's open_issues_count adds the two together, and a
    -- clerk who says six hundred when five hundred are issues is worse than a
    -- clerk who says nothing at all.
    issues  = 'https://api.github.com/search/issues?per_page=1'
        .. '&q=repo%3AMudlet%2FMudlet+is%3Aissue+is%3Aopen',
    first   = 'https://api.github.com/search/issues?per_page=1'
        .. '&q=repo%3AMudlet%2FMudlet+is%3Aissue+is%3Aopen+label%3A%22good+first+issue%22',
}

-- Long enough for a cold lookup on a phone, short enough that nobody decides
-- the room is broken. The browser's fetch has no timeout of its own, so a
-- request left hanging would otherwise leave the clerk mid-sentence for good.
local FETCH_WAIT = 8

-- An answer is kept for five minutes. Asking twice is a thing visitors do to a
-- live number — looking again is how you check that it is one — and the second
-- ask should cost the room nothing.
local FRESH = 300

local function settleJob(reason, body)
    local job = D.job
    if not job then return end
    D.job = nil
    if job.timer then killTimer(job.timer) end
    job.done(reason, body)
end

local function bindHTTP()
    if D.httpBound then return end
    D.httpBound = true
    -- Anonymous handlers hear every request the profile makes, the seed's
    -- included, so both ends filter: these on the url in flight, the seed's on
    -- its own route.
    registerAnonymousEventHandler('sysGetHttpDone', function(_, url, body)
        if D.job and url == D.job.url then settleJob(nil, body) end
    end)
    registerAnonymousEventHandler('sysGetHttpError', function(_, message, url)
        if not (D.job and url == D.job.url) then return end
        -- Both of GitHub's rate limits answer 403, and the secondary one
        -- sometimes 429. Everything else — no route, no network, a proxy
        -- having a bad afternoon — is one thing from where the visitor is
        -- standing, and gets the one other line.
        local code = tostring(message or ''):match('HTTP (%d+)')
        settleJob((code == '403' or code == '429') and 'limit' or 'wire')
    end)
end

-- One request in flight. A second question asked while the first is still out
-- replaces it: the old answer is dropped rather than printed late, underneath
-- a line it no longer belongs to.
local function fetch(url, done)
    bindHTTP()
    if D.job and D.job.timer then killTimer(D.job.timer) end
    local job = { url = url, done = done }
    D.job = job
    job.timer = tempTimer(FETCH_WAIT, function()
        if D.job == job then settleJob('wire') end
    end)
    getHTTP(url, { Accept = 'application/vnd.github+json' })
end

local function decode(body)
    local ok, data = pcall(yajl.to_value, body)
    if ok and type(data) == 'table' then return data end
end

-- An answer that lands after the visitor has walked out belongs to a room they
-- are not in. The sage's greeting checks the same thing for the same reason: a
-- clerk should not call after you from another room.
local function stillHere()
    return D.here == 'workshop'
end

-- What the clerk says when GitHub does not answer. Two lines, because the two
-- reasons are genuinely different and one of them is the visitor's own doing:
-- an unauthenticated caller gets sixty requests an hour per address, and a
-- browser that has been sitting on this page all afternoon can spend them.
local function apology(reason, label, url)
    if reason == 'limit' then
        say(C.say, '"That is my lot."', C.text, ' The clerk sets the pen down, ',
            'unembarrassed. ', C.say, '"They count how much I am allowed to say, and I ',
            'have said all of it this hour. Come back when it has forgotten me — or read ',
            'it off the wall yourself, it is the same wall."')
    else
        say(C.say, '"Not written up."', C.text, ' The clerk shuts the book on a thumb. ',
            C.say, '"The wire between here and the workshop proper is down. It happens. ',
            'It is all posted outside, if you cannot wait for me."')
    end
    say(C.dim, '  ', link(label, url))
end

-- GitHub flags its own bots and not the ones a project keeps: dependabot
-- arrives typed Bot, and mudlet-machine-account — which pushes the translation
-- and release commits — arrives as an ordinary user with a machine's name. The
-- clerk counts hands and machines apart because the difference is the
-- interesting half of the number.
local function isMachine(who, kind)
    return kind == 'Bot' or who:find('%[bot%]') ~= nil
        or who:find('machine%-account') ~= nil
end

-- Cut to n code points, not n bytes: commit messages carry umlauts like
-- everything else here, and a byte cut lands in the middle of one.
local function clip(str, n)
    if ulen(str) <= n then return str end
    local count = 0
    for i = 1, #str do
        local byte = str:byte(i)
        if byte < 128 or byte > 191 then
            count = count + 1
            if count > n - 1 then return str:sub(1, i - 1) .. '…' end
        end
    end
    return str
end

-- "2026-08-31T03:39:17Z" -> "three hours ago".
--
-- os.time reads a table as *local* time and the API answers in UTC, so the
-- difference between the two clocks is added back on. Without it the clerk is
-- wrong by the visitor's own offset, which is worst in exactly the places
-- nobody testing this lives.
--
-- Both tables have their isdst dropped, which is the whole trick: os.date
-- fills it in from the *current* season, and a table that says "not summer
-- time" put through os.time in July is an hour out. Absent, it is worked out
-- from the date in hand — which is the right answer for both of these.
local function ago(iso)
    local y, mo, d, h, mi, s = tostring(iso):match('(%d+)-(%d+)-(%d+)T(%d+):(%d+):(%d+)')
    if not y then return nil end
    local at = os.time({ year = tonumber(y), month = tonumber(mo), day = tonumber(d),
        hour = tonumber(h), min = tonumber(mi), sec = tonumber(s) })
    local utc = os.date('!*t')
    utc.isdst = nil
    local skew = os.difftime(os.time(), os.time(utc))
    local secs = os.difftime(os.time(), at + skew)
    if secs < 90 then return 'just now' end
    if secs < 5400 then return spell(math.floor(secs / 60 + 0.5)) .. ' minutes ago' end
    if secs < 129600 then return spell(math.floor(secs / 3600 + 0.5)) .. ' hours ago' end
    return spell(math.floor(secs / 86400 + 0.5)) .. ' days ago'
end

-- The clerk's two answers, printed. Neither prints the sentence that introduces
-- it: that line belongs to the ask, which knows whether the clerk had to go and
-- look or was still holding the number.
local function tellWeek(week)
    if week.count == 0 then
        say(C.say, '"Nothing in seven days."', C.text, ' Neither worried nor surprised. ',
            C.say, '"It goes in bursts. Somebody will break something tonight."')
    else
        local hands = spell(week.people) .. (week.people == 1 and ' hand' or ' hands')
        local machines = spell(week.machines)
            .. (week.machines == 1 and ' machine' or ' machines')
        -- A week with nothing but machines in it is a real week — the release
        -- and translation robots push on their own — and "from no hands" is
        -- not a sentence anybody says out loud.
        local from = 'from ' .. hands
        if week.people == 0 then
            from = 'and every one of them from a machine'
        elseif week.machines > 0 then
            from = from .. ' and ' .. machines
        end
        say(C.say, '"', week.more and 'A hundred and more' or spellCap(week.count),
            ' in seven days,"', C.text, ' the clerk says, ', C.say, '"', from, '."')
        if week.last then
            say(C.dim, '  ', week.last.when and (week.last.when .. ' — ') or '',
                link(week.last.title, week.last.url, week.last.title), C.dim,
                ', by ', week.last.who)
        end
    end
    say(C.dim, '  ', link('the full run of it', URL.commits))
end

local function tellIssues(open)
    if open.first and open.first > 0 then
        say(C.say, '"', tostring(open.count), ' open,"', C.text, ' the clerk says, ',
            C.say, '"and ', spell(open.first), ' of them marked for anyone who fancies a ',
            'first go at it."')
        say(C.dim, '  ', link('the ' .. spell(open.first) .. ' of them', URL.firstish))
    elseif open.first then
        say(C.say, '"', tostring(open.count), ' open, and not one of them marked for a first ',
            'go."', C.text, ' The clerk sounds mildly impressed. ', C.say, '"Somebody has ',
            'been taking them."')
    else
        say(C.say, '"', tostring(open.count), ' open."', C.text, ' Said the way you would say ',
            'the tide is in.')
    end
    say(C.dim, '  ', link('the board itself', URL.issues))
end

-- What landed in the last seven days: one request, and everything the clerk
-- says about it is counted out of the answer rather than read off a field.
function D.week()
    say()
    if D.weekly and os.difftime(os.time(), D.weekly.at) < FRESH then
        say(C.text, 'The clerk does not need to look twice.')
        tellWeek(D.weekly)
        return
    end

    local ok, since = pcall(os.date, '!%Y-%m-%dT%H:%M:%SZ', os.time() - 7 * 86400)
    if not ok or type(since) ~= 'string' then
        apology('wire', 'the full run of it', URL.commits)
        return
    end

    say(C.text, 'The clerk pulls the week down off the wall by the window.')
    fetch(GH.commits .. since, function(reason, body)
        if not stillHere() then return end
        local list = not reason and decode(body) or nil
        if not list then
            apology(reason or 'wire', 'the full run of it', URL.commits)
            return
        end

        local week = { at = os.time(), count = #list, more = #list >= 100,
            people = 0, machines = 0 }
        local seen = {}
        for _, entry in ipairs(list) do
            local account = type(entry.author) == 'table' and entry.author or nil
            local committed = type(entry.commit) == 'table' and entry.commit or {}
            local authored = type(committed.author) == 'table' and committed.author or {}
            -- The login where GitHub matched the email to an account, and the
            -- name off the commit itself where it did not. Either way it is
            -- the same person twice under one key.
            local who = account and account.login or authored.name or '?'
            if not seen[who] then
                seen[who] = true
                if isMachine(tostring(who):lower(), account and account.type) then
                    week.machines = week.machines + 1
                else
                    week.people = week.people + 1
                end
            end
            if not week.last and type(committed.message) == 'string' then
                -- os.date('!*t') is the one call in this file that assumes a
                -- full os library under the wasm; the clause it feeds is
                -- decoration, so it is asked for rather than relied on.
                local timed, when = pcall(ago, authored.date)
                week.last = {
                    title = clip(committed.message:match('^[^\r\n]*') or '', 56),
                    who   = tostring(who),
                    when  = timed and type(when) == 'string' and when or nil,
                    url   = type(entry.html_url) == 'string' and entry.html_url or URL.commits,
                }
            end
        end

        D.weekly = week
        tellWeek(week)
    end)
end

-- What is still open: two requests, chained, because the search API counts one
-- query at a time. The second one — the issues a stranger could take — is the
-- reason this room is worth walking into, but it is not worth losing the first
-- number over, so a failure there answers with what came back.
function D.issues()
    say()
    if D.open and os.difftime(os.time(), D.open.at) < FRESH then
        say(C.text, 'The clerk points at the board without turning round.')
        tellIssues(D.open)
        return
    end

    say(C.text, 'The clerk runs a thumb down the board.')
    fetch(GH.issues, function(reason, body)
        if not stillHere() then return end
        local data = not reason and decode(body) or nil
        local count = data and tonumber(data.total_count)
        if not count then
            apology(reason or 'wire', 'the board itself', URL.issues)
            return
        end
        fetch(GH.first, function(reason2, body2)
            if not stillHere() then return end
            local firsts = not reason2 and decode(body2) or nil
            D.open = { at = os.time(), count = count,
                first = firsts and tonumber(firsts.total_count) or nil }
            tellIssues(D.open)
        end)
    end)
end

-- The clerk answers to two subjects and admits to the rest. The nouns are
-- matched on a substring the way the room's things are, so "this week",
-- "commits" and "what landed" all reach the same book.
local function about(noun, words)
    for _, word in ipairs(words) do
        if noun:find(word, 1, true) then return true end
    end
end

function D.askClerk(noun)
    if noun == '' or noun == 'clerk' then
        say()
        say(C.text, 'The clerk looks up, pen still moving.')
        say(C.say, '"Two things I keep: what has landed this week, and what is still open."')
        say(C.dim, '  ', cmd('ask about this week', 'ask about this week',
                'the last seven days, off github.com', C.dim),
            C.dim, '   ·   ', cmd('ask about issues', 'ask about issues',
                'what is still open, off github.com', C.dim))
        return
    end
    if about(noun, { 'week', 'commit', 'land', 'chang', 'new', 'recent' }) then D.week() return end
    if about(noun, { 'issue', 'bug', 'open', 'board', 'first', 'todo', 'help' }) then D.issues() return end

    say()
    if findMaker(noun) then
        say(C.say, '"Names are the sage\'s book, not mine,"', C.text, ' the clerk says, ',
            'nodding through the wall. ', C.say, '"Two doors round, past the arguing."')
    else
        say(C.say, '"Not in my book."', C.text, ' The clerk shrugs, not unkindly.')
    end
    say(C.dim, 'What the clerk does keep: ',
        cmd('this week', 'ask about this week', 'what has landed', C.dim), C.dim, ', and ',
        cmd('what is open', 'ask about issues', 'what is still open', C.dim), C.dim, '.')
end

-- Two people in this world answer questions, and they keep different books: the
-- sage has the past, off a fixed ledger, and the clerk has this week, off the
-- network. Which one answers is which room you are standing in.
function D.ask(noun)
    if D.here == 'workshop' then D.askClerk(noun) return end
    if D.here ~= 'makers' then
        say(C.text, 'There is nobody here to ask. Both books are kept off the commons: ',
            'the sage\'s in Makers Hall to the west of it, the clerk\'s in the Workshop ',
            'to the north.')
        return
    end
    if noun == '' then D.ledger() return end
    if noun == 'everyone' or noun == 'everybody' or noun == 'all' then D.everyone() return end

    local m = findMaker(noun)
    if not m then
        say(C.say, '"Not in this ledger," the sage says, "and I would know."')
        say(C.dim, 'Try a name, or ', cmd('ask about everyone', 'ask about everyone',
            'the whole ledger', C.dim), C.dim .. '.')
        return
    end
    D.tell(m)
end

D.here = D.here or 'home'

-- The map --------------------------------------------------------------------
--
-- Not a drawing of a map: Mudlet's own mapper, small, in the corner of the
-- console. "A real mapper" is one of the six claims the page makes two
-- sections down, so the demo may as well be running one — six rooms is a
-- tiny map, but it is the same widget, the same map database and the same
-- centerview() that a twelve-thousand-room game drives.

local AREA = 'mudlet.org'

-- Fixed ids, and the area is torn down before it is rebuilt: a profile keeps
-- its map across package reinstalls, so anything createRoomID()'d would stack
-- up a fresh set of rooms every time the world was rewritten.
local ROOM = { home = 1, news = 2, commons = 3, vault = 4, makers = 5, workshop = 6 }

-- Laid out as the site reads: news above the front page, the vault below it,
-- the commons to the west. All six sit on one z level, including the vault —
-- a cellar on its own level would be a map with one room on it, which is
-- accurate and useless at this size.
--
-- The workshop goes north of the commons, which puts it inside the bounding box
-- the other five already made: three squares by three, and the map frames the
-- same way it did with a corner empty.
local PLACE = {
    home     = { x =  0, y =  0 },
    news     = { x =  0, y =  1 },
    commons  = { x = -1, y =  0 },
    vault    = { x =  0, y = -1 },
    makers   = { x = -2, y =  0 },
    workshop = { x = -1, y =  1 },
}

-- The vault is `down`/`up` only. It sits one square below the front page so it
-- reads as a cellar, but the exit is a stair, so the mapper draws the stair
-- markers and no line — a line would say you can walk south into it.
local MAP_EXITS = {
    { 'home', 'news',    'north', 'south' },
    { 'home', 'commons', 'west',  'east'  },
    { 'home', 'vault',   'down',  'up'    },
    { 'commons', 'makers', 'west',  'east'  },
    { 'commons', 'workshop', 'north', 'south' },
}

local ENV = 200   -- one custom env colour, so the rooms are the site's orange

function D.buildMap()
    local existing = getAreaTable()[AREA]
    if existing then deleteArea(existing) end
    local area = addAreaName(AREA)

    setCustomEnvColor(ENV, 245, 108, 39, 255)
    setMapBackgroundColor(30, 24, 19)   -- the hero terminal's own ground

    -- The map info panel — area, room id, coordinate ranges — is on by default
    -- and, at this size, is the entire widget. Four rooms need no coordinate
    -- readout; the room name is already the line above the map.
    for label in pairs(getMapInfo()) do disableMapInfo(label) end

    for key, id in pairs(ROOM) do
        if roomExists(id) then deleteRoom(id) end
        addRoom(id)
        setRoomArea(id, area)
        setRoomCoordinates(id, PLACE[key].x, PLACE[key].y, 0)
        setRoomName(id, D.rooms[key].title)
        setRoomEnv(id, ENV)
    end
    for _, e in ipairs(MAP_EXITS) do
        setExit(ROOM[e[1]], ROOM[e[2]], e[3])
        setExit(ROOM[e[2]], ROOM[e[1]], e[4])
    end
    updateMap()
end

-- Floating in the corner rather than docked: Mudlet Web's own mapper dock is a
-- tab in a side panel, which is most of a hero-sized console. Nothing is
-- reserved, so the console keeps its full width and the map covers the tail of
-- the first few lines, where a game would put its HUD.
--
-- Sized off the window and re-run on resize: this console is ~560px wide in the
-- homepage hero and full-screen in the standalone demo, and the same square has
-- to be a reasonable share of both.
local MAP_MAX = 140
-- Clear of the right edge by more than it looks like it needs: the console's
-- scrollbar sits in that gutter, and 8px put the map's border against it.
local MAP_INSET = 18

-- A status bar across the top: the room you are in on the left, the map control
-- on the right. It is reserved space rather than an overlay — setBorderTop keeps
-- the console's text below it, so nothing ever runs under the map button.
--
-- Console borders are a *saved profile setting*. The right gutter is cleared
-- out loud because an earlier version of this world reserved one for a docked
-- map, and a returning visitor still has it: 156px of a 560px console, wrapping
-- every line short for a map that is no longer there.
--
local BAR_H = 30

setBorderRight(0)
setBorderTop(BAR_H)

-- The map is Mudlet's own, floating in the corner over the text, and it starts
-- closed behind a small icon.
--
-- Opt-in rather than always-on because of where this runs. Below 900px Mudlet
-- Web puts every *window* behind a tab strip, and the homepage hero is a ~560px
-- frame: a map that opened itself there would cost 40px of a 290px console
-- before the visitor had asked for anything. Closed, it costs one 22px icon.
--
-- The icon is a Geyser label, and labels are overlays rather than windows —
-- the one thing that floats at every width.
local ICON = { open = '#f56c27', shut = '#9c8f7c' }

local function iconCss(open)
    return string.format([[
        background-color: rgba(43,35,27,0.92);
        border: 1px solid %s;
        border-radius: 4px;
        color: %s;
        font-family: ui-monospace, monospace;
        font-size: 9px;
        letter-spacing: 0.08em;
        text-align: center;
        padding-top: 4px;
    ]], open and ICON.open or '#3a2f24', open and ICON.open or ICON.shut)
end

local function mapSize()
    local w = getMainWindowSize()
    return math.max(96, math.min(MAP_MAX, math.floor(w * 0.3))), w
end

function D.mapPaint()
    if not D.mapOpen then return end
    local size, w = mapSize()
    createMapper(w - size - MAP_INSET, 38, size, size)
    -- centerview before zoom: zoom without an area id applies to the *current*
    -- area, and until the view is on a room there is no current area for it to
    -- land on.
    centerview(ROOM[D.here])
    -- Zoom counts rooms across the view, so it goes *up* as the widget shrinks:
    -- 5 keeps all six rooms in frame from *any* of them at MAP_MAX, and this
    -- holds that framing at every size in between. Tighter looks better from
    -- the front page and drops a room off the edge from the news room, which is
    -- the wrong trade for a map of six things.
    setMapZoom(5 * MAP_MAX / size)
    updateMap()
end

function D.mapToggle()
    D.mapOpen = not D.mapOpen
    if D.mapOpen then
        D.mapPaint()
    else
        -- Not closeMapWidget(): that hides the *dockable* map widget, a
        -- different window from the one createMapper opens, and on an embedded
        -- mapper it does nothing at all. Collapsing to zero is how Geyser.Mapper
        -- hides its own embedded case.
        local size, w = mapSize()
        createMapper(w - size - MAP_INSET, 38, 0, 0)
    end
    if D.icon then D.icon:setStyleSheet(iconCss(D.mapOpen)) end
end

-- The bar, built once. Three labels in stacking order: the strip itself, the
-- room name written into it, and the map control on top at the right. Geyser
-- holds all three against their edges as the window resizes — the width of the
-- name is '-52px', which is Geyser for "the whole bar, less the button".
function D.chrome()
    if D.bar then return end

    D.bar = Geyser.Label:new({
        name = 'demoBar', x = 0, y = 0, width = '100%', height = BAR_H .. 'px',
    })
    D.bar:setStyleSheet([[
        background-color: #2b231b;
        border-bottom: 1px solid #3a2f24;
    ]])

    D.name = Geyser.Label:new({
        name = 'demoRoomName', x = '12px', y = 0,
        width = '-52px', height = BAR_H .. 'px',
    })
    D.name:setStyleSheet([[
        background-color: rgba(0,0,0,0);
        color: #9c8f7c;
        font-family: ui-monospace, monospace;
        font-size: 11px;
        padding-top: 9px;
    ]])

    D.icon = Geyser.Label:new({
        name = 'demoMapIcon',
        x = '-42px', y = '5px', width = '34px', height = '20px',
    })
    -- A word, not a glyph. At 20px a map pictogram is a smudge, and this is
    -- the only control the embed has — it has to say what it does.
    D.icon:echo('map')
    D.icon:setStyleSheet(iconCss(false))
    -- A function, not the name of one: Geyser takes a string here in Mudlet,
    -- but this build never resolves it and the click goes nowhere.
    D.icon:setClickCallback(function() D.mapToggle() end)
end

-- The bar's left half. Prefixed with the site's own wordmark mark, so the
-- client's status line and the page's logo read as the same voice.
function D.barName()
    if D.name and D.rooms[D.here] then
        D.name:echo('&gt; ' .. D.rooms[D.here].title)
    end
end

function D.mapWidget()
    D.chrome()
    D.bindKeys()
    D.barName()
    -- Geyser holds the bar against its edges by itself; the mapper is a window
    -- and has to be repositioned by hand.
    D.mapPaint()
end

-- How the map follows you, when it is open at all.
function D.mapHere()
    if D.mapOpen then centerview(ROOM[D.here]) end
end

-- Verbs ----------------------------------------------------------------------

local DIRS = {
    n = 'north', s = 'south', e = 'east', w = 'west', u = 'up', d = 'down',
    north = 'north', south = 'south', east = 'east', west = 'west',
    up = 'up', down = 'down',
}

local function find(noun)
    if noun == '' then return nil end
    for _, thing in ipairs(D.rooms[D.here].things) do
        for _, key in ipairs(thing.keys) do
            if key == noun then return thing end
        end
    end
    -- Second pass, so 'look windows crate' finds the crate and 'look forum'
    -- finds the forum door without every phrasing being spelled out above.
    for _, thing in ipairs(D.rooms[D.here].things) do
        for _, key in ipairs(thing.keys) do
            if noun:find(key, 1, true) or key:find(noun, 1, true) then return thing end
        end
    end
end

function D.look(noun)
    local room = D.rooms[D.here]
    if noun and noun ~= '' then
        local thing = find(noun)
        if not thing then
            say(C.text, 'You do not see that here.')
            return
        end
        say()
        thing.look()
        return
    end

    D.mapHere()
    D.barName()

    say()
    say(C.room, room.title)
    -- A description is a string when nothing in it moves, and a function when
    -- it quotes something the site was asked about — evaluated here, at print
    -- time, so a seed that lands late still reads correctly the next look.
    say(C.desc, type(room.desc) == 'function' and room.desc() or room.desc)

    -- Both lists print as links, so the world can be played entirely by
    -- clicking: every noun looks at itself, every exit walks.
    --
    -- Things and people are listed apart, the way a MUD does it: the furniture
    -- goes in one "Here:" line, and anyone alive gets their own sentence
    -- underneath in their own colour. A sage listed between a ledger and a set
    -- of chairs reads as another piece of furniture.
    local line = { C.text, 'Here: ' }
    local first = true
    for _, thing in ipairs(room.things) do
        if not thing.npc then
            if not first then line[#line + 1] = C.text .. ', ' end
            line[#line + 1] = cmd(thing.name, 'look ' .. thing.keys[1],
                'look at ' .. thing.name)
            first = false
        end
    end
    line[#line + 1] = C.text .. '.'
    if not first then say(unpack(line)) end

    for _, thing in ipairs(room.things) do
        if thing.npc then thing.presence() end
    end

    local names = {}
    for dir in pairs(room.exits) do names[#names + 1] = dir end
    table.sort(names)
    line = { C.exit, 'Exits: ' }
    for i, dir in ipairs(names) do
        if i > 1 then line[#line + 1] = C.exit .. ', ' end
        line[#line + 1] = cmd(dir, dir, 'go ' .. dir, C.exit)
    end
    say(unpack(line))
end

-- Every way into a room goes through here: a typed direction, a clicked exit,
-- or a numpad key.
function D.enter(key)
    -- A greeting still pending belongs to the room being left.
    if D.enterTimer then killTimer(D.enterTimer) D.enterTimer = nil end
    D.here = key
    D.look()
    local enter = D.rooms[key].enter
    if enter then enter() end
end

function D.go(dir)
    local dest = D.rooms[D.here].exits[dir]
    if not dest then
        say(C.text, 'Nothing that way but page margin.')
        return
    end
    D.enter(dest)
end

-- Numpad walking is *spatial*, not lexical. The vault is reached by 'down',
-- but it is drawn one square below the front page — so pressing 2 goes there,
-- because the map is what the visitor is looking at when they reach for the
-- keypad. The exit still has to exist; this reads the map, it does not
-- teleport through walls.
function D.step(dx, dy)
    local from = PLACE[D.here]
    if not from then return end
    for key, place in pairs(PLACE) do
        if place.x == from.x + dx and place.y == from.y + dy then
            for _, dest in pairs(D.rooms[D.here].exits) do
                if dest == key then D.enter(key) return end
            end
        end
    end
    say(C.text, 'Nothing that way but page margin.')
end

-- Numpad 8/4/6/2 walk the map, 9 and 3 take the exits called up and down for
-- anyone whose hands already know that layout, and 5 looks.
--
-- The keypad modifier is the whole trick: numpad 8 and the 8 above the letters
-- send the same key code, and only Qt::KeypadModifier tells them apart. Bound
-- as tempKeys because the package reinstalls on every version bump and
-- permKeys would pile up copies.
local KEYPAD = 0x20000000

function D.bindKeys()
    if D.keys then return end
    D.keys = {}
    local function bind(code, fn) D.keys[#D.keys + 1] = tempKey(KEYPAD, code, fn) end
    bind(56, function() D.step( 0,  1) end)   -- 8, north on the map
    bind(50, function() D.step( 0, -1) end)   -- 2, south
    bind(52, function() D.step(-1,  0) end)   -- 4, west
    bind(54, function() D.step( 1,  0) end)   -- 6, east
    bind(57, function() D.go('up') end)       -- 9
    bind(51, function() D.go('down') end)     -- 3
    bind(53, function() D.look() end)         -- 5
end

-- take/press is the click. It opens the page in a new tab — openUrl is a popup
-- the browser may or may not allow from a keypress, so the line printed
-- alongside carries the link too and the visitor never ends up looking at a
-- refusal.
--
-- Except for the crates. Those URLs are installers, and 130 MiB arriving in the
-- downloads tray because somebody typed a word at a demo is not a thing to do
-- to a visitor: a `heavy` thing hands over the link and lets them decide.
--
-- The orange button is that rule the other way up. It is labelled DOWNLOAD
-- MUDLET and pressing it is the visitor asking for precisely that, so it
-- carries its own `grab` and starts the real download.
function D.take(noun)
    local thing = find(noun)
    if not thing then
        say(C.text, 'You cannot take that.')
        return
    end
    if thing.grab then
        thing.grab()
        return
    end
    if not thing.url then
        say(C.text, 'It stays where it is.')
        return
    end
    -- A crate's weight and its link are the release the site is serving, not
    -- the version it was stencilled with when this room was written.
    if thing.crate then
        local build = SITE.release.builds[thing.crate] or {}
        say(C.text, 'You get both hands under the lid. ',
            link(crateLabel(thing.crate), build.url or thing.url))
        return
    end
    if thing.heavy then
        say(C.text, 'You get both hands under the lid. ', link(thing.heavy, thing.url))
        return
    end
    openUrl(thing.url)
    say(C.text, 'It opens ', link('in a new tab', thing.url),
        C.text, ' — if your browser let it.')
end

function D.help()
    say()
    say(C.sys, 'mudlet.org, walked instead of scrolled. Six rooms, one website.')
    say()
    say(C.text, '  ', cmd('look', 'look', 'look around'),
        C.text, '           ', C.desc, 'look around you')
    say(C.text, '  look <thing>   ', C.desc, 'look closer — a banner, a crate, a door')
    say(C.text, '  n s e w u d    ', C.desc, 'walk. Everything leads back to the front page')
    say(C.text, '  numpad         ', C.desc, '8 4 6 2 walk the map, 5 looks')
    say(C.text, '  take <thing>   ', C.desc, 'some of these are downloads, and taking them works')
    say(C.text, '  ', cmd('lua <code>', 'lua echo("hello from Lua")', 'try it'),
        C.text, '     ', C.desc, 'this is a real Lua runtime')
    say(C.text, '  ask <name>     ', C.desc, 'the sage in Makers Hall knows who built what')
    say(C.text, '  ', cmd('ask this week', 'ask about this week',
        'the clerk counts the last seven days'),
        C.text, '  ', C.desc, 'the clerk in the Workshop reads it off GitHub, live')
    say()
    -- The one place a colour tag is written by hand rather than by say(): the
    -- sample has to be closed with </u> as well as opened, because everything
    -- in one say() is a single decho and the underline would otherwise run to
    -- the end of the line.
    say(C.dim, 'Underlined words are clickable. ', C.room .. U, 'Orange ones',
        '</u>' .. C.dim, ' open the real mudlet.org.')
end

local REPLIES = {
    inventory = function()
        say(C.text, 'You are carrying one (1) web browser, several tabs you meant to close, ',
            'and a growing suspicion that this is a real MUD client.')
    end,
    xyzzy = function()
        say(C.text, 'Nothing happens. This is a faithful implementation.')
    end,
    who = function()
        say(C.text, 'A few dozen people across a decade and change, most of whom are behind ',
            'the doors west of the front page.')
        say(C.dim, '  ', link('the makers', URL.makers))
    end,
    score = function()
        say(C.text, 'You have visited a website. It is going well.')
    end,
}
REPLIES.i = REPLIES.inventory
REPLIES.inv = REPLIES.inventory
REPLIES.credits = REPLIES.who

function D.input(raw)
    local cmdline = (raw or ''):lower():gsub('^%s+', ''):gsub('%s+$', '')
    if cmdline == '' then return end

    local verb, rest = cmdline:match('^(%S+)%s*(.*)$')
    rest = rest:gsub('^at%s+', ''):gsub('^the%s+', '')

    if DIRS[verb] and rest == '' then
        D.go(DIRS[verb])
    elseif verb == 'go' or verb == 'walk' then
        if DIRS[rest] then D.go(DIRS[rest]) else say(C.text, 'Which way?') end
    elseif verb == 'look' or verb == 'l' or verb == 'examine' or verb == 'x'
        or verb == 'read' or verb == 'inspect' then
        D.look(rest)
    elseif verb == 'take' or verb == 'get' or verb == 'press' or verb == 'push'
        or verb == 'open' or verb == 'click' or verb == 'download' then
        -- 'download' on its own is the front page's big orange button, wherever
        -- the visitor happens to be standing.
        if rest == '' and verb == 'download' then
            press(true)
        else
            D.take(rest)
        end
    elseif verb == 'ask' or verb == 'about' then
        -- 'ask sage about X' and 'ask X' both land here. There is at most one
        -- person to ask in any room, so who is being asked is optional — and
        -- naming the wrong one of the two is not a correction worth printing.
        D.ask((rest:gsub('^sage%s*', ''):gsub('^clerk%s*', ''):gsub('^about%s+', '')))
    elseif verb == 'help' or verb == 'commands' or verb == '?' then
        D.help()
    elseif REPLIES[verb] then
        REPLIES[verb]()
    else
        say(C.text, "Nothing happens. Try ", cmd('look', 'look', 'look around'),
            C.text, ', or ', cmd('help', 'help', 'the short list of commands'), C.text .. '.')
    end
end

-- Boot -----------------------------------------------------------------------
--
-- The page draws these same two lines while the client is still loading, in
-- the same colour, metrics and dot rhythm, and drops its copy the moment these
-- appear — so the handover is invisible and what the visitor sees is one client
-- connecting once. Change these and the markup in prototype/index.src.html
-- (.term__boot) has to change with them.
-- Asking the site -------------------------------------------------------------
--
-- The endpoint is site-relative on purpose. The demo is framed from the site's
-- own origin — a hard requirement for unrelated reasons, see demo/README.md —
-- so the REST root is reachable from wherever the frame is served without the
-- world having to be told what site it is in. Two spellings of the one route
-- because /wp-json/ only exists with pretty permalinks on; the query form is
-- what a plain install answers, and is tried when the first fails.
local SEED_URLS = {
    '/wp-json/mudlet/v1/demo',
    '/?rest_route=/mudlet/v1/demo',
}

-- The longest the first room will wait for an answer, on top of the 1.5s the
-- connect animation runs anyway. Everywhere there is no WordPress the request
-- fails immediately and none of this is spent.
local SEED_WAIT = 1.5

-- Nothing that arrives is trusted and nothing is required: a field replaces
-- what the world already says only when it turns up with something in it. A
-- site answering half of this — an older theme, a plugin somebody deactivated —
-- leaves the other half as written prose rather than as a hole in a sentence.
local function fill(into, from, keys)
    if type(from) ~= 'table' then return end
    for _, key in ipairs(keys) do
        local value = from[key]
        if value ~= nil and value ~= '' then into[key] = value end
    end
end

-- A notice needs a headline and somewhere to go; the date, the author and the
-- clause under it are decoration and may be missing.
local function notices(posts)
    local clean = {}
    if type(posts) ~= 'table' then return clean end
    for _, post in ipairs(posts) do
        if type(post) == 'table' and type(post.title) == 'string'
            and type(post.url) == 'string' and post.title ~= '' then
            clean[#clean + 1] = {
                date   = tostring(post.date or ''),
                title  = post.title,
                author = tostring(post.author or ''),
                blurb  = tostring(post.blurb or ''),
                url    = post.url,
            }
        end
    end
    return clean
end

-- The ledger, rewritten from the site's copy of it.
--
-- Somebody the hall has never heard of gets a chair; somebody it knows keeps
-- their name, their handle and the nouns the sage answers to, and takes the
-- site's sentence in place of the one written here. Who is on the project now
-- comes across too, so the eight at the front of the ledger are the eight the
-- About dialog currently draws large.
--
-- The exception is an entry marked `own`: a line that talks about this demo
-- from inside it is one the About dialog cannot make, and there is no version
-- of it upstream to take instead.
--
-- Matched on the full name first — the sage's `keys` are deliberately loose,
-- and a loose match is the wrong tool when the question is "is this the same
-- person" rather than "who does this visitor mean".
local function inLedger(name)
    local wanted = name:lower()
    for _, m in ipairs(MAKERS) do
        if m.name:lower() == wanted then return m end
    end
    return findMaker(wanted)
end

local function roster(people)
    if type(people) ~= 'table' then return end
    for _, person in ipairs(people) do
        local name = type(person) == 'table' and person.name or nil
        if type(name) == 'string' and name ~= '' then
            local line = type(person.line) == 'string' and person.line ~= ''
                and person.line or nil
            local gh = type(person.github) == 'string' and person.github ~= ''
                and person.github or nil
            local known = inLedger(name)
            if known then
                if line and not known.own then known.line = line end
                known.gh = known.gh or gh
                if type(person.core) == 'boolean' then known.big = person.core end
            else
                local keys = {}
                for word in name:lower():gmatch('%a+') do keys[#keys + 1] = word end
                if gh then keys[#keys + 1] = gh:lower() end
                MAKERS[#MAKERS + 1] = {
                    big  = person.core ~= false,
                    name = name,
                    gh   = gh,
                    keys = keys,
                    line = line or 'In the credits, and the ledger has not caught up '
                        .. 'with what they have done yet.',
                }
            end
        end
    end
end

function D.applySeed(data)
    if type(data) ~= 'table' then return end

    local release = data.release
    if type(release) == 'table' then
        fill(SITE.release, release, { 'version', 'date', 'date_short', 'date_loud', 'url' })
        if type(release.builds) == 'table' then
            for name, build in pairs(SITE.release.builds) do
                fill(build, release.builds[name], { 'label', 'size', 'short', 'url' })
            end
        end
    end

    fill(SITE.games, data.games, { 'count', 'url' })
    if type(data.games) == 'table' and type(data.games.names) == 'table'
        and #data.games.names > 0 then
        SITE.games.names = data.games.names
    end

    fill(SITE.makers, data.makers, { 'count', 'url' })
    if type(data.makers) == 'table' then roster(data.makers.people) end

    fill(SITE.news, data.news, { 'count', 'url' })
    if type(data.news) == 'table' then
        local board = notices(data.news.posts)
        if #board > 0 then SITE.news.posts = board end
    end
end

-- One request, and one answer either way: whatever happens, D.settled ends up
-- true and the world stops waiting. The handlers filter on the route because
-- they are anonymous and would otherwise hear anything else that ever fetches.
function D.askSite()
    local attempt = 0

    local function settle()
        if D.settled then return end
        D.settled = true
        D.connected()
    end

    local function ask()
        attempt = attempt + 1
        if not SEED_URLS[attempt] then settle() return end
        getHTTP(SEED_URLS[attempt])
    end

    local function ours(url)
        return type(url) == 'string' and url:find('mudlet/v1/demo', 1, true) ~= nil
    end

    registerAnonymousEventHandler('sysGetHttpDone', function(_, url, body)
        if D.settled or not ours(url) then return end
        -- A site that answers with something other than JSON is a site that
        -- does not have this endpoint. That is not an error the visitor needs.
        local ok, data = pcall(yajl.to_value, body)
        if ok then D.applySeed(data) end
        settle()
    end)

    registerAnonymousEventHandler('sysGetHttpError', function(_, _, url)
        if D.settled or not ours(url) then return end
        ask()
    end)

    ask()
end

function D.boot()
    -- Before the first line, not at connect: the page holds an identical bar
    -- over this frame while the bundle loads, so the client must already have
    -- one when the cover lifts or the whole console would shift 30px at the cut.
    D.chrome()
    say(C.sys, 'mudlet web')
    say()
    say(C.sys, 'connecting')
    D.dot = 0
    D.dotTimer = tempTimer(0.3, function() D.dots() end, true)
    -- The connect animation doubles as the seed's window: the request goes out
    -- with the first dot, and the first room is printed once the animation has
    -- run its length *and* the site has answered or run out of time. Both
    -- timers call the same gate; whichever is last through it connects.
    D.askSite()
    tempTimer(1.5, function() D.animated = true D.connected() end)
    tempTimer(1.5 + SEED_WAIT, function() D.settled = true D.connected() end)
end

-- Rewrites the last line in place rather than printing another one: cursor to
-- the end, drop the line, print its replacement. The same three calls swap
-- "connecting..." for the connect line when the world is ready, so the line
-- the page was animating turns into the answer instead of scrolling away.
local function swapLastLine(...)
    moveCursorEnd()
    deleteLine()
    say(...)
end

function D.dots()
    D.dot = (D.dot % 3) + 1
    swapLastLine(C.sys, 'connecting' .. string.rep('.', D.dot))
end

function D.connected()
    if D.online or not (D.animated and D.settled) then return end
    D.online = true
    if D.dotTimer then killTimer(D.dotTimer) end
    swapLastLine(C.sys, '*** connected ***')
    -- The map arrives with the first room rather than during the connect
    -- animation: the page is holding an identical copy of those two lines over
    -- this console, and a mapper appearing under the cover would pop into view
    -- the moment it lifted.
    D.buildMap()
    D.mapWidget()
    registerAnonymousEventHandler('sysWindowResizeEvent', function() D.mapWidget() end)
    D.look()
    say()
    say(C.sys, 'This is Mudlet, running in your browser — and this is mudlet.org,')
    say(C.sys, 'laid out as a MUD. Click anything underlined, or type ',
        cmd('help', 'help', 'the short list of commands', C.sys), C.sys .. '.')
    D.probe()
end

-- Phase 0 probe. Four claims, printed rather than assumed: a plain .lua shipped
-- in the .mpackage is require()able from the profile VFS, so is a directory
-- through package.path's second pattern, so is a sibling one level down,
-- and an error raised inside any of them names the file and the line. Removed
-- with the probe files themselves once the split lands.
function D.probe()
    say()
    local ok, plain = pcall(require, 'mudlet-demo.probe')
    say(C.sys, 'probe plain   : ', tostring(ok), '  ', tostring(ok and plain.where or plain))
    local okDir, dir = pcall(require, 'mudlet-demo.probedir')
    say(C.sys, 'probe initlua : ', tostring(okDir), '  ', tostring(okDir and dir.where or dir))
    say(C.sys, 'probe sibling : ', tostring(okDir), '  ', tostring(okDir and dir.leaf or '-'))
    if ok then
        say(C.sys, 'probe error   : ', tostring(select(2, pcall(plain.boom))))
    end
end

tempTimer(0.4, function() D.boot() end)
