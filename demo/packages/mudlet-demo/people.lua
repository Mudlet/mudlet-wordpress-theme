-- The sage's book -------------------------------------------------------------
--
-- Everyone who ever built Mudlet, and everything the sage in Makers Hall says
-- out of that list. The room itself is in rooms/makers.lua.

local D = demo
local core = require('mudlet-demo.core')
local URL = require('mudlet-demo.urls')
local SITE = require('mudlet-demo.site')
local C, say, link, cmd = core.C, core.say, core.link, core.cmd
local spell, spellCap = core.spell, core.spellCap

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

-- Read downwards, so the eight stay together at the top of the first column.
-- The whole point of the list is seeing all thirty at once: one column is
-- thirty lines of scrolling, two is fifteen, three is ten and fits a
-- hero-sized console without moving.
--
-- Three is the ceiling, though, not the answer. This console is a panel in a
-- web page: the hero wraps at ~68 columns on a laptop and at half that on a
-- phone, and three columns there is 59 characters of layout folded into a
-- 34-character console — every row wrapping, which is the one thing a column
-- layout cannot survive. So ask the console how wide it is and print what
-- fits. Reading downwards is why the count has to be known before the first
-- row is built rather than discovered while building it.
--
-- The padding is its own segment because each name is a link, and a link is
-- its own decho call — there is no string in the middle of the line to pad.
--
-- How many columns actually fit is core.columns, which the shelf of crates
-- under the Release Vault asks the same question of. Three is the ceiling here,
-- not the answer: at the width of a phone hero three columns is 59 characters
-- of layout folded into a 34-character console, every row wrapping, which is
-- the one thing a column layout cannot survive.
local MAX_COLS = 3

-- Derived, not typed, so the column stays true if someone adds a longer name
-- to the ledger above. Blaine von Roeder, seventeen, at the time of writing.
local LONGEST = 0
for _, m in ipairs(MAKERS) do LONGEST = math.max(LONGEST, ulen(m.name)) end

-- A name is the whole of a cell here, and the indent is two spaces.
local function layout()
    return core.columns(LONGEST, MAX_COLS, 2)
end

function D.everyone()
    say()
    -- One line of preamble, not two: every line spent here is a name pushed
    -- off the top of the console.
    say(C.say, '"All of them, then."', C.text, ' The sage turns the ledger round.')
    say()
    local cols, gap = layout()
    local pad = LONGEST + gap
    local rows = math.ceil(#MAKERS / cols)
    for r = 1, rows do
        local line = { C.dim, '  ' }
        for c = 0, cols - 1 do
            local m = MAKERS[r + c * rows]
            if m then
                line[#line + 1] = makerLink(m)
                if MAKERS[r + (c + 1) * rows] then
                    line[#line + 1] = C.dim ..
                        string.rep(' ', math.max(1, pad - ulen(m.name)))
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

return { MAKERS = MAKERS, findMaker = findMaker, ulen = ulen }
