-- mudlet.org, walked instead of scrolled.
--
-- Four rooms stand in for the site: the front page, the release downloads, the
-- news archive, and the places the site links out to. Everything a visitor can
-- open here opens the real page it is parodying — the descriptions carry the
-- links, so `look windows` is the download page's Windows row and the link in
-- it is that row's button.
--
-- Nothing has to be typed. Every noun, exit and suggested command prints as a
-- clickable link, so the whole world is playable with a mouse — which is the
-- point in a homepage hero, where most visitors will click before they type.
--
-- The content is typed out on purpose. Versions, weights, hashes and headlines
-- are copied from the live site (4.22.0, 6 July 2026); nothing in here assumes
-- that stays true, and feeding it from the page's own release and post data
-- later is a change to the tables at the top, not to the machinery below.
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
    ptb      = 'https://make.mudlet.org/snapshots/?platform=all&source=ptb',
    win      = 'https://www.mudlet.org/download/66/',
    macx     = 'https://www.mudlet.org/download/65/',
    macarm   = 'https://www.mudlet.org/download/64/',
    linux    = 'https://www.mudlet.org/download/63/',
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

-- The world ------------------------------------------------------------------
--
-- Each thing carries the noun the parser matches (keys[1] is canonical, the
-- rest are the phrasings a visitor might reach for), the name it is listed
-- under, an optional url that `take` opens, and what looking at it prints.

