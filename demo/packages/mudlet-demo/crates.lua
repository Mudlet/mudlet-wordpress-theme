-- The crates, and what is in one -------------------------------------------------
--
-- The cellar under the Release Vault (rooms/cellar.lua) is the world's own
-- source, in crates. This is what the crates do.
--
-- The shelf is inventory.lua, which the build writes while it zips, so the
-- number stencilled on a lid is the size that file actually shipped at. Only the
-- heaviest dozen are out where they can be read and the rest are behind them,
-- counted rather than listed: a shelf longer than the console is a shelf whose
-- top nobody ever sees.
--
-- One crate is named after them, and it has to be. The best thing a crate can
-- say when it opens is that there is more explanation in it than program, and
-- the three crates that manage it are the three smallest interesting files in
-- the package — a shelf of the heaviest twelve can never show one of them, so
-- the funniest line in this file would have been reachable only by typing a
-- name nobody had been given. The shelf points at it instead, and which one it
-- is comes out of the build's own count rather than from anybody choosing.
--
-- Opening one is the other half, and it is a joke rather than a listing. It used
-- to print the first few lines of the file, which sounds like the better idea
-- until you try it: every file in this package opens with a paragraph or four
-- explaining itself, so a crate promised full of Lua opened on prose, and six
-- lines of somebody else's comments is a thin thing to hand a visitor who came
-- down here for the joke. So the file is read and *counted* instead — comment
-- against code — and the ratio is funnier than any line in it.
--
-- The reading is still the whole point. The count is taken now, out of this
-- profile, off the file that shipped: a transcript cannot count itself, and the
-- build's own number on the lid and this one agree because they are two counts
-- of one file.
--
-- Nothing here prints source any more, which took a hazard out with it: decho
-- reads an r,g,b triple in angle brackets as a colour tag, and this package's
-- comments are full of things that look exactly like one — core.lua's palette is
-- literal colour tags. Printing source meant printing it through echo with the
-- colour set around it, because echo is the only output path here that does not
-- parse what it is handed. If anything in this world prints a line of a file
-- again, that is the reason it cannot use say().

local core = require('mudlet-demo.core')
local inventory = require('mudlet-demo.inventory')
local C, say, cmd = core.C, core.say, core.cmd
local spell = core.spell

-- Twelve on the shelf and the rest behind them. Four rows of three in a hero,
-- six of two on a phone, and the whole shelf on screen at either.
local LIMIT = 12

-- Heaviest first, and by name where two are the same size, so the shelf is the
-- same shelf twice running.
local HEAVY = {}
for i, e in ipairs(inventory.files) do HEAVY[i] = e end
table.sort(HEAVY, function(a, b)
    if a.lines ~= b.lines then return a.lines > b.lines end
    return a.path < b.path
end)

