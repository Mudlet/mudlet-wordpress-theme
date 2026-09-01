-- Colour, links and words -----------------------------------------------------
--
-- What everything else in this package prints with, and nothing about the world
-- itself: the palette, the two kinds of clickable, the single output path they
-- share, and the numbers-into-words the prose is written in.

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

-- The world announces the room it is in under this name, and whatever draws a
-- status line listens for it. In this package rather than in either end of it
-- so that the raise and the handler cannot drift apart.
local ROOM_EVENT = 'mudlet-demo:room'

-- Every way of naming a direction, long and short. Here rather than in
-- verbs.lua because the mapper speaks the short forms: getPath fills
-- speedWalkDir with "n"/"up", and the walk has to turn those into the words
-- the rooms declare their exits with.
local DIRS = {
    n = 'north', s = 'south', e = 'east', w = 'west', u = 'up', d = 'down',
    north = 'north', south = 'south', east = 'east', west = 'west',
    up = 'up', down = 'down',
}

return {
    C = C, U = U, SCRIPT_LINES = SCRIPT_LINES, ROOM_EVENT = ROOM_EVENT, DIRS = DIRS,
    thousands = thousands, link = link, cmd = cmd, say = say,
    spell = spell, spellCap = spellCap,
}