D.rooms = {
    home = {
        title = 'The Front Page',
        desc = 'A wide room under a banner in letters the colour of a struck match: '
            .. 'play immersive, multiplayer, pure-text games. Below it, one orange '
            .. 'button, worn smooth in the middle. Shelves down the near wall hold '
            .. 'forty-two boxed worlds. On a plinth in the centre someone has left a '
            .. 'terminal running a small MUD; you lean over it, and lean over it, and '
            .. 'lean over it.',
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
                url = URL.download,
                look = function()
                    say(C.text, 'Big. Orange. Worn smooth by a great many hands. It reads DOWNLOAD ',
                        'MUDLET, and it is not a metaphor for anything — it does that.')
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
                    say(C.text, 'Forty-two boxes, a hostname stencilled on each lid: Achaea, Aetolia, ',
                        'Lusternia, Imperian, Starmourn, Aardwolf, BatMUD, ZombieMUD, WoTMUD, ',
                        'Icesus, 3Kingdoms, God Wars II, and thirty more.')
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
                        'a Lua script three hundred lines long.')
                    say(C.dim, 'Prove it: ',
                        cmd('lua echo("hello from Lua")', 'lua echo("hello from Lua")',
                            'run it in the demo\'s own Lua VM', C.dim))
                end,
            },
        },
    },

    news = {
        title = 'The News Room',
        desc = 'A small office that is almost entirely cork. Notices go up faster than '
            .. 'anyone takes them down and the layers have gone geological — the bottom '
            .. 'of the board is from 2008. A drawer beneath is labelled ARCHIVE, amended '
            .. 'in a second pen to 178 AND RISING.',
        exits = { south = 'home' },
        things = {
            {
                name = 'the notice board',
                keys = { 'board', 'notice board', 'noticeboard', 'notices', 'news' },
                look = function()
                    say(C.text, 'Three notices near the top, still crisp:')
                    say()
                    say(C.dim, '  6 Jul 2026  ', link('4.22 — mapping, made friendlier', URL.post422))
                    say(C.text, '    Vadim Peretokin', C.desc,
                        ' — create, rename and delete map areas from the mapper itself.')
                    say(C.dim, '  6 Jul 2026  ', link('Mudlet 4.22.0', URL.post4220))
                    say(C.text, '    Vadim Peretokin', C.desc,
                        ' — a Configure Areas UI, lockable stub exits, fourteen fixes.')
                    say(C.dim, ' 13 Jun 2026  ', link('4.21 — Mudlet, made better', URL.post421))
                    say(C.text, '    ZookaOnGit', C.desc,
                        ' — 47 features, 77 improvements, 207 bug fixes.')
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
        desc = 'Cold, dry, very well swept. Four crates stand on trestles, each stencilled '
            .. 'with a platform and a weight, each with a long number chalked on the lid '
            .. 'that nobody has ever checked. A fifth stands apart by the stairs, its lid '
            .. 'loose and its contents faintly warm. On the wall, in chalk: 4.22.0 — 6 JULY 2026.',
        exits = { up = 'home' },
        things = {
            {
                name = 'windows',
                keys = { 'windows', 'win', 'exe' },
                url = URL.win,
                heavy = 'Mudlet 4.22.0 for Windows, 128.8 MiB',
                look = function()
                    say(C.text, 'Pine, sealed 6 July 2026. An installer, signed — the certificate ',
                        'was donated, which is the sort of thing that happens to projects ',
                        'people like.')
                    say(C.dim, '  sha256 b9f49c8d…9089bd39')
                    say(C.dim, '  ', link('Mudlet 4.22.0 for Windows, 128.8 MiB', URL.win))
                end,
            },
            {
                name = 'macos',
                keys = { 'macos', 'mac', 'intel', 'x86', 'x86_64' },
                url = URL.macx,
                heavy = 'Mudlet 4.22.0 for macOS (Intel), 131.7 MiB',
                look = function()
                    say(C.text, 'The older of the two Macs — Intel, x86_64, sealed the same July ',
                        'morning as the rest of them.')
                    say(C.dim, '  sha256 64371626…e64ac6d7')
                    say(C.dim, '  ', link('Mudlet 4.22.0 for macOS (Intel), 131.7 MiB', URL.macx))
                end,
            },
            {
                name = 'silicon',
                keys = { 'silicon', 'apple silicon', 'arm', 'arm64', 'm1', 'm2' },
                url = URL.macarm,
                heavy = 'Mudlet 4.22.0 for macOS (Apple Silicon), 130.1 MiB',
                look = function()
                    say(C.text, 'Apple Silicon, built native — no translation layer, no apology on ',
                        'startup.')
                    say(C.dim, '  sha256 54d97693…10261bdb')
                    say(C.dim, '  ', link('Mudlet 4.22.0 for macOS (Apple Silicon), 130.1 MiB', URL.macarm))
                end,
            },
            {
                name = 'linux',
                keys = { 'linux', 'appimage', 'ubuntu', 'debian' },
                url = URL.linux,
                heavy = 'Mudlet 4.22.0 for Linux, 170.4 MiB',
                look = function()
                    say(C.text, 'The heaviest of the four, and the only one that is not really an ',
                        'installer: an AppImage. Put it somewhere permanent and run it from ',
                        'there. It is the Ubuntu answer, the Debian answer and the "my ',
                        'distribution is unusual" answer, all under one lid.')
                    say(C.dim, '  sha256 8f10a78a…8a7c9040')
                    say(C.dim, '  ', link('Mudlet 4.22.0 for Linux, 170.4 MiB', URL.linux))
                end,
            },
            {
                name = 'ptb',
                keys = { 'ptb', 'fifth', 'fifth crate', 'warm crate', 'test', 'public test build' },
                url = URL.ptb,
                heavy = 'the Public Test Build snapshots',
                look = function()
                    say(C.text, 'The Public Test Build: everything that has landed since 4.22.0, ',
                        'unsealed by design. The people who open this crate are the reason the ',
                        'other four are safe to open.')
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
        exits = { east = 'home', west = 'makers' },
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
            D.enterTimer = tempTimer(2, [[demo.greet()]])
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
                    say(C.text, 'Thirty of them, near enough. Some are pulled right in and warm; ',
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
    { big = true, name = 'Piotr Wilczynski', gh = 'Delwing', keys = { 'piotr', 'wilczynski', 'delwing' },
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

-- The world spells its numbers out — forty-two boxes, thirty near enough — so
-- the sage does too rather than dropping digits into the middle of a sentence.
local NUMBERS = {
    'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten',
    'eleven', 'twelve', 'thirteen', 'fourteen', 'fifteen', 'sixteen', 'seventeen',
    'eighteen', 'nineteen', 'twenty', 'twenty-one', 'twenty-two', 'twenty-three',
    'twenty-four', 'twenty-five', 'twenty-six', 'twenty-seven', 'twenty-eight',
    'twenty-nine', 'thirty',
}

local function spell(n) return NUMBERS[n] or tostring(n) end

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
    tail[#tail + 1] = cmd('twenty-nine other names', 'look ledger',
        'turn back to the ledger', C.dim)
    say(unpack(tail))
end

-- The eight the About box lists first, then a count of the rest. Printing all
-- thirty by default is a wall of names in a console this size; `everyone` is
-- there for anyone who wants the wall.
function D.ledger()
    say()
    say(C.text, 'The sage lets the ledger fall open at the front.')
    say(C.say, '"Thirty, near enough. These eight carry it."')
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
    say(C.dim, 'Thirty names, one client. ', link('mudlet.org/the-makers', URL.makers))
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
        say(C.say, '"All thirty of them."', C.text, ' The sage closes the ledger, ',
            'looking rather pleased.')
    else
        say(C.say, '"You have asked after ' .. spell(asked) .. ' of them,"', C.text,
            ' the sage says. ', C.say, '"There are ' .. spell(#MAKERS - asked) .. ' more."')
    end
end

function D.ask(noun)
    if D.here ~= 'makers' then
        say(C.text, 'There is nobody here to ask. The sage keeps the ledger in Makers ',
            'Hall, ', cmd('west of the commons', 'west', 'go west', C.text), C.text .. '.')
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
-- sections down, so the demo may as well be running one — four rooms is a
-- tiny map, but it is the same widget, the same map database and the same
-- centerview() that a twelve-thousand-room game drives.

local AREA = 'mudlet.org'

-- Fixed ids, and the area is torn down before it is rebuilt: a profile keeps
-- its map across package reinstalls, so anything createRoomID()'d would stack
-- up a fresh set of rooms every time the world was rewritten.
local ROOM = { home = 1, news = 2, commons = 3, vault = 4, makers = 5 }

-- Laid out as the site reads: news above the front page, the vault below it,
-- the commons to the west. All four sit on one z level, including the vault —
-- a cellar on its own level would be a map with one room on it, which is
-- accurate and useless at this size.
local PLACE = {
    home    = { x =  0, y =  0 },
    news    = { x =  0, y =  1 },
    commons = { x = -1, y =  0 },
    vault   = { x =  0, y = -1 },
    makers  = { x = -2, y =  0 },
}

-- The vault is `down`/`up` only. It sits one square below the front page so it
-- reads as a cellar, but the exit is a stair, so the mapper draws the stair
-- markers and no line — a line would say you can walk south into it.
local MAP_EXITS = {
    { 'home', 'news',    'north', 'south' },
    { 'home', 'commons', 'west',  'east'  },
    { 'home', 'vault',   'down',  'up'    },
    { 'commons', 'makers', 'west',  'east'  },
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
    -- 5 keeps all four rooms in frame from *any* of them at MAP_MAX, and this
    -- holds that framing at every size in between. Tighter looks better from
    -- the front page and drops a room off the edge from the news room, which is
    -- the wrong trade for a map of four things.
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
    say(C.desc, room.desc)

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
function D.take(noun)
    local thing = find(noun)
    if not thing then
        say(C.text, 'You cannot take that.')
        return
    end
    if not thing.url then
        say(C.text, 'It stays where it is.')
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
    say(C.sys, 'mudlet.org, walked instead of scrolled. Four rooms, one website.')
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
            openUrl(URL.download)
            say(C.text, 'The button is on the front page, but it works from here: ',
                link('mudlet.org/download', URL.download), C.text .. '.')
        else
            D.take(rest)
        end
    elseif verb == 'ask' or verb == 'about' then
        -- 'ask sage about X' and 'ask X' both land here; the sage is the only
        -- one in the world to ask, so the noun in the middle is optional.
        D.ask((rest:gsub('^sage%s*', ''):gsub('^about%s+', '')))
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
function D.boot()
    -- Before the first line, not at connect: the page holds an identical bar
    -- over this frame while the bundle loads, so the client must already have
    -- one when the cover lifts or the whole console would shift 30px at the cut.
    D.chrome()
    say(C.sys, 'mudlet web')
    say()
    say(C.sys, 'connecting')
    D.dot = 0
    D.dotTimer = tempTimer(0.3, [[demo.dots()]], true)
    tempTimer(1.5, [[demo.connected()]])
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
    if D.dotTimer then killTimer(D.dotTimer) end
    swapLastLine(C.sys, '*** connected ***')
    -- The map arrives with the first room rather than during the connect
    -- animation: the page is holding an identical copy of those two lines over
    -- this console, and a mapper appearing under the cover would pop into view
    -- the moment it lifted.
    D.buildMap()
    D.mapWidget()
    registerAnonymousEventHandler('sysWindowResizeEvent', 'demo.mapWidget')
    D.look()
    say()
    say(C.sys, 'This is Mudlet, running in your browser — and this is mudlet.org,')
    say(C.sys, 'laid out as a MUD. Click anything underlined, or type ',
        cmd('help', 'help', 'the short list of commands', C.sys), C.sys .. '.')
end

tempTimer(0.4, [[demo.boot()]])
