-- The Sub-Cellar, down from the Release Vault: the world's own source, in
-- crates. A cellar under a cellar, which is what `down` twice ought to get you.
--
-- The only room here standing in for no page on the site, and the only one that
-- teaches nothing the other eight have not taught already. It is the easter
-- egg — the world admitting what it is made of — and it is allowed to be that
-- once. It is reached the way everything else is, by an exit the room above
-- prints in its own list, and it is on the map two squares under the front
-- page: a cellar under a cellar, which is the joke `down` twice ought to get
-- you. Nothing about it is hidden; it is simply further down than most people
-- go.
--
-- It is also the only room whose furniture links to nothing. Everywhere else a
-- thing opens the real page it parodies; there is no page behind a cellar.
--
-- Three of the things down here are jokes, and all three are about languages
-- rather than about people, which is what lets them survive the next person to
-- commit to this file. The row of crates starts at one, and there is a rectangle
-- painted where the zeroth would go, for everyone who arrives from a language
-- that counts from nothing. There is a pad of forms for declaring in advance
-- everything that might go wrong, which is what a checked exception is and which
-- nothing here has ever filled in. And there is a bin that empties itself on a
-- schedule nobody sets and is under no obligation to do it when asked, which is
-- every garbage collector anybody has ever shouted at.
--
-- The fourth thing is a mark, and it is deliberately not a signature. A byline
-- on a crate would claim a file that other people will edit, and be wrong the
-- first time one of them does. This claims nothing: two stencils on the inside
-- of a lid, a DEL key and a wing, left by whoever was last working down here.
-- The name they make is never printed in this room — but the sage upstairs
-- keeps a ledger, and answers to it, so the visitor who reads the lid has
-- somewhere to take it. That is the whole of the puzzle and it is meant to be
-- solvable in one hop, not guessed.
--
-- The furniture is real, though, and the crates are not typed: they and the
-- numbers stencilled on them are inventory.lua, which the build writes while it
-- zips, and opening one reads and counts the file out of the profile's own
-- directory. Both halves are in mudlet-demo/crates.lua.

local D = demo
local core = require('mudlet-demo.core')
local inventory = require('mudlet-demo.inventory')
local crates = require('mudlet-demo.crates')
local season = require('mudlet-demo.season')
local C, say, cmd = core.C, core.say, core.cmd
local spell, spellCap, thousands = core.spell, core.spellCap, core.thousands

local GHOST_LINES = 2111