local SHOWN = math.min(LIMIT, #HEAVY)

-- The most over-explained crate in the cellar: the highest ratio of comment to
-- Lua, out of what actually shipped as a file. Worked out once, here, so that
-- editing any file in this world can change which crate the shelf sends you to
-- and nothing has to be told about it.
local TALKIEST
for _, e in ipairs(inventory.files) do
    if e.shipped and e.code > 0 and e.comment > e.code then
        if not TALKIEST or e.comment * TALKIEST.code > TALKIEST.comment * e.code then
            TALKIEST = e
        end
    end
end

-- Every lid says .lua, so no lid says it. The name is only what the crate is
-- labelled; `look` is always sent the full path, so nothing has to guess what
-- `core` means.
local function label(e)
    return (e.path:gsub('%.lua$', ''))
end

-- Measured over what is actually on the shelf rather than over the whole
-- package: the twelve heaviest have short names, and a column sized for
-- `rooms/workshop` would be padding held for a name that is not there.
local WIDEST, DIGITS = 0, 1
for i = 1, SHOWN do
    WIDEST = math.max(WIDEST, #label(HEAVY[i]))
    DIGITS = math.max(DIGITS, #tostring(HEAVY[i].lines))
end

local CELL, MAX_COLS = WIDEST + 1 + DIGITS, 3

-- Where the package is, asked of this file rather than assembled out of a name
-- typed twice. debug.getinfo's `source` is the path Lua opened this module from,
-- and package.path is seeded with the profile directory, so it is the package's
-- own directory with this file's name on the end of it.
--
-- Asked for by function rather than by stack level, because a level is counted
-- from whoever is asking and going through pcall makes that pcall itself: the
-- obvious debug.getinfo(1) here describes a C function and matches nothing. And
-- both separators, in a long string so the backslash can be written as itself —
-- Lua's searcher builds that path with the platform's separator, and a pattern
-- that assumed a forward slash silently matched nothing and fell through to the
-- fallback below, which is a fallback doing the real answer's job.
local function packageDir()
    local ok, info = pcall(debug.getinfo, packageDir, 'S')
    local src = ok and info and type(info.source) == 'string' and (info.source:gsub('^@', ''))
    local dir = type(src) == 'string' and src:match([[^(.*)[/\][^/\]+$]])
    if dir and dir ~= '' then return dir end
    -- Only for a build with no debug library at all.
    return getMudletHomeDir() .. '/mudlet-demo'
end

-- The number on the lid, right-aligned under the last digit of the longest of
-- them. Its own segment because the name in front of it is a link, and a link is
-- a decho call of its own with no string in the middle of it to pad.
local function stencil(e)
    return C.dim .. string.rep(' ', WIDEST - #label(e) + 1)
        .. string.format('%' .. DIGITS .. 'd', e.lines)
end

-- What is in a file, counted where it lies. Nil if this build cannot open it,
-- which is a fact about the sandbox rather than about the crate.
--
-- A line beginning with two dashes is a comment, which is true of every line in
-- this package and would not be true of a file with a long comment in it. There
-- are none here, and a crate is not the place to write a Lua parser.
local function count(e)
    local f = io.open(packageDir() .. '/' .. e.path, 'r')
    if not f then return nil end
    local comment, blank, code = 0, 0, 0
    for line in f:lines() do
        if line:match('^%s*%-%-') then comment = comment + 1
        elseif line:match('^%s*$') then blank = blank + 1
        else code = code + 1 end
    end
    f:close()
    return comment, blank, code
end

-- The line the room actually came for. Four bands rather than one, because the
-- package is not uniformly guilty: site.lua is a table and config.lua is five
-- lines of manifest, and telling either of those off for being over-explained
-- would be a joke that had not read the crate.
local function verdict(comment, code)
    if code == 0 then
        return 'Not one line of Lua in it. It is a label on a box, which is a perfectly '
            .. 'good thing for a box to be.'
    elseif comment > code then
        return 'More explanation than program. Every crate down here is like this, and '
            .. 'whoever packed them is not sorry about it.'
    elseif comment * 3 >= code then
        return 'About a word of explanation for every two of Lua, which down here passes '
            .. 'for restraint.'
    end
    return 'Hardly a word of explanation in it — either somebody was in a hurry, or it was '
        .. 'the one file that looked obvious at the time.'
end

local M = {}

-- Read downwards, like the sage's ledger, so the heaviest crate is the first
-- name in the first column rather than the first name in the first row.
function M.list()
    local cols, gap = core.columns(CELL, MAX_COLS, 2)
    local rows = math.ceil(SHOWN / cols)
    for r = 1, rows do
        local line = { C.dim, '  ' }
        for c = 0, cols - 1 do
            local at = r + c * rows
            if at <= SHOWN then
                local e = HEAVY[at]
                line[#line + 1] = cmd(label(e), 'look ' .. e.path, 'get the lid off this one')
                line[#line + 1] = stencil(e)
                if at + rows <= SHOWN then line[#line + 1] = string.rep(' ', gap) end
            end
        end
        say(unpack(line))
    end

    local rest = #HEAVY - SHOWN
    if rest > 0 then
        say(C.dim, 'and ', spell(rest), ' more behind them, the biggest of those ',
            HEAVY[SHOWN + 1].lines, ' lines.')
    end

    -- The light crate the heavy ones would otherwise hide. Named rather than
    -- listed, because the reason to open it is the ratio and not the size.
    if TALKIEST then
        say()
        say(C.dim, 'The one to get the lid off is a small one at the back: ',
            cmd(label(TALKIEST), 'look ' .. TALKIEST.path, 'more talk than program'),
            C.dim, ', which is ', spell(TALKIEST.comment), ' lines of explanation ',
            'wrapped round ', spell(TALKIEST.code), ' of Lua.')
    end
end

function M.open(e)
    say()

    if not e.shipped then
        say(C.text, 'The lid is nailed down, and there is nothing under it to find. ',
            e.path, ' is the one part of this world that does not arrive as a file: it is ',
            'a script inside the packaged XML, run before there is a package directory to ',
            'read anything out of. Its ', e.lines, ' lines are counted with the rest ',
            'because they are part of the world — they are just not part of the shelf.')
        return
    end

    local comment, blank, code = count(e)
    if not comment then
        say(C.text, 'The lid will not shift. Lua in this build cannot open ', e.path,
            ' where it lies, which is a fact about the sandbox rather than about the ',
            'crate: the file is certainly there, because the world you are standing in ',
            'was loaded out of it.')
        return
    end

    say(C.text, 'The lid comes off. ', e.path, ': ', e.lines, ' lines counted where they ',
        'lie — ', comment, ' of comment, ', blank, ' blank, ', code, ' of Lua.')
    say(C.dim, verdict(comment, code))
end

return M
