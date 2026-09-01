-- The mapper and the bar it hangs in -------------------------------------------
--
-- Kept as one file because they share the icon, the widget geometry and the
-- room ids: the status bar carries the map's own control, and splitting them
-- would mean exporting five private helpers across a seam that is not real.

local D = demo
local core = require('mudlet-demo.core')
local rooms = require('mudlet-demo.rooms')

-- The map --------------------------------------------------------------------
--
-- Not a drawing of a map: Mudlet's own mapper, small, in the corner of the
-- console. "A real mapper" is one of the six claims the page makes two
-- sections down, so the demo may as well be running one — six rooms is a
-- tiny map, but it is the same widget, the same map database and the same
-- centerview() that a twelve-thousand-room game drives.

local AREA = 'mudlet.org'

-- Where a room is drawn is not written down anywhere: it is walked.
--
-- One square per compass step, breadth-first from the front page, so a room's
-- position is a consequence of how you get to it. The exits are already
-- declared once, in the room that has them, and that is the whole of what a
-- new room has to say for itself — the coordinate, the id and the line drawn
-- on the map all follow from it.
--
-- `up` and `down` step one square as well, on the same z, which is what makes
-- the vault read as a cellar: `down` from the front page puts it directly
-- under, and because the exit is called `down` rather than `south` the mapper
-- draws the stair markers and no line — a line would say you can walk into it.
-- All six rooms therefore sit on one level. A cellar on a level of its own
-- would be a map with one room on it, which is accurate and useless at this
-- size.
local STEP = {
    north = {  0,  1 }, south = {  0, -1 },
    east  = {  1,  0 }, west  = { -1,  0 },
    up    = {  0,  1 }, down  = {  0, -1 },
}

local START = 'home'

-- Everything that can go wrong below is a mistake in the exits — ours, made
-- while editing — and not anything a visitor can reach. So it goes to the
-- debug console and the map is drawn regardless: a hero has no business
-- showing anybody a stack trace, and most of a map beats none of one.
local function complain(...)
    if type(debugc) == 'function' then
        debugc('mudlet-demo map: ' .. table.concat({ ... }, ' ') .. '\n')
    end
end

