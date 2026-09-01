-- Connecting to nothing at all -------------------------------------------------
--
-- The fake connect the page hands over to, and the first room printed on the
-- other side of it.

local D = demo
local core = require('mudlet-demo.core')
local seed = require('mudlet-demo.seed')
local C, say, cmd = core.C, core.say, core.cmd
local SEED_WAIT = seed.SEED_WAIT

-- Boot -----------------------------------------------------------------------
--
-- The page draws these same two lines while the client is still loading, in
-- the same colour, metrics and dot rhythm, and drops its copy the moment these
-- appear — so the handover is invisible and what the visitor sees is one client
-- connecting once. Change these and the markup in the theme's hero
-- (template-parts/home/hero.php, .term__boot) has to change with them.
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
end
