-- What a visitor can do -------------------------------------------------------
--
-- The parser, the verbs behind it, and which of the two people in this world
-- answers a question. Everything typed or clicked arrives at D.input.

local D = demo
local core = require('mudlet-demo.core')
local URL = require('mudlet-demo.urls')
local SITE = require('mudlet-demo.site')
local dl = require('mudlet-demo.download')
local people = require('mudlet-demo.people')
local map = require('mudlet-demo.map')
local C, U, say, link, cmd = core.C, core.U, core.say, core.link, core.cmd
local crateLabel, press = dl.crateLabel, dl.press
local findMaker = people.findMaker
local PLACE = map.PLACE

-- Three people in this world answer questions, and no two of them keep the same
-- kind of book: the sage has the past off a fixed ledger, the clerk has this
-- week off the network, and the imp has every name this client answers to and
-- keeps no book at all — it counts the shelves instead. Which one answers is
-- which room you are standing in.
--
-- `raw` is the noun with its capitals still on, and only the imp has any use
-- for it: see D.input.
function D.ask(noun, raw)
    if D.here == 'workshop' then D.askClerk(noun) return end
    if D.here == 'stacks' then D.askImp(noun, raw or '') return end
    if D.here ~= 'makers' then
        say(C.text, 'There is nobody here to ask. All three of them keep off the commons: ',
            'the sage in Makers Hall to the west of it, the clerk in the Workshop to the ',
            'north, and the imp in the Stacks below.')
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

-- Verbs ----------------------------------------------------------------------

local DIRS = core.DIRS

local function find(noun)
    if noun == '' then return nil end
    for _, thing in ipairs(D.rooms[D.here].things) do
        for _, key in ipairs(thing.keys) do
            if key == noun then return thing end
        end
    end
    -- Second pass, so 'look windows crate' finds the crate and 'look forum'
    -- finds the forum door without every phrasing being spelled out in the rooms.
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

    -- The bar above the console draws the same two facts these lines just
    -- printed. Announced rather than drawn from here: the bar is map.lua's,
    -- and a verb has no business knowing it exists. In look() and not enter()
    -- because boot() opens the first room with a bare look() and never calls
    -- enter(), so a bar filled from the latter would stay empty until the
    -- visitor moved.
    raiseEvent(core.ROOM_EVENT, room.title, table.concat(names, ','))
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
    say(C.sys, 'mudlet.org, walked instead of scrolled. Seven rooms, one website.')
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
    say(C.text, '  ', cmd('fetch <name>', 'fetch tempAlias',
        'the imp keeps a box for every function this client has'),
        C.text, '   ', C.desc, 'the imp in the Stacks deals only in exact names')
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
    local typed = (raw or ''):gsub('^%s+', ''):gsub('%s+$', '')
    local cmdline = typed:lower()
    if cmdline == '' then return end

    -- Anything typed ends a walk still in progress, the way it does in a real
    -- client: the steps that are left belong to a room the visitor has just
    -- stopped caring about. See D.walk() in map.lua.
    D.stopWalk()

    -- Split off the raw line and lower it afterwards, rather than lowering
    -- first: everything here matches in lower case — rooms, nouns, directions —
    -- except the one room where the capitals are the whole point. A Mudlet
    -- function is called registerAnonymousEventHandler and not
    -- registeranonymouseventhandler, and an imp that pretended otherwise would
    -- be teaching the wrong thing. Because `rest` is only ever `rawRest`
    -- lowered, a prefix stripped from one can be cut off the other by length.
    local verb, rawRest = typed:match('^(%S+)%s*(.*)$')
    rawRest = rawRest:gsub('^[Aa][Tt]%s+', ''):gsub('^[Tt][Hh][Ee]%s+', '')
    verb = verb:lower()
    local rest = rawRest:lower()

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
        -- naming the wrong one of the three is not a correction worth printing.
        local noun = rest:gsub('^sage%s*', ''):gsub('^clerk%s*', ''):gsub('^imp%s*', '')
            :gsub('^about%s+', '')
        D.ask(noun, rawRest:sub(#rawRest - #noun + 1))
    elseif verb == 'fetch' or verb == 'box' then
        -- The one verb in this world that is given the line as it was typed.
        -- Refused outside the Stacks rather than answered, because an alias
        -- made in there and used out here should say plainly that it worked and
        -- that the imp is elsewhere.
        if D.here == 'stacks' then
            D.fetchBox(rawRest)
        else
            say(C.text, 'Your hands stay empty. The boxes — and the imp who will not ',
                'reach for one without the name on its lid — are in the Stacks, south ',
                'of the commons.')
        end
    elseif verb == 'alias' then
        -- `alias b`, `alias on b`, `alias for b` all land the same place;
        -- `alias off` takes it away again. See mudlet-demo/catalogue.lua, which
        -- is the other half of what mudlet-demo/trigger.lua demonstrates.
        local word = rest:gsub('^on%s+', ''):gsub('^for%s+', '')
        if word == 'off' or word == 'none' or word == 'stop' then
            D.aliasOff()
        else
            D.aliasFor(word)
        end
    elseif verb == 'kettle' or verb == 'boil' or verb == 'brew' or verb == 'put' then
        -- `put the kettle on`, `boil`, `kettle` all land the same place;
        -- `kettle off` lifts the switch again. The third of the three things
        -- this world hands over — see mudlet-demo/kettle.lua, and the two rooms
        -- either side of it in trigger.lua and catalogue.lua.
        --
        -- `put` is here rather than in a verb of its own because there is
        -- exactly one thing in this world to put anywhere; `put the crate down`
        -- falls through to the same place every other unknown line does.
        local word = rest:gsub('^kettle%s*', ''):gsub('^on%s*$', '')
        if verb == 'put' and word == rest then
            say(C.text, rest == '' and 'Put what, where?'
                or 'Nothing here goes anywhere else.')
        elseif word == 'off' or word == 'none' or word == 'stop' or word == 'up' then
            D.kettleOff()
        elseif D.here == 'workshop' then
            D.boil()
        else
            -- Refused where there is no kettle, and told where one is - the same
            -- bargain `fetch` strikes outside the Stacks. A timer already
            -- ticking is the client's and goes on ticking regardless.
            say(C.text, 'There is nothing to boil in here. The kettle is on the bench in ',
                'the Workshop, north of the commons.')
        end
    elseif verb == 'trigger' or verb == 'trig' or verb == 'watch' then
        -- `trigger on gold`, `trigger for gold`, `trigger gold` all land the
        -- same place; `trigger off` takes it away again. See
        -- mudlet-demo/trigger.lua — that and mudlet-demo/catalogue.lua are the
        -- two things here that script the client rather than describing it, one
        -- in each direction.
        local word = rest:gsub('^on%s+', ''):gsub('^for%s+', '')
        if word == 'off' or word == 'none' or word == 'stop' then
            D.triggerOff()
        else
            D.watchFor(word)
        end
    elseif verb == 'help' or verb == 'commands' or verb == '?' then
        D.help()
    elseif REPLIES[verb] then
        REPLIES[verb]()
    else
        say(C.text, "Nothing happens. Try ", cmd('look', 'look', 'look around'),
            C.text, ', or ', cmd('help', 'help', 'the short list of commands'), C.text .. '.')
    end
end