local function sortedKeys(t)
    local keys = {}
    for key in pairs(t) do keys[#keys + 1] = key end
    table.sort(keys)
    return keys
end

-- Ids are derived from the room names, sorted, rather than kept in a list
-- beside them. Renumbering is safe because the area is torn down before it is
-- rebuilt and deleteArea takes its rooms with it. What is *not* safe is
-- createRoomID(), which would stack up a fresh set of rooms on every package
-- reinstall — a profile keeps its map.
local function ids()
    local id = {}
    for i, key in ipairs(sortedKeys(rooms)) do id[key] = i end
    return id
end

-- Breadth-first, taking exits in sorted order, so the layout and anything said
-- about it are the same on every run.
local function layout()
    local at = { [START] = { x = 0, y = 0 } }
    local queue, head = { START }, 1
    while queue[head] do
        local key = queue[head]
        head = head + 1
        local here = at[key]
        for _, dir in ipairs(sortedKeys(rooms[key].exits)) do
            local dest = rooms[key].exits[dir]
            local step = STEP[dir]
            if not rooms[dest] then
                complain(key, 'has an exit', dir, 'to', dest .. ',', 'which is not a room')
            elseif not step then
                complain(key, 'reaches', dest, 'by', dir .. ',', 'which the map cannot step in')
            elseif not at[dest] then
                at[dest] = { x = here.x + step[1], y = here.y + step[2] }
                queue[#queue + 1] = dest
            elseif at[dest].x ~= here.x + step[1] or at[dest].y ~= here.y + step[2] then
                complain(dir, 'from', key, 'would put', dest, 'somewhere it already is not:',
                    'the exits into it disagree about where it is')
            end
        end
    end

    -- A room nothing leads to is a room nobody can see, and two rooms in one
    -- square is a map that lies about the world. Neither stops the drawing.
    local taken = {}
    for _, key in ipairs(sortedKeys(rooms)) do
        local place = at[key]
        if not place then
            complain(key, 'cannot be reached from', START .. ',', 'so it is not on the map')
        else
            local square = place.x .. ',' .. place.y
            if taken[square] then
                complain(key, 'and', taken[square], 'are drawn in the same square')
            end
            taken[square] = key
        end
    end
    return at
end

local ROOM, PLACE = ids(), layout()

local ENV = 200   -- one custom env colour, so the rooms are the site's orange

function D.buildMap()
    local existing = getAreaTable()[AREA]
    if existing then deleteArea(existing) end
    local area = addAreaName(AREA)

    setCustomEnvColor(ENV, 245, 108, 39, 255)
    setMapBackgroundColor(30, 24, 19)   -- the hero terminal's own ground

    -- The map info panel — area, room id, coordinate ranges — is on by default
    -- and, at this size, is the entire widget. Six rooms need no coordinate
    -- readout; the room name is already the line above the map.
    for label in pairs(getMapInfo()) do disableMapInfo(label) end

    for key, id in pairs(ROOM) do
        if PLACE[key] then
            if roomExists(id) then deleteRoom(id) end
            addRoom(id)
            setRoomArea(id, area)
            setRoomCoordinates(id, PLACE[key].x, PLACE[key].y, 0)
            setRoomName(id, rooms[key].title)
            setRoomEnv(id, ENV)
        end
    end

    -- Drawn from the side that declares them, one exit at a time, so a pair
    -- that disagrees comes out as two one-way exits rather than as a guess.
    for key, room in pairs(rooms) do
        for dir, dest in pairs(room.exits) do
            if PLACE[key] and PLACE[dest] then setExit(ROOM[key], ROOM[dest], dir) end
        end
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

-- The exits, as text rather than as controls: the console already prints them
-- as links, but that line scrolls away with the room it belongs to, and on the
-- bar they stay where they can always be reached. Same colour as the console's
-- exits so the two read as one thing, and no border or fill — a row of buttons
-- across the top of a hero is louder than the thing it is pointing at.
local EXIT_COLOUR = '#82c0c7'
-- The bar's text metrics. A label cannot be asked how wide its text came out,
-- so the row after the name is placed by counting characters: ui-monospace at
-- 11px advances about 6.6px, and everything on the left of the bar is set in
-- it. An error here shows up as the gap after the name being a pixel or two
-- out, never as anything overlapping.
local BAR_CHAR, BAR_GAP = 6.6, 8
-- And a label insets its text before it draws it: Mudlet Web gives the echo
-- the 4px Qt gives a QTextDocument's documentMargin, then clips at the label's
-- own edge. A box measured to fit only the word therefore loses the right-hand
-- pixels of its last character - which on a two-letter word is most of it, and
-- 'up' was arriving as 'u' and a sliver.
local BAR_INSET = 4
-- No room here has more than three ways out. The pool is built once and the
-- spares stay hidden: labels created per room would leak a set on every move.
local EXIT_MAX = 4

-- Nothing sets a vertical offset anywhere in this bar. A Mudlet label centres
-- its text on its own — TLabel's default is Qt::AlignLeft | Qt::AlignVCenter —
-- so a label given the bar's full height is centred in it by doing nothing,
-- and a padding-top would be fighting that.
local function exitCss()
    return string.format([[
        background-color: rgba(0,0,0,0);
        color: %s;
        font-family: ui-monospace, monospace;
        font-size: 11px;
    ]], EXIT_COLOUR)
end

-- The pipe between the name and the exits, in the name's own colour: it
-- belongs to neither and separating them is all it does.
local function pipeCss()
    return [[
        background-color: rgba(0,0,0,0);
        color: #6b6053;
        font-family: ui-monospace, monospace;
        font-size: 11px;
    ]]
end

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

    -- The separator and one label per way out, hidden until a room says
    -- otherwise. Built here so the set exists once; placed in D.barExits(),
    -- which runs on every look. Full bar height, so each centres itself.
    D.pipe = Geyser.Label:new({
        name = 'demoBarPipe', x = 0, y = 0, width = '1px', height = BAR_H .. 'px',
    })
    D.pipe:setStyleSheet(pipeCss())
    D.pipe:echo('|')
    D.pipe:hide()

    D.exits = {}
    for i = 1, EXIT_MAX do
        local exit = Geyser.Label:new({
            name = 'demoExit' .. i, x = 0, y = 0, width = '1px', height = BAR_H .. 'px',
        })
        exit:setStyleSheet(exitCss())
        exit:hide()
        D.exits[i] = exit
    end
end

-- The bar's left half. Prefixed with the site's own wordmark mark, so the
-- client's status line and the page's logo read as the same voice.
function D.barName()
    if D.name and D.rooms[D.here] then
        D.name:echo('&gt; ' .. D.rooms[D.here].title)
    end
end

-- The width of the name's column, in characters, worked out once: the longest
-- title in the world plus the '> ' D.barName() writes in front of it. Lazily,
-- because this file is required before rooms/init.lua has finished assembling
-- D.rooms - and by the time a bar is being painted it always has.
local cols
local function nameCols()
    if not cols then
        cols = 0
        for _, room in pairs(D.rooms) do
            cols = math.max(cols, #('> ' .. room.title))
        end
    end
    return cols
end

-- The ways out of this room, written after its name.
--
-- Left to right from where the name ends, which is why BAR_CHAR exists: the
-- name is a label of its own and its width is the count of what was echoed
-- into it. The map control keeps the other end of the bar; nothing here can
-- reach it, because three directions and the longest room name in the world
-- come to less than half the console at its narrowest.
function D.barExits()
    if not D.exits then return end

    local room = D.rooms[D.here]
    local dirs = {}
    if room then
        for dir in pairs(room.exits) do dirs[#dirs + 1] = dir end
        -- pairs() over the exit table has no order, and a row whose words
        -- reshuffle on every look is worse than no row at all.
        table.sort(dirs)
    end

    -- The name's column is as wide as the longest title in the world, not as
    -- wide as this room's: walking from the Release Vault to Makers Hall is six
    -- characters shorter, and exits that slid 40px left under the cursor would
    -- be a worse thing to have fixed in place than a little empty bar.
    local x = 12 + BAR_INSET + math.ceil(nameCols() * BAR_CHAR) + BAR_GAP

    if #dirs > 0 then
        D.pipe:resize((BAR_INSET + math.ceil(BAR_CHAR) + 1) .. 'px', BAR_H .. 'px')
        D.pipe:move(x .. 'px', 0)
        D.pipe:show()
        x = x + BAR_INSET + math.ceil(BAR_CHAR) + 1 + BAR_GAP
    else
        D.pipe:hide()
    end

    for i, exit in ipairs(D.exits) do
        local dir = dirs[i]
        if dir then
            local w = BAR_INSET + math.ceil(#dir * BAR_CHAR) + 1
            exit:resize(w .. 'px', BAR_H .. 'px')
            exit:move(x .. 'px', 0)
            exit:echo(dir)
            -- Re-set every time: the closure has to carry *this* room's
            -- direction, not the one the label was showing in the last one.
            exit:setClickCallback(function() D.go(dir) end)
            exit:show()
            x = x + w + BAR_GAP
        else
            exit:hide()
        end
    end
end

-- The bar follows the world by event rather than by being called from it, so
-- that D.look() can announce the room without knowing a bar exists. In-profile:
-- raiseEvent does not leave this client, and nothing outside it listens.
--
-- The event carries the room's name and its exits for anybody else who cares;
-- this handler reads the world instead, because it also has to repaint on a
-- resize, when no event has been raised.
registerAnonymousEventHandler(core.ROOM_EVENT, function()
    D.barName()
    D.barExits()
end)

function D.mapWidget()
    D.chrome()
    D.bindKeys()
    D.barName()
    D.barExits()
    -- Geyser holds the bar against its edges by itself; the mapper is a window
    -- and has to be repositioned by hand.
    D.mapPaint()
end

-- How the map follows you, when it is open at all.
function D.mapHere()
    if D.mapOpen then centerview(ROOM[D.here]) end
end


-- Double-clicking a room on the map walks you to it ---------------------------
--
-- Mudlet pathfinds first and then hands the walk over: getPath fills
-- speedWalkDir with one direction per step, and calls whatever global
-- doSpeedWalk() the mapper package defines. Define none and mudix sends the
-- directions itself, all at once - which in a world with no socket to send
-- them down means double-clicking a room does nothing whatever.
--
-- So this is that function, and it walks rather than teleports: one step, a
-- pause, the next. Three reasons, and only the first is cosmetic. A burst of
-- commands is what a real game reads as spam and what gets a speedwalk
-- throttled. The room descriptions arrive in order, which is the whole of
-- what there is to watch. And a walk you can see is a walk you can stop --
-- typing anything cancels the rest, the way it does in a real client.

-- Slow enough to read a room title, fast enough not to feel like waiting.
local STEP = 0.45

local walkTimer

-- Called from D.input on every command, so it has to be cheap and silent when
-- there is nothing to stop.
function D.stopWalk()
    if walkTimer then killTimer(walkTimer) walkTimer = nil end
end

function doSpeedWalk()
    D.stopWalk()

    -- Copied out now: speedWalkDir is overwritten by the next getPath, and
    -- this walk outlives its own call by design.
    local dirs = {}
    for i = 1, #speedWalkDir do dirs[i] = core.DIRS[speedWalkDir[i]] or speedWalkDir[i] end
    if #dirs == 0 then return end

    local function step(i)
        walkTimer = nil
        local dir = dirs[i]
        if not dir then return end

        -- The path was found against the map, and the map is drawn from these
        -- same exits - so this should always hold. It is checked because the
        -- alternative to checking is walking someone into a wall on the one
        -- day it does not.
        local room = D.rooms[D.here]
        if not room or not room.exits[dir] then return end

        D.go(dir)
        if dirs[i + 1] then walkTimer = tempTimer(STEP, function() step(i + 1) end) end
    end

    step(1)
end


return { PLACE = PLACE }
