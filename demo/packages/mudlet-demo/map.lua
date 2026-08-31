-- The mapper and the bar it hangs in -------------------------------------------
--
-- Kept as one file because they share the icon, the widget geometry and the
-- room ids: the status bar carries the map's own control, and splitting them
-- would mean exporting five private helpers across a seam that is not real.

local D = demo

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

return { PLACE = PLACE }