local things = {
    {
        name = 'the crates',
        keys = { 'crates', 'crate', 'shelves', 'shelf', 'files', 'modules', 'source', 'code' },
        -- One line over the shelf and nothing under it: twelve crates in four
        -- rows and six lines all told, which is a hero-sized console with
        -- nothing scrolled off the top of it. The shelf is the last thing
        -- printed, so it is the part still on screen.
        look = function()
            say(C.text, spellCap(#inventory.files), ' crates, ',
                thousands(inventory.lines), ' lines. The heavy ones are at the front:')
            say()
            crates.list()
        end,
    },
    {
        name = 'the empty space',
        keys = { 'space', 'rectangle', 'gap', 'floor', 'zero', '0', 'outline' },
        look = function()
            say(C.text, 'A rectangle painted on the floor at the head of the row, exactly ',
                'the size of a crate, with a 0 stencilled inside it. Nothing has ever ',
                'stood in it, and nothing is going to.')
            say(C.dim, 'The crates begin at one. Everybody who comes down here from some ',
                'other language stands looking at this space for a moment.')
        end,
    },
    {
        name = 'a pad of forms',
        keys = { 'form', 'forms', 'pad', 'declaration', 'paperwork', 'nail' },
        look = function()
            say(C.text, 'On a nail by the stairs: DECLARATION OF THINGS THAT MAY GO WRONG, ',
                'to be completed in advance, in triplicate, and signed before any work ',
                'begins.')
            say(C.dim, 'The pad is full. Not one has been torn off. Down here a thing goes ',
                'wrong at the moment it goes wrong, and the room finds out when you do.')
        end,
    },
    {
        name = 'the bin',
        keys = { 'bin', 'sack', 'rubbish', 'trash', 'waste', 'collector' },
        look = function()
            say(C.text, 'A wheeled bin at the end of the row, for anything down here that ',
                'nothing else reaches any more. It is empty. It is always empty.')
            say(C.dim, 'It takes itself out on a schedule nobody sets, at a moment nobody ',
                'picks. You can ask it to go now. It will consider your request.')
        end,
    },
    {
        name = 'the open crate',
        keys = { 'open crate', 'open', 'tools', 'crowbar', 'work', 'trestle' },
        look = function()
            say(C.text, 'One crate at the far end has been opened and not closed again. The ',
                'lid is off and propped against the trestle, and there are tools on the ',
                'floor beside it that somebody meant to come back for.')
            say(C.dim, 'Whatever was being done down here was not finished. From where you ',
                'are standing you are looking at the ',
                cmd('inside of the lid', 'look lid', 'the underside, facing out', C.dim),
                C.dim, '.')
        end,
    },
    {
        -- Hidden, and reached from the crate above rather than from the room's
        -- own list: a mark you are pointed at is a mark, and a mark on the
        -- furniture list is a plaque.
        name = 'the inside of the lid',
        keys = { 'lid', 'underside', 'inside', 'stencils', 'stencil', 'mark', 'marks' },
        hidden = true,
        look = function()
            say(C.text, 'Two stencils, small, near the hinge, in the same paint as the ',
                'numbers on the lids. One is a keyboard key with DEL on it. The other is ',
                'a wing.')
            say(C.dim, 'There is nothing else on the lid and no explanation anywhere in ',
                'this cellar. Whoever leaves these has been down here more than once.')
            say(C.dim, 'The sage in Makers Hall keeps a ledger of everyone who ever built ',
                'any of this, and will answer to a name if you have got one.')
        end,
    },
    {
        -- One night a year, the file this world used to be. Until 650a95a the
        -- whole package was world.lua, one file of 2,111 lines - `git show
        -- 650a95a^:demo/packages/mudlet-demo/world.lua | wc -l` - and the crates
        -- on these trestles are what it was split into. The figure is typed
        -- because it is a fact about a commit, which no edit can change; the
        -- crates it is set against are counted, and that comparison is the
        -- joke, because the split came out longer than the thing it split.
        name = 'a ghost',
        keys = { 'ghost', 'spirit', 'spectre', 'phantom', 'world.lua' },
        npc = true,
        when = season.halloween,
        presence = function()
            say(C.text, 'A ', cmd('ghost', 'look ghost', 'look at the ghost', C.say),
                C.text, ' drifts the length of the row, counting the crates under its ',
                'breath and losing its place.')
        end,
        look = function()
            say(C.text, 'Pale, and very long — longer than anything else down here, and ',
                'it has to stoop. Across its chest, faded almost to nothing, a stencil: ',
                'world.lua, ', thousands(GHOST_LINES), ' lines.')
            say(C.text, 'It was the whole world once, every room of it in one file, until ',
                'the night it was split into the crates in front of you. It counts them, ',
                'gets to ', spell(#inventory.files), ', and starts again, as though one ',
                'of them must be it.')
            if inventory.lines > 0 then
                say(C.dim, 'Between them the crates come to ', thousands(inventory.lines),
                    ' lines now. It was ', thousands(GHOST_LINES), ', and it did everything.')
            end
        end,
    },
}

-- What the ghost says to anything put to it, and what it does about `boo`.
-- Both are reached from verbs.lua, which only asks on the night it is here.
function D.askGhost()
    say(C.say, '"Two thousand, one hundred and eleven," the ghost says, to nobody, and ',
        'goes back to counting.')
end

function D.booGhost()
    say(C.text, 'The ghost goes clean through a crate and comes out the far side reading ',
        'slightly differently. It has been refactored before. It is not frightened of you.')
end

-- One hidden thing per crate, so `look core.lua` gets a lid off. Hidden rather
-- than listed: a crate name per file in the room's `Here:` line would bury the
-- things worth looking at, and the list of them is what the shelf prints.
--
-- The short names are added only where they name one crate. There are two
-- init.lua in this world, and a noun that reaches both should reach neither:
-- the full path still says which is wanted.
local seen = {}
for _, e in ipairs(inventory.files) do
    local base = e.path:match('[^/]+$')
    seen[base] = (seen[base] or 0) + 1
end

for _, e in ipairs(inventory.files) do
    local base = e.path:match('[^/]+$')
    local keys = { e.path }
    if seen[base] == 1 then
        keys[#keys + 1] = base
        keys[#keys + 1] = (base:gsub('%.lua$', ''))
    end
    things[#things + 1] = {
        name = e.path,
        keys = keys,
        hidden = true,
        look = function() crates.open(e) end,
    }
end

return {
    title = 'The Sub-Cellar',
    desc = function()
        return 'Lower than the vault, half the size of it, and nothing down here is for '
            .. 'sale. ' .. spellCap(#inventory.files) .. ' crates stand on trestles in one '
            .. 'long row, each stencilled with a filename and a line count, and between '
            .. 'them they are the world you are standing in — this room included, which '
            .. 'takes a moment. The row starts at one. At the far end, a crate has been '
            .. 'left open.'
            .. (season.halloween() and ' Tonight it is colder down here than it ought to be.' or '')
    end,
    exits = { up = 'vault' },
    symbol = '▦',
    things = things,
}
